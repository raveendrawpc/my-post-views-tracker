<?php

namespace WPCreative\WordPress\MyPostViewsTracker;

class PostViewsTracker {
    private static $instance = null;
    private $ipLogTable;

    private function __construct() {
        global $wpdb;
        $this->ipLogTable = $wpdb->prefix . 'post_views_ip_log';

        add_action('wp_head', [$this, 'trackPostViews']);
        add_action('admin_menu', [$this, 'registerAdminPage']);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'post_views_ip_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            ip_address varchar(100) NOT NULL,
            view_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY ip_address (ip_address)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function trackPostViews() {
        if (is_single()) {
            global $post, $wpdb;

            $ip_address = $_SERVER['REMOTE_ADDR'];
            $post_id = $post->ID;

            // Use interval in hours
            $recent_view = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $this->ipLogTable WHERE post_id = %d AND ip_address = %s AND view_time > DATE_SUB(NOW(), INTERVAL %d HOUR)",
                $post_id,
                $ip_address,
                24 // Fixed interval of 24 hours
            ));

            if (!$recent_view) {
                $wpdb->insert($this->ipLogTable, [
                    'post_id' => $post_id,
                    'ip_address' => $ip_address,
                    'view_time' => current_time('mysql'),
                ]);
            }
        }
    }

    public function registerAdminPage() {
        add_menu_page(
            __('Post Views Stats', 'my-post-views-tracker'),
            __('Post Views', 'my-post-views-tracker'),
            'manage_options',
            'post-views-stats',
            [$this, 'renderAdminPage'],
            'dashicons-visibility'
        );
    }

    public function renderAdminPage() {
        global $wpdb;

        // Get selected time period from query parameters
        $time_period = isset($_GET['time_period']) ? sanitize_text_field($_GET['time_period']) : '1 day';

        // Map time periods to intervals in hours
        $intervals = [
            '1 day' => 24,
            '7 days' => 168, // 7 days * 24 hours
            '1 month' => 720, // Approx. 30 days * 24 hours
            '1 year' => 8760 // 365 days * 24 hours
        ];
        $hours = isset($intervals[$time_period]) ? $intervals[$time_period] : 24;

        // Fetch post view data
        $posts = get_posts(['numberposts' => -1]);
        $data = [];
        foreach ($posts as $post) {
            $views = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $this->ipLogTable WHERE post_id = %d AND view_time > DATE_SUB(NOW(), INTERVAL %d HOUR)",
                $post->ID,
                $hours
            ));
            $data[] = [
                'title' => $post->post_title,
                'views' => (int) $views
            ];
        }

        // Enqueue Chart.js
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Post Views Stats', 'my-post-views-tracker'); ?></h1>
            <form method="GET" action="">
                <input type="hidden" name="page" value="post-views-stats" />
                <select name="time_period" onchange="this.form.submit()">
                    <option value="1 day" <?php selected($time_period, '1 day'); ?>><?php esc_html_e('Last 1 Day', 'my-post-views-tracker'); ?></option>
                    <option value="7 days" <?php selected($time_period, '7 days'); ?>><?php esc_html_e('Last 7 Days', 'my-post-views-tracker'); ?></option>
                    <option value="1 month" <?php selected($time_period, '1 month'); ?>><?php esc_html_e('Last 1 Month', 'my-post-views-tracker'); ?></option>
                    <option value="1 year" <?php selected($time_period, '1 year'); ?>><?php esc_html_e('Last 1 Year', 'my-post-views-tracker'); ?></option>
                </select>
            </form>
            <canvas id="viewsChart" width="400" height="200"></canvas>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var ctx = document.getElementById('viewsChart').getContext('2d');
                    var chartData = <?php echo json_encode($data); ?>;
                    var labels = chartData.map(item => item.title);
                    var values = chartData.map(item => item.views);

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '<?php esc_html_e('Post Views', 'my-post-views-tracker'); ?>',
                                data: values,
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                });
            </script>
        </div>
<?php
    }
}
