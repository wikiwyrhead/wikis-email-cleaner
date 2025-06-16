<?php
/**
 * Admin functionality class
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class
 */
class Wikis_Email_Cleaner_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'init_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_wikis_email_cleaner_scan', array( $this, 'ajax_scan_emails' ) );
        add_action( 'wp_ajax_wikis_email_cleaner_export', array( $this, 'ajax_export_results' ) );
        add_action( 'wp_ajax_wikis_email_cleaner_dismiss_donation', array( $this, 'ajax_dismiss_donation' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Email Cleaner', 'wikis-email-cleaner' ),
            __( 'Email Cleaner', 'wikis-email-cleaner' ),
            'manage_options',
            'wikis-email-cleaner',
            array( $this, 'admin_page' ),
            'dashicons-email-alt',
            30
        );

        add_submenu_page(
            'wikis-email-cleaner',
            __( 'Settings', 'wikis-email-cleaner' ),
            __( 'Settings', 'wikis-email-cleaner' ),
            'manage_options',
            'wikis-email-cleaner-settings',
            array( $this, 'settings_page' )
        );

        add_submenu_page(
            'wikis-email-cleaner',
            __( 'Logs', 'wikis-email-cleaner' ),
            __( 'Logs', 'wikis-email-cleaner' ),
            'manage_options',
            'wikis-email-cleaner-logs',
            array( $this, 'logs_page' )
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting( 'wikis_email_cleaner_settings', 'wikis_email_cleaner_settings' );

        // General settings section
        add_settings_section(
            'wikis_email_cleaner_general',
            __( 'General Settings', 'wikis-email-cleaner' ),
            array( $this, 'general_section_callback' ),
            'wikis_email_cleaner_settings'
        );

        // Validation settings section
        add_settings_section(
            'wikis_email_cleaner_validation',
            __( 'Validation Settings', 'wikis-email-cleaner' ),
            array( $this, 'validation_section_callback' ),
            'wikis_email_cleaner_settings'
        );

        // Schedule settings section
        add_settings_section(
            'wikis_email_cleaner_schedule',
            __( 'Schedule Settings', 'wikis-email-cleaner' ),
            array( $this, 'schedule_section_callback' ),
            'wikis_email_cleaner_settings'
        );

        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // Enable automatic cleaning
        add_settings_field(
            'enable_auto_clean',
            __( 'Enable Automatic Cleaning', 'wikis-email-cleaner' ),
            array( $this, 'checkbox_field_callback' ),
            'wikis_email_cleaner_settings',
            'wikis_email_cleaner_general',
            array( 'field' => 'enable_auto_clean' )
        );

        // Deep validation
        add_settings_field(
            'enable_deep_validation',
            __( 'Enable Deep Validation', 'wikis-email-cleaner' ),
            array( $this, 'checkbox_field_callback' ),
            'wikis_email_cleaner_settings',
            'wikis_email_cleaner_validation',
            array( 'field' => 'enable_deep_validation' )
        );

        // Minimum score
        add_settings_field(
            'minimum_score',
            __( 'Minimum Valid Score', 'wikis-email-cleaner' ),
            array( $this, 'number_field_callback' ),
            'wikis_email_cleaner_settings',
            'wikis_email_cleaner_validation',
            array( 'field' => 'minimum_score', 'min' => 0, 'max' => 100 )
        );

        // Schedule frequency
        add_settings_field(
            'schedule_frequency',
            __( 'Scan Frequency', 'wikis-email-cleaner' ),
            array( $this, 'select_field_callback' ),
            'wikis_email_cleaner_settings',
            'wikis_email_cleaner_schedule',
            array(
                'field' => 'schedule_frequency',
                'options' => array(
                    'hourly' => __( 'Hourly', 'wikis-email-cleaner' ),
                    'daily' => __( 'Daily', 'wikis-email-cleaner' ),
                    'weekly' => __( 'Weekly', 'wikis-email-cleaner' ),
                )
            )
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'wikis-email-cleaner' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'wikis-email-cleaner-admin',
            WIKIS_EMAIL_CLEANER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            WIKIS_EMAIL_CLEANER_VERSION
        );

        wp_enqueue_script(
            'wikis-email-cleaner-admin',
            WIKIS_EMAIL_CLEANER_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            WIKIS_EMAIL_CLEANER_VERSION,
            true
        );

        wp_localize_script(
            'wikis-email-cleaner-admin',
            'wikisEmailCleaner',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wikis_email_cleaner_nonce' ),
                'strings' => array(
                    'scanning' => __( 'Scanning emails...', 'wikis-email-cleaner' ),
                    'complete' => __( 'Scan complete!', 'wikis-email-cleaner' ),
                    'error' => __( 'An error occurred.', 'wikis-email-cleaner' ),
                )
            )
        );
    }

    /**
     * Main admin page
     */
    public function admin_page() {
        include WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'admin/views/admin-page.php';
    }

    /**
     * Settings page
     */
    public function settings_page() {
        include WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Logs page
     */
    public function logs_page() {
        include WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'admin/views/logs-page.php';
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__( 'Configure general email cleaning settings.', 'wikis-email-cleaner' ) . '</p>';
    }

    /**
     * Validation section callback
     */
    public function validation_section_callback() {
        echo '<p>' . esc_html__( 'Configure email validation parameters.', 'wikis-email-cleaner' ) . '</p>';
    }

    /**
     * Schedule section callback
     */
    public function schedule_section_callback() {
        echo '<p>' . esc_html__( 'Configure automatic scanning schedule.', 'wikis-email-cleaner' ) . '</p>';
    }

    /**
     * Checkbox field callback
     *
     * @param array $args Field arguments
     */
    public function checkbox_field_callback( $args ) {
        $options = get_option( 'wikis_email_cleaner_settings', array() );
        $value = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : false;
        
        printf(
            '<input type="checkbox" id="%s" name="wikis_email_cleaner_settings[%s]" value="1" %s />',
            esc_attr( $args['field'] ),
            esc_attr( $args['field'] ),
            checked( 1, $value, false )
        );
    }

    /**
     * Number field callback
     *
     * @param array $args Field arguments
     */
    public function number_field_callback( $args ) {
        $options = get_option( 'wikis_email_cleaner_settings', array() );
        $value = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : 50;
        
        printf(
            '<input type="number" id="%s" name="wikis_email_cleaner_settings[%s]" value="%s" min="%d" max="%d" />',
            esc_attr( $args['field'] ),
            esc_attr( $args['field'] ),
            esc_attr( $value ),
            isset( $args['min'] ) ? intval( $args['min'] ) : 0,
            isset( $args['max'] ) ? intval( $args['max'] ) : 100
        );
    }

    /**
     * Select field callback
     *
     * @param array $args Field arguments
     */
    public function select_field_callback( $args ) {
        $options = get_option( 'wikis_email_cleaner_settings', array() );
        $value = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : '';
        
        printf( '<select id="%s" name="wikis_email_cleaner_settings[%s]">', esc_attr( $args['field'] ), esc_attr( $args['field'] ) );
        
        foreach ( $args['options'] as $option_value => $option_label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $option_value ),
                selected( $value, $option_value, false ),
                esc_html( $option_label )
            );
        }
        
        echo '</select>';
    }

    /**
     * AJAX scan emails with enhanced validation
     */
    public function ajax_scan_emails() {
        check_ajax_referer( 'wikis_email_cleaner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'wikis-email-cleaner' ) );
        }

        // Get settings
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        $deep_validation = ! empty( $settings['enable_deep_validation'] );
        $minimum_score = isset( $settings['minimum_score'] ) ? intval( $settings['minimum_score'] ) : 60;

        // Get Newsletter subscribers
        $newsletter_integration = new Wikis_Email_Cleaner_Newsletter_Integration();
        $subscribers = $newsletter_integration->get_subscribers_for_validation( array(
            'limit' => 500, // Process in manageable batches
            'exclude_recently_validated' => true
        ) );

        if ( empty( $subscribers ) ) {
            wp_send_json_success( array(
                'total_scanned' => 0,
                'invalid_count' => 0,
                'results' => array(),
                'message' => __( 'No subscribers found to validate.', 'wikis-email-cleaner' )
            ) );
        }

        $validator = new Wikis_Email_Cleaner_Validator();
        $logger = new Wikis_Email_Cleaner_Logger();

        $results = array();
        $invalid_count = 0;
        $questionable_count = 0;
        $unsubscribed_count = 0;
        $categories = array(
            'syntax_errors' => 0,
            'non_existent_domains' => 0,
            'disposable_emails' => 0,
            'role_based_emails' => 0,
            'fake_patterns' => 0
        );

        foreach ( $subscribers as $subscriber ) {
            $validation = $validator->validate_email( $subscriber['email'], $deep_validation );

            // Log validation result
            $logger->log_validation( $subscriber['id'], $validation );

            // Categorize the result
            if ( ! $validation['is_valid'] ) {
                $invalid_count++;
                $results[] = array_merge( $validation, array( 'subscriber_id' => $subscriber['id'] ) );

                // Categorize the type of invalidity
                if ( ! empty( $validation['errors'] ) ) {
                    foreach ( $validation['errors'] as $error ) {
                        if ( strpos( $error, 'format' ) !== false || strpos( $error, 'syntax' ) !== false ) {
                            $categories['syntax_errors']++;
                        } elseif ( strpos( $error, 'domain' ) !== false || strpos( $error, 'MX' ) !== false ) {
                            $categories['non_existent_domains']++;
                        } elseif ( strpos( $error, 'fake' ) !== false || strpos( $error, 'pattern' ) !== false ) {
                            $categories['fake_patterns']++;
                        }
                    }
                }

                if ( ! empty( $validation['warnings'] ) ) {
                    foreach ( $validation['warnings'] as $warning ) {
                        if ( strpos( $warning, 'disposable' ) !== false ) {
                            $categories['disposable_emails']++;
                        } elseif ( strpos( $warning, 'role-based' ) !== false ) {
                            $categories['role_based_emails']++;
                        }
                    }
                }

                // Auto-unsubscribe if enabled and score is very low
                if ( ! empty( $settings['enable_auto_clean'] ) && $validation['score'] < $minimum_score ) {
                    $this->unsubscribe_invalid_email( $subscriber['id'], $subscriber['email'], $validation );
                    $unsubscribed_count++;
                }

            } elseif ( $validation['score'] < 70 ) {
                $questionable_count++;
            }
        }

        wp_send_json_success( array(
            'total_scanned' => count( $subscribers ),
            'invalid_count' => $invalid_count,
            'questionable_count' => $questionable_count,
            'unsubscribed_count' => $unsubscribed_count,
            'categories' => $categories,
            'results' => $results,
            'settings_used' => array(
                'deep_validation' => $deep_validation,
                'minimum_score' => $minimum_score,
                'auto_clean_enabled' => ! empty( $settings['enable_auto_clean'] )
            )
        ) );
    }

    /**
     * Unsubscribe invalid email
     *
     * @param int    $subscriber_id Subscriber ID
     * @param string $email         Email address
     * @param array  $validation    Validation result
     */
    private function unsubscribe_invalid_email( $subscriber_id, $email, $validation ) {
        global $wpdb;

        $newsletter_table = $wpdb->prefix . 'newsletter';

        // Update subscriber status to unsubscribed
        $wpdb->update(
            $newsletter_table,
            array( 'status' => 'U' ),
            array( 'id' => $subscriber_id ),
            array( '%s' ),
            array( '%d' )
        );

        // Trigger Newsletter Plugin action if available
        if ( class_exists( 'Newsletter' ) ) {
            $newsletter = Newsletter::instance();
            $user = $newsletter->get_user( $subscriber_id );
            if ( $user ) {
                do_action( 'newsletter_user_unsubscribed', $user );
            }
        }

        /**
         * Action fired when an email is automatically unsubscribed
         *
         * @since 1.0.0
         * @param int    $subscriber_id Subscriber ID
         * @param string $email         Email address
         * @param array  $validation    Validation result
         */
        do_action( 'wikis_email_cleaner_auto_unsubscribed', $subscriber_id, $email, $validation );
    }

    /**
     * AJAX export results
     */
    public function ajax_export_results() {
        check_ajax_referer( 'wikis_email_cleaner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'wikis-email-cleaner' ) );
        }

        // Generate CSV export
        $logger = new Wikis_Email_Cleaner_Logger();
        $export_url = $logger->export_to_csv();

        wp_send_json_success( array( 'export_url' => $export_url ) );
    }

    /**
     * AJAX dismiss donation section
     */
    public function ajax_dismiss_donation() {
        check_ajax_referer( 'wikis_email_cleaner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'wikis-email-cleaner' ) );
        }

        // Save user preference to hide donation
        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'wikis_email_cleaner_hide_donation', true );

        wp_send_json_success( array( 'message' => 'Donation section dismissed successfully' ) );
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if Newsletter plugin is active
        if ( ! class_exists( 'Newsletter' ) ) {
            ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e( 'Wikis Email Cleaner requires The Newsletter Plugin to be installed and activated.', 'wikis-email-cleaner' ); ?></p>
            </div>
            <?php
        }
    }
}
