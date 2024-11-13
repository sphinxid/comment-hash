<?php
/**
 * Plugin Name: Comment Hash
 * Plugin URI: https://github.com/sphinxid/comment-hash
 * Description: Protect your WordPress comments from spam by requiring proof-of-work. This innovative solution makes commenters' devices solve a small computational puzzle before posting, effectively preventing automated spam while maintaining a smooth user experience. No captchas, no annoying verifications - just intelligent spam prevention.
 * Version: 1.0.1
 * Author: sphinxid
 * Author URI: https://github.com/sphinxid/comment-hash
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: comment-hash
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug configuration
define('COMMENT_HASH_DEBUG', false);  // Set to true to enable debug logging

class CommentHash {
    private $plugin_path;
    private $plugin_url;
    private const DIFFICULTY = 5;
    private const NONCE_RANGE = 10000000000;

    /**
     * Debug logging function
     */
    private function debug_log($message, $data = null) {
        if (COMMENT_HASH_DEBUG) {
            if ($data !== null) {
                error_log('Comment Hash Debug: ' . $message . ' ' . print_r($data, true));
            } else {
                error_log('Comment Hash Debug: ' . $message);
            }
        }
    }

    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Initialize the plugin
        add_action('init', array($this, 'init'));

        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        // Add action links to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));

        // Add AJAX handlers
        add_action('wp_ajax_get_comment_challenge', array($this, 'get_comment_challenge'));
        add_action('wp_ajax_nopriv_get_comment_challenge', array($this, 'get_comment_challenge'));

        // Filter comments
        add_filter('preprocess_comment', array($this, 'verify_comment_pow'), 10, 1);
    }

    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=comment-hash-settings') . '">' . __('Settings', 'comment-hash') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function init() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function activate() {
        // Generate and save default settings if they don't exist
        if (!get_option('comment_hash_secret_key')) {
            $this->generate_secret_key();
        }

        if (!get_option('comment_hash_time_diff')) {
            update_option('comment_hash_time_diff', 7200);
        }
    }

    private function generate_secret_key() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        $key = '';
        for ($i = 0; $i < 64; $i++) {
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }
        update_option('comment_hash_secret_key', $key);
    }

    public function enqueue_scripts() {
        if (is_singular() && comments_open()) {
            wp_enqueue_script(
                'comment-hash-worker',
                $this->plugin_url . 'js/worker.js',
                array(),
                '1.0.1',
                true
            );

            wp_enqueue_script(
                'comment-hash-main',
                $this->plugin_url . 'js/main.js',
                array('jquery'),
                '1.0.1',
                true
            );

            wp_enqueue_style(
                'comment-hash-style',
                $this->plugin_url . 'css/style.css',
                array(),
                '1.0.1'
            );

            wp_localize_script('comment-hash-main', 'commentHashSettings', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'difficulty' => self::DIFFICULTY,
                'nonceRange' => self::NONCE_RANGE,
                'workerUrl' => $this->plugin_url . 'js/worker.js'
            ));
        }
    }

    public function get_comment_challenge() {
        $challenge = bin2hex(random_bytes(32));
        $unique_str = bin2hex(random_bytes(16));
        $timestamp = gmdate('Y-m-d H:i:s');

        $data = $challenge . $unique_str . $timestamp;
        $secret_key = get_option('comment_hash_secret_key');
        $digest = hash_hmac('sha256', $data, $secret_key);

        $this->debug_log('Generated challenge data:', array(
            'challenge' => $challenge,
            'uniqueStr' => $unique_str,
            'timestamp' => $timestamp,
            'digest' => $digest
        ));

        wp_send_json_success(array(
            'challenge' => $challenge,
            'uniqueStr' => $unique_str,
            'timestamp' => $timestamp,
            'digest' => $digest
        ));
    }

    public function verify_comment_pow($commentdata) {
        // Skip verification for admin users
        if (is_admin() || current_user_can('manage_options')) {
            $this->debug_log('Skipping verification for admin user');
            return $commentdata;
        }

        $this->debug_log('Verifying comment submission POST data:', $_POST);

        $nonce = isset($_POST['comment_pow_nonce']) ? sanitize_text_field($_POST['comment_pow_nonce']) : '';
        $challenge = isset($_POST['comment_pow_challenge']) ? sanitize_text_field($_POST['comment_pow_challenge']) : '';
        $timestamp = isset($_POST['comment_pow_timestamp']) ? sanitize_text_field($_POST['comment_pow_timestamp']) : '';
        $digest = isset($_POST['comment_pow_digest']) ? sanitize_text_field($_POST['comment_pow_digest']) : '';

        if (empty($nonce) || empty($challenge) || empty($timestamp) || empty($digest)) {
            $this->debug_log('Missing required POW fields');
            wp_die('Comment validation failed. Please try again.');
        }

        // Verify timestamp
        $timestamp_dt = new DateTime($timestamp, new DateTimeZone('UTC'));
        $current_dt = new DateTime('now', new DateTimeZone('UTC'));
        $time_diff = get_option('comment_hash_time_diff', 7200);

        $actual_time_diff = $current_dt->getTimestamp() - $timestamp_dt->getTimestamp();
        $this->debug_log('Time difference check:', array(
            'current_time' => $current_dt->format('Y-m-d H:i:s'),
            'submission_time' => $timestamp_dt->format('Y-m-d H:i:s'),
            'difference' => $actual_time_diff,
            'max_allowed' => $time_diff
        ));

        if ($actual_time_diff > $time_diff) {
            $this->debug_log('Comment submission expired');
            wp_die('Comment validation expired. Please try again.');
        }

        // Verify digest
        $secret_key = get_option('comment_hash_secret_key');
        $unique_str = isset($_POST['comment_pow_unique_str']) ? sanitize_text_field($_POST['comment_pow_unique_str']) : '';

        // Calculate digest with the same data used in get_comment_challenge
        $data = $challenge . $unique_str . $timestamp;
        $calculated_digest = hash_hmac('sha256', $data, $secret_key);

        $this->debug_log('Digest verification:', array(
            'received_digest' => $digest,
            'calculated_digest' => $calculated_digest,
            'challenge' => $challenge,
            'unique_str' => $unique_str,
            'timestamp' => $timestamp
        ));

        if ($calculated_digest !== $digest) {
            $this->debug_log('Digest verification failed');
            wp_die('Invalid comment validation. Please try again.');
        }

        // Verify proof of work
        $pow_data = $challenge . $unique_str . $timestamp . $nonce;
        $hash_result = hash('sha256', $pow_data);
        $target = str_repeat('0', self::DIFFICULTY);

        $this->debug_log('POW verification:', array(
            'hash_result' => $hash_result,
            'target' => $target,
            'nonce' => $nonce
        ));

        if (!str_starts_with($hash_result, $target)) {
            $this->debug_log('POW verification failed');
            wp_die('Invalid proof of work. Please try again.');
        }

        $this->debug_log('Comment verification successful');
        return $commentdata;
    }

    public function add_settings_page() {
        add_options_page(
            'Comment Hash Settings',
            'Comment Hash',
            'manage_options',
            'comment-hash-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('comment_hash_options', 'comment_hash_secret_key');
        register_setting('comment_hash_options', 'comment_hash_time_diff', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_time_diff')
        ));
    }

    public function sanitize_time_diff($value) {
        return max(300, min(86400, intval($value)));
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Comment Hash Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('comment_hash_options');
                do_settings_sections('comment_hash_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Secret Key</th>
                        <td>
                            <input type="text" name="comment_hash_secret_key" value="<?php echo esc_attr(get_option('comment_hash_secret_key')); ?>" class="regular-text" />
                            <p class="description">64-character secret key used for comment validation. Change with caution.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Time Difference (seconds)</th>
                        <td>
                            <input type="number" name="comment_hash_time_diff" value="<?php echo esc_attr(get_option('comment_hash_time_diff', 7200)); ?>" min="300" max="86400" class="regular-text" />
                            <p class="description">Maximum time allowed between challenge generation and comment submission (300-86400 seconds).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Debug Mode</th>
                        <td>
                            <p class="description">Debug mode is currently <?php echo COMMENT_HASH_DEBUG ? 'enabled' : 'disabled'; ?>. To change this, modify the COMMENT_HASH_DEBUG constant in the plugin file.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
new CommentHash();
