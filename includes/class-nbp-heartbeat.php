<?php
/**
 * WordPress Heartbeat integration
 *
 * @package NewBook_Polling
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBP_Heartbeat {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 3);
    }

    /**
     * Handle heartbeat received event
     */
    public function heartbeat_received($response, $data, $screen_id) {
        // Only process if client is monitoring NewBook updates
        if (!isset($data['nbp_monitor'])) {
            return $response;
        }

        $monitor_data = $data['nbp_monitor'];

        // Validate required fields
        if (empty($monitor_data['location_id']) ||
            empty($monitor_data['date_from']) ||
            empty($monitor_data['date_to'])) {
            return $response;
        }

        $location_id = intval($monitor_data['location_id']);
        $date_from = sanitize_text_field($monitor_data['date_from']);
        $date_to = sanitize_text_field($monitor_data['date_to']);
        $last_check = isset($monitor_data['last_check']) ?
            sanitize_text_field($monitor_data['last_check']) :
            date('Y-m-d H:i:s', strtotime('-1 minute'));

        // Get buffered changes for this location and date range
        $changes = $this->get_buffered_changes($location_id, $date_from, $date_to, $last_check);

        // Add to heartbeat response
        $response['nbp_updates'] = array(
            'bookings' => $changes,
            'timestamp' => current_time('mysql'),
            'count' => count($changes)
        );

        return $response;
    }

    /**
     * Get buffered changes matching criteria
     */
    private function get_buffered_changes($location_id, $date_from, $date_to, $last_check) {
        global $wpdb;
        $table = $wpdb->prefix . NBP_TABLE_PREFIX . 'change_buffer';

        // Query for bookings that overlap the requested date range
        // and were detected after the client's last check
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT booking_data, detected_at
            FROM {$table}
            WHERE location_id = %d
              AND (
                  (arrival_date BETWEEN %s AND %s)
                  OR (departure_date BETWEEN %s AND %s)
                  OR (arrival_date <= %s AND departure_date >= %s)
              )
              AND detected_at > %s
            ORDER BY detected_at ASC
        ",
            $location_id,
            $date_from, $date_to,
            $date_from, $date_to,
            $date_from, $date_to,
            $last_check
        ));

        $bookings = array();

        foreach ($results as $row) {
            $booking = json_decode($row->booking_data, true);
            if ($booking) {
                $bookings[] = $booking;
            }
        }

        return $bookings;
    }

    /**
     * Get buffer contents for a location (for debugging)
     */
    public function get_buffer_contents($location_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . NBP_TABLE_PREFIX . 'change_buffer';

        if ($location_id) {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT *
                FROM {$table}
                WHERE location_id = %d
                ORDER BY detected_at DESC
                LIMIT 100
            ", $location_id), ARRAY_A);
        } else {
            $results = $wpdb->get_results("
                SELECT *
                FROM {$table}
                ORDER BY detected_at DESC
                LIMIT 100
            ", ARRAY_A);
        }

        return $results;
    }
}
