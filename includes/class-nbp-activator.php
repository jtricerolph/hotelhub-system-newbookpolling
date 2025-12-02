<?php
/**
 * Plugin activation handler
 *
 * @package NewBook_Polling
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBP_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::schedule_cron_events();
        self::set_default_options();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . NBP_TABLE_PREFIX;

        // Change buffer table
        $sql = "CREATE TABLE IF NOT EXISTS {$table_prefix}change_buffer (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL,
            booking_id VARCHAR(100) NOT NULL,
            booking_data LONGTEXT NOT NULL,
            arrival_date DATE NOT NULL,
            departure_date DATE NOT NULL,
            detected_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY location_idx (location_id),
            KEY dates_idx (arrival_date, departure_date),
            KEY detected_idx (detected_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Schedule WP-Cron events
     */
    private static function schedule_cron_events() {
        // Register custom schedule temporarily for activation
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule_temp'));

        // Schedule polling event (every 60 seconds)
        if (!wp_next_scheduled('nbp_poll_newbook')) {
            wp_schedule_event(time(), 'nbp_one_minute', 'nbp_poll_newbook');
        }

        // Schedule cleanup event (hourly)
        if (!wp_next_scheduled('nbp_cleanup_buffer')) {
            wp_schedule_event(time(), 'hourly', 'nbp_cleanup_buffer');
        }

        // Remove temporary filter
        remove_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule_temp'));
    }

    /**
     * Temporary cron schedule registration for activation
     */
    public static function add_cron_schedule_temp($schedules) {
        $schedules['nbp_one_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'nbp')
        );
        return $schedules;
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        add_option('nbp_polling_enabled', true);
        add_option('nbp_polling_interval', 60);
        add_option('nbp_buffer_ttl', 300); // 5 minutes
        add_option('nbp_version', NBP_VERSION);
    }
}
