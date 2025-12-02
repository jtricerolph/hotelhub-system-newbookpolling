<?php
/**
 * NewBook API polling handler
 *
 * @package NewBook_Polling
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBP_Poller {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('nbp_poll_newbook', array($this, 'poll_newbook'));
        add_action('nbp_cleanup_buffer', array($this, 'cleanup_buffer'));
    }

    /**
     * Poll NewBook API for changes
     */
    public function poll_newbook() {
        // Check if polling is enabled
        if (!get_option('nbp_polling_enabled', true)) {
            return;
        }

        // Get all active locations from Hotel Hub
        $hotels = hha()->hotels->get_all();

        if (empty($hotels)) {
            return;
        }

        foreach ($hotels as $hotel) {
            if (!$hotel->is_active) {
                continue;
            }

            $this->poll_location($hotel->id);
        }
    }

    /**
     * Poll a specific location
     */
    private function poll_location($location_id) {
        // Get NewBook integration
        $integration = hha()->integrations->get($location_id, 'newbook');

        if (!$integration || !$integration->is_active) {
            return;
        }

        // Get decrypted settings
        $settings = hha()->integrations->get_settings($location_id, 'newbook');

        if (!$settings) {
            error_log('[NBP] No NewBook settings found for location ' . $location_id);
            return;
        }

        // Get last check time (default to 2 minutes ago for first run)
        $option_key = 'nbp_last_check_' . $location_id;
        $last_check = get_option($option_key, date('Y-m-d H:i:s', strtotime('-2 minutes')));

        try {
            // Initialize NewBook API
            $api = new HHA_NewBook_API($settings);

            // Poll for changes using list_type:all
            $response = $api->get_bookings(
                $last_check,
                current_time('mysql'),
                'all',
                true  // force_refresh
            );

            // Store changes in buffer
            if (!empty($response['data']) && is_array($response['data'])) {
                $stored_count = $this->store_changes($location_id, $response['data']);

                // Log success
                error_log(sprintf(
                    '[NBP] Location %d: Found %d changes, stored %d in buffer',
                    $location_id,
                    count($response['data']),
                    $stored_count
                ));
            }

            // Update last check timestamp
            update_option($option_key, current_time('mysql'));

        } catch (Exception $e) {
            error_log('[NBP] Polling error for location ' . $location_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Store booking changes in buffer
     */
    private function store_changes($location_id, $bookings) {
        global $wpdb;
        $table = $wpdb->prefix . NBP_TABLE_PREFIX . 'change_buffer';

        $stored_count = 0;
        $skipped_missing_fields = 0;
        $skipped_duplicates = 0;
        $failed_inserts = 0;

        foreach ($bookings as $booking) {
            // Validate required fields
            if (empty($booking['booking_id']) || empty($booking['arrival_date']) || empty($booking['departure_date'])) {
                $skipped_missing_fields++;
                error_log('[NBP] Skipped booking - missing required fields: ' . json_encode(array(
                    'booking_id' => isset($booking['booking_id']) ? $booking['booking_id'] : 'missing',
                    'arrival_date' => isset($booking['arrival_date']) ? $booking['arrival_date'] : 'missing',
                    'departure_date' => isset($booking['departure_date']) ? $booking['departure_date'] : 'missing'
                )));
                continue;
            }

            // Check if this booking is already in buffer (recent duplicate)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE location_id = %d
                 AND booking_id = %s
                 AND detected_at > %s",
                $location_id,
                $booking['booking_id'],
                date('Y-m-d H:i:s', strtotime('-30 seconds'))
            ));

            if ($existing) {
                $skipped_duplicates++;
                continue;
            }

            // Insert into buffer
            $result = $wpdb->insert(
                $table,
                array(
                    'location_id'    => $location_id,
                    'booking_id'     => $booking['booking_id'],
                    'booking_data'   => json_encode($booking),
                    'arrival_date'   => date('Y-m-d', strtotime($booking['arrival_date'])),
                    'departure_date' => date('Y-m-d', strtotime($booking['departure_date'])),
                    'detected_at'    => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result) {
                $stored_count++;
            } else {
                $failed_inserts++;
                error_log('[NBP] Failed to insert booking ' . $booking['booking_id'] . ': ' . $wpdb->last_error);
            }
        }

        // Log summary
        if ($skipped_missing_fields > 0 || $skipped_duplicates > 0 || $failed_inserts > 0) {
            error_log(sprintf(
                '[NBP] Location %d buffer summary - Stored: %d, Skipped (missing fields): %d, Skipped (duplicates): %d, Failed: %d',
                $location_id,
                $stored_count,
                $skipped_missing_fields,
                $skipped_duplicates,
                $failed_inserts
            ));
        }

        return $stored_count;
    }

    /**
     * Clean up old buffer entries
     */
    public function cleanup_buffer() {
        global $wpdb;
        $table = $wpdb->prefix . NBP_TABLE_PREFIX . 'change_buffer';

        $ttl = get_option('nbp_buffer_ttl', 300); // Default 5 minutes
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $ttl . ' seconds'));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE detected_at < %s",
            $cutoff
        ));

        if ($deleted > 0) {
            error_log('[NBP] Cleaned up ' . $deleted . ' old buffer entries');
        }
    }

    /**
     * Get buffer statistics
     */
    public function get_buffer_stats() {
        global $wpdb;
        $table = $wpdb->prefix . NBP_TABLE_PREFIX . 'change_buffer';

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_entries,
                MIN(detected_at) as oldest_entry,
                MAX(detected_at) as newest_entry
            FROM {$table}
        ", ARRAY_A);

        return $stats;
    }
}
