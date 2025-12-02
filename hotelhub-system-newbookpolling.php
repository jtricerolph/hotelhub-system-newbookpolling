<?php
/**
 * Plugin Name: Hotel Hub System - NewBook Polling
 * Plugin URI: https://github.com/jtricerolph/hotelhub-system-newbookpolling
 * Description: Centralized NewBook API polling service for real-time booking updates across Hotel Hub modules
 * Version: 1.0.0
 * Author: JTR
 * License: GPL v2 or later
 * Text Domain: nbp
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NBP_VERSION', '1.0.0');
define('NBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NBP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NBP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('NBP_TABLE_PREFIX', 'nbp_');

/**
 * Plugin activation
 */
function nbp_activate() {
    require_once NBP_PLUGIN_DIR . 'includes/class-nbp-activator.php';
    NBP_Activator::activate();
}
register_activation_hook(__FILE__, 'nbp_activate');

/**
 * Plugin deactivation
 */
function nbp_deactivate() {
    // Clear scheduled cron events
    $timestamp = wp_next_scheduled('nbp_poll_newbook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'nbp_poll_newbook');
    }

    $timestamp = wp_next_scheduled('nbp_cleanup_buffer');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'nbp_cleanup_buffer');
    }
}
register_deactivation_hook(__FILE__, 'nbp_deactivate');

/**
 * Check if Hotel Hub App is active
 */
function nbp_check_dependencies() {
    if (!function_exists('hha')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>NewBook Polling</strong> requires the <strong>Hotel Hub App</strong> plugin to be installed and activated.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Initialize plugin
 */
function nbp_init() {
    if (!nbp_check_dependencies()) {
        return;
    }

    require_once NBP_PLUGIN_DIR . 'includes/class-nbp-core.php';

    // Initialize the plugin
    NBP_Core::get_instance();
}
add_action('plugins_loaded', 'nbp_init');

/**
 * Global accessor function
 */
function nbp() {
    return NBP_Core::get_instance();
}
