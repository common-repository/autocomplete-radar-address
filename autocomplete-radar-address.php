<?php
/*
Plugin Name: Autocomplete Radar Address
Description: A WordPress plugin that uses the Radar API for address autocomplete functionality.
Version: 1.0
Author: Sunil and Jay
Author Email: info@nephilainc.com
Author URI: https://nephilainc.com/
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue scripts and styles for frontend and backend
function autordr_autocomplete_enqueue_scripts() {
    $plugin_version = '1.0';
    
    // Enqueue the local CSS file
    wp_register_style('ara-radar-css', plugin_dir_url(__FILE__) . 'css/radar.css', array(), $plugin_version);
    
    // Enqueue the local JS files
    wp_register_script('ara-radar-js', plugin_dir_url(__FILE__) . 'js/radar.min.js', array(), $plugin_version, true);
    wp_register_script('ara-custom-js', plugin_dir_url(__FILE__) . 'js/autocomplete-radar.js', array('jquery', 'ara-radar-js'), $plugin_version, true);
    
    // Add the defer attribute to the ara-radar-js script
    wp_script_add_data('ara-radar-js', 'defer', true);

    // Enqueue the registered styles and scripts
    wp_enqueue_style('ara-radar-css');
    wp_enqueue_script('ara-radar-js');
    wp_enqueue_script('ara-custom-js');
    
    // Localize script to pass the API key and field IDs to the JS file
    $api_key = get_option('autordr_radautordr_api_key');
    $frontend_field_ids = get_option('autordr_frontend_field_ids');
    $backend_field_ids = get_option('autordr_backend_field_ids');
    
    wp_localize_script('ara-custom-js', 'autordr_settings', array(
        'api_key' => $api_key,
        'frontend_field_ids' => array_map('trim', explode(',', $frontend_field_ids)),
        'backend_field_ids' => array_map('trim', explode(',', $backend_field_ids))
    ));
}
add_action('wp_enqueue_scripts', 'autordr_autocomplete_enqueue_scripts');
add_action('admin_enqueue_scripts', 'autordr_autocomplete_enqueue_scripts');







// Add settings page
function autordr_add_settings_page() {
    add_menu_page('Autocomplete Radar Address', 'Autocomplete Radar Address', 'manage_options', 'autocomplete-radar-address', 'autordr_settings_page', 'dashicons-location-alt', 26);
}
add_action('admin_menu', 'autordr_add_settings_page');

// Display settings page
function autordr_settings_page() {
    ?>
    <div class="wrap">
        <h1>Autocomplete Radar Address Settings</h1>
        <p>Register at <a href="https://radar.com/login" target="_blank">Radar</a> to get the API keys.</p>
        <form method="post" action="options.php">
            <?php
            settings_fields('autordr_settings_group');
            do_settings_sections('autordr_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Radar API Key</th>
                    <td><input type="text" name="autordr_radautordr_api_key" value="<?php echo esc_attr(get_option('autordr_radautordr_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Frontend Field IDs (comma-separated)</th>
                    <td><input type="text" name="autordr_frontend_field_ids" value="<?php echo esc_attr(get_option('autordr_frontend_field_ids')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Backend Field IDs (comma-separated)</th>
                    <td><input type="text" name="autordr_backend_field_ids" value="<?php echo esc_attr(get_option('autordr_backend_field_ids')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
function autordr_register_settings() {
    register_setting('autordr_settings_group', 'autordr_radautordr_api_key');
    register_setting('autordr_settings_group', 'autordr_frontend_field_ids');
    register_setting('autordr_settings_group', 'autordr_backend_field_ids');
}
add_action('admin_init', 'autordr_register_settings');

// Helper function to sanitize field IDs
function autordr_sanitize_field_id($field_id) {
    return preg_replace('/[^a-zA-Z0-9_]/', '_', trim($field_id));
}

// Inline JavaScript for Frontend Radar initialization
function autordr_initialize_radar_inline_script() {
    $api_key = get_option('autordr_radautordr_api_key');
    $frontend_field_ids = get_option('autordr_frontend_field_ids');
    
    if ($api_key && $frontend_field_ids) {
        $field_ids_array = explode(',', $frontend_field_ids);
        
        // Start constructing the inline JavaScript
        $inline_script = "document.addEventListener('DOMContentLoaded', function() {";
        $inline_script .= "Radar.initialize('" . esc_js($api_key) . "');";
        
        foreach ($field_ids_array as $field_id) {
            $originalFieldId = esc_js(trim($field_id));
            $sanitizedFieldId = esc_js(autordr_sanitize_field_id($field_id));

            $inline_script .= "(function() {
                var originalFieldId = '{$originalFieldId}';
                var sanitizedFieldId = '{$sanitizedFieldId}';
                var field = document.getElementById(originalFieldId);
                if (field) {
                    var dataListId = sanitizedFieldId + '-suggestions';
                    var dataList = document.getElementById(dataListId);
                    if (!dataList) {
                        dataList = document.createElement('datalist');
                        dataList.id = dataListId;
                        document.body.appendChild(dataList);
                        field.setAttribute('list', dataListId);
                    }
                    field.addEventListener('input', function(event) {
                        const query = event.target.value;
                        if (query.length > 2) {
                            Radar.autocomplete({ query: query, limit: 5 }).then((result) => {
                                const suggestions = result.addresses;
                                dataList.innerHTML = '';
                                suggestions.forEach((address) => {
                                    const option = document.createElement('option');
                                    option.value = address.formattedAddress;
                                    dataList.appendChild(option);
                                });
                            }).catch((err) => {
                                console.error(err);
                            });
                        }
                    });
                }
            })();";
        }

        $inline_script .= "});";

        // Add the inline script to the previously enqueued custom JS
        wp_add_inline_script('ara-custom-js', $inline_script);
    }
}
add_action('wp_enqueue_scripts', 'autordr_initialize_radar_inline_script');

// Inline JavaScript for Admin Radar initialization
function autordr_initialize_radar_admin_inline_script() {
    $api_key = get_option('autordr_radautordr_api_key');
    $backend_field_ids = get_option('autordr_backend_field_ids');
    
    if ($api_key && $backend_field_ids) {
        $field_ids_array = explode(',', $backend_field_ids);

        // Start constructing the inline JavaScript
        $inline_script = "document.addEventListener('DOMContentLoaded', function() {";
        $inline_script .= "Radar.initialize('" . esc_js($api_key) . "');";
        
        foreach ($field_ids_array as $field_id) {
            $originalFieldId = esc_js(trim($field_id));
            $sanitizedFieldId = esc_js(autordr_sanitize_field_id($field_id));

            $inline_script .= "(function() {
                var originalFieldId = '{$originalFieldId}';
                var sanitizedFieldId = '{$sanitizedFieldId}';
                var field = document.getElementById(originalFieldId);
                if (field) {
                    var dataListId = sanitizedFieldId + '-suggestions';
                    var dataList = document.getElementById(dataListId);
                    if (!dataList) {
                        dataList = document.createElement('datalist');
                        dataList.id = dataListId;
                        document.body.appendChild(dataList);
                        field.setAttribute('list', dataListId);
                    }
                    field.addEventListener('input', function(event) {
                        const query = event.target.value;
                        if (query.length > 2) {
                            Radar.autocomplete({ query: query, limit: 5 }).then((result) => {
                                const suggestions = result.addresses;
                                dataList.innerHTML = '';
                                suggestions.forEach((address) => {
                                    const option = document.createElement('option');
                                    option.value = address.formattedAddress;
                                    dataList.appendChild(option);
                                });
                            }).catch((err) => {
                                console.error(err);
                            });
                        }
                    });
                }
            })();";
        }

        $inline_script .= "});";

        // Add the inline script to the previously enqueued custom JS for admin
        wp_add_inline_script('ara-custom-js', $inline_script);
    }
}
add_action('admin_enqueue_scripts', 'autordr_initialize_radar_admin_inline_script');

?>
