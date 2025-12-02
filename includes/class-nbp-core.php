<?php
/**
 * Core plugin class
 *
 * @package NewBook_Polling
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBP_Core {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Poller instance
     */
    public $poller;

    /**
     * Heartbeat instance
     */
    public $heartbeat;

    /**
     * Admin instance
     */
    public $admin;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once NBP_PLUGIN_DIR . 'includes/class-nbp-poller.php';
        require_once NBP_PLUGIN_DIR . 'includes/class-nbp-heartbeat.php';

        if (is_admin()) {
            require_once NBP_PLUGIN_DIR . 'includes/class-nbp-admin.php';
        }
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->poller = new NBP_Poller();
        $this->heartbeat = new NBP_Heartbeat();

        if (is_admin()) {
            $this->admin = new NBP_Admin();
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedule'));

        // Register with Hotel Hub
        add_filter('hha_register_modules', array($this, 'register_with_hotel_hub'));
    }

    /**
     * Add custom cron schedule (every 60 seconds)
     */
    public function add_cron_schedule($schedules) {
        $schedules['nbp_one_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'nbp')
        );
        return $schedules;
    }

    /**
     * Register module with Hotel Hub
     */
    public function register_with_hotel_hub($modules) {
        $modules['newbook-polling'] = array(
            'name'        => 'NewBook Polling',
            'description' => 'Real-time NewBook API change detection and distribution service',
            'version'     => NBP_VERSION,
            'department'  => 'System',
            'icon'        => 'sync',
            'settings_pages' => array(
                array(
                    'slug'       => 'nbp-settings',
                    'title'      => 'NewBook Polling Settings',
                    'menu_title' => 'NewBook Polling',
                    'callback'   => array($this->admin, 'render_settings_page')
                )
            )
        );

        return $modules;
    }
}
