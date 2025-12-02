<?php
/**
 * Admin settings page
 *
 * @package NewBook_Polling
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBP_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'handle_manual_trigger'));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Get polling status
        $polling_enabled = get_option('nbp_polling_enabled', true);
        $buffer_stats = nbp()->poller->get_buffer_stats();
        $hotels = hha()->hotels->get_all();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="nbp-admin-container">
                <!-- Polling Status Card -->
                <div class="nbp-card">
                    <h2>Polling Status</h2>
                    <table class="form-table">
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php if ($polling_enabled): ?>
                                    <span class="nbp-status-badge nbp-status-active">Active</span>
                                <?php else: ?>
                                    <span class="nbp-status-badge nbp-status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Interval:</th>
                            <td>Every 60 seconds</td>
                        </tr>
                        <tr>
                            <th>Buffer TTL:</th>
                            <td><?php echo intval(get_option('nbp_buffer_ttl', 300)); ?> seconds (<?php echo round(intval(get_option('nbp_buffer_ttl', 300)) / 60, 1); ?> minutes)</td>
                        </tr>
                        <tr>
                            <th>Next Poll:</th>
                            <td>
                                <?php
                                $next_poll = wp_next_scheduled('nbp_poll_newbook');
                                if ($next_poll) {
                                    echo esc_html(human_time_diff($next_poll, current_time('timestamp'))) . ' from now';
                                } else {
                                    echo 'Not scheduled';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>

                    <form method="post" style="margin-top: 20px;">
                        <?php wp_nonce_field('nbp_manual_trigger', 'nbp_nonce'); ?>
                        <button type="submit" name="nbp_trigger_poll" class="button button-primary">
                            Trigger Poll Now
                        </button>
                    </form>
                </div>

                <!-- Buffer Statistics Card -->
                <div class="nbp-card">
                    <h2>Buffer Statistics</h2>
                    <table class="form-table">
                        <tr>
                            <th>Entries:</th>
                            <td><?php echo intval($buffer_stats['total_entries']); ?></td>
                        </tr>
                        <tr>
                            <th>Oldest Entry:</th>
                            <td>
                                <?php
                                if ($buffer_stats['oldest_entry']) {
                                    echo esc_html(human_time_diff(strtotime($buffer_stats['oldest_entry']), current_time('timestamp'))) . ' ago';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Newest Entry:</th>
                            <td>
                                <?php
                                if ($buffer_stats['newest_entry']) {
                                    echo esc_html(human_time_diff(strtotime($buffer_stats['newest_entry']), current_time('timestamp'))) . ' ago';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Location Status Card -->
                <div class="nbp-card">
                    <h2>Location Polling Status</h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Status</th>
                                <th>NewBook Integration</th>
                                <th>Last Check</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hotels as $hotel): ?>
                                <?php
                                $integration = hha()->integrations->get($hotel->id, 'newbook');
                                $last_check = get_option('nbp_last_check_' . $hotel->id, 'Never');
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($hotel->name); ?></strong></td>
                                    <td>
                                        <?php if ($hotel->is_active): ?>
                                            <span class="nbp-status-badge nbp-status-active">Active</span>
                                        <?php else: ?>
                                            <span class="nbp-status-badge nbp-status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($integration && $integration->is_active): ?>
                                            <span class="nbp-status-badge nbp-status-active">Connected</span>
                                        <?php else: ?>
                                            <span class="nbp-status-badge nbp-status-inactive">Not Connected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($last_check !== 'Never') {
                                            echo esc_html(human_time_diff(strtotime($last_check), current_time('timestamp'))) . ' ago';
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Buffer Contents (Debug) -->
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <div class="nbp-card">
                    <h2>Buffer Contents (Debug)</h2>
                    <?php
                    $buffer_contents = nbp()->heartbeat->get_buffer_contents();
                    if (!empty($buffer_contents)):
                    ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Location</th>
                                    <th>Arrival</th>
                                    <th>Departure</th>
                                    <th>Detected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($buffer_contents, 0, 20) as $entry): ?>
                                    <tr>
                                        <td><?php echo esc_html($entry['booking_id']); ?></td>
                                        <td><?php echo intval($entry['location_id']); ?></td>
                                        <td><?php echo esc_html($entry['arrival_date']); ?></td>
                                        <td><?php echo esc_html($entry['departure_date']); ?></td>
                                        <td><?php echo esc_html(human_time_diff(strtotime($entry['detected_at']), current_time('timestamp'))); ?> ago</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($buffer_contents) > 20): ?>
                            <p><em>Showing 20 of <?php echo count($buffer_contents); ?> entries</em></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><em>Buffer is empty</em></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Server Cron Setup Instructions -->
                <div class="nbp-card">
                    <h2>⚙️ Server Cron Configuration (Recommended)</h2>
                    <p><strong>Why use server cron?</strong> WordPress WP-Cron relies on site traffic to trigger. For critical polling like NewBook updates, a real server cron job ensures reliable execution every 60 seconds, even with zero site traffic.</p>

                    <h3 style="margin-top: 20px;">Step 1: Disable WordPress Cron</h3>
                    <p>Add this line to your <code>wp-config.php</code> file (before "That's all, stop editing!"):</p>
                    <pre style="background: #f5f5f5; padding: 12px; border-radius: 4px; overflow-x: auto;">define('DISABLE_WP_CRON', true);</pre>

                    <h3 style="margin-top: 20px;">Step 2: Add Server Cron Job</h3>
                    <p>Add one of the following to your server's crontab. Choose the method that works for your hosting:</p>

                    <h4>Option A: Using wget (Most Compatible)</h4>
                    <pre style="background: #f5f5f5; padding: 12px; border-radius: 4px; overflow-x: auto;">* * * * * wget -q -O - <?php echo esc_url(site_url('wp-cron.php?doing_wp_cron')); ?> >/dev/null 2>&1</pre>

                    <h4>Option B: Using curl</h4>
                    <pre style="background: #f5f5f5; padding: 12px; border-radius: 4px; overflow-x: auto;">* * * * * curl -s <?php echo esc_url(site_url('wp-cron.php?doing_wp_cron')); ?> >/dev/null 2>&1</pre>

                    <h4>Option C: Using WP-CLI (Best Performance)</h4>
                    <pre style="background: #f5f5f5; padding: 12px; border-radius: 4px; overflow-x: auto;">* * * * * cd <?php echo ABSPATH; ?> && wp cron event run --due-now >/dev/null 2>&1</pre>

                    <h3 style="margin-top: 20px;">Step 3: Verify It's Working</h3>
                    <p>After setting up the cron job:</p>
                    <ol>
                        <li>Wait 2-3 minutes</li>
                        <li>Refresh this page</li>
                        <li>Check "Last Check" times for each location above</li>
                        <li>Times should update every ~60 seconds</li>
                        <li>Buffer Statistics should show new entries (if there are booking changes)</li>
                    </ol>

                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 20px;">
                        <strong>⚠️ Note:</strong> If you're on shared hosting and don't have crontab access, contact your hosting provider. Most hosts can set this up for you, or they may have a "Cron Jobs" interface in cPanel/Plesk.
                    </div>

                    <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 12px; margin-top: 20px;">
                        <strong>💡 Tip:</strong> Even without server cron, the plugin will work via WP-Cron triggered by site traffic. Server cron just makes it more reliable and ensures consistent 60-second intervals.
                    </div>
                </div>
            </div>
        </div>

        <style>
            .nbp-admin-container {
                max-width: 1200px;
            }
            .nbp-card {
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .nbp-card h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #e5e7eb;
            }
            .nbp-status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
            }
            .nbp-status-active {
                background: #dcfce7;
                color: #166534;
            }
            .nbp-status-inactive {
                background: #f3f4f6;
                color: #6b7280;
            }
        </style>
        <?php
    }

    /**
     * Handle manual poll trigger
     */
    public function handle_manual_trigger() {
        if (!isset($_POST['nbp_trigger_poll'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['nbp_nonce'], 'nbp_manual_trigger')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Trigger the poll
        nbp()->poller->poll_newbook();

        // Show success notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo 'Poll triggered successfully! Check the buffer statistics below.';
            echo '</p></div>';
        });

        // Refresh the page to show updated stats
        wp_safe_redirect(add_query_arg('poll_triggered', '1', wp_get_referer()));
        exit;
    }
}
