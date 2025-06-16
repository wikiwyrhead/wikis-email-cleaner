<?php
/**
 * Newsletter Plugin integration class
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Newsletter integration class
 */
class Wikis_Email_Cleaner_Newsletter_Integration {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into Newsletter Plugin events
        add_filter( 'newsletter_subscription', array( $this, 'validate_subscription' ), 10, 2 );
        add_action( 'newsletter_user_confirmed', array( $this, 'on_user_confirmed' ) );
        add_action( 'newsletter_user_unsubscribed', array( $this, 'on_user_unsubscribed' ) );
        add_action( 'newsletter_user_bounced', array( $this, 'on_user_bounced' ) );
        add_action( 'newsletter_user_complained', array( $this, 'on_user_complained' ) );
    }

    /**
     * Initialize integration
     */
    public function init() {
        // Check if Newsletter Plugin is available
        if ( ! class_exists( 'Newsletter' ) ) {
            return;
        }

        // Add custom hooks for Newsletter Plugin
        $this->setup_custom_hooks();
    }

    /**
     * Setup custom hooks
     */
    private function setup_custom_hooks() {
        // Add email validation to subscription process
        add_action( 'wp_ajax_newsletter_subscription', array( $this, 'ajax_validate_subscription' ), 5 );
        add_action( 'wp_ajax_nopriv_newsletter_subscription', array( $this, 'ajax_validate_subscription' ), 5 );
    }

    /**
     * Validate subscription email
     *
     * @param TNP_Subscription $subscription Subscription object
     * @param object|null      $user         Existing user object
     * @return TNP_Subscription|null Modified subscription or null to cancel
     */
    public function validate_subscription( $subscription, $user ) {
        // Skip validation if subscription is already null
        if ( ! $subscription ) {
            return $subscription;
        }

        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        // Skip validation if not enabled for subscriptions
        if ( empty( $settings['validate_on_subscription'] ) ) {
            return $subscription;
        }

        $email = $subscription->data['email'];
        $validator = new Wikis_Email_Cleaner_Validator();
        $validation = $validator->validate_email( $email, false ); // Quick validation for subscriptions

        // Log validation attempt
        $logger = new Wikis_Email_Cleaner_Logger();
        $logger->log_validation( 0, array_merge( $validation, array( 'action_taken' => 'subscription_check' ) ) );

        // Check if email should be rejected
        $minimum_score = isset( $settings['subscription_minimum_score'] ) ? intval( $settings['subscription_minimum_score'] ) : 30;
        
        if ( ! $validation['is_valid'] || $validation['score'] < $minimum_score ) {
            // Reject subscription
            return null;
        }

        // Add validation score to subscription data for future reference
        $subscription->data['validation_score'] = $validation['score'];
        $subscription->data['validation_warnings'] = $validation['warnings'];

        return $subscription;
    }

    /**
     * Handle user confirmation
     *
     * @param object $user User object
     */
    public function on_user_confirmed( $user ) {
        // Perform deep validation on confirmed users
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        if ( empty( $settings['deep_validate_on_confirm'] ) ) {
            return;
        }

        $validator = new Wikis_Email_Cleaner_Validator();
        $validation = $validator->validate_email( $user->email, true );

        // Log validation
        $logger = new Wikis_Email_Cleaner_Logger();
        $logger->log_validation( $user->id, array_merge( $validation, array( 'action_taken' => 'confirmation_check' ) ) );

        // Check if user should be unsubscribed after deep validation
        $minimum_score = isset( $settings['minimum_score'] ) ? intval( $settings['minimum_score'] ) : 50;
        
        if ( ! $validation['is_valid'] || $validation['score'] < $minimum_score ) {
            $this->unsubscribe_user( $user->id, 'failed_deep_validation' );
        }
    }

    /**
     * Handle user unsubscription
     *
     * @param object $user User object
     */
    public function on_user_unsubscribed( $user ) {
        // Log unsubscription event
        $logger = new Wikis_Email_Cleaner_Logger();
        $logger->log_validation( $user->id, array(
            'email' => $user->email,
            'is_valid' => false,
            'score' => 0,
            'errors' => array( 'User unsubscribed' ),
            'warnings' => array(),
            'action_taken' => 'user_unsubscribed',
            'checked_at' => current_time( 'mysql' )
        ) );
    }

    /**
     * Handle bounced user
     *
     * @param object $user User object
     */
    public function on_user_bounced( $user ) {
        // Log bounce event
        $logger = new Wikis_Email_Cleaner_Logger();
        $logger->log_validation( $user->id, array(
            'email' => $user->email,
            'is_valid' => false,
            'score' => 0,
            'errors' => array( 'Email bounced' ),
            'warnings' => array(),
            'action_taken' => 'bounced',
            'checked_at' => current_time( 'mysql' )
        ) );

        // Optionally perform additional validation
        $this->handle_problematic_email( $user, 'bounced' );
    }

    /**
     * Handle complained user
     *
     * @param object $user User object
     */
    public function on_user_complained( $user ) {
        // Log complaint event
        $logger = new Wikis_Email_Cleaner_Logger();
        $logger->log_validation( $user->id, array(
            'email' => $user->email,
            'is_valid' => false,
            'score' => 0,
            'errors' => array( 'Spam complaint' ),
            'warnings' => array(),
            'action_taken' => 'complained',
            'checked_at' => current_time( 'mysql' )
        ) );

        // Handle complaint
        $this->handle_problematic_email( $user, 'complained' );
    }

    /**
     * AJAX validate subscription
     */
    public function ajax_validate_subscription() {
        // Only validate if our plugin settings require it
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        if ( empty( $settings['validate_on_subscription'] ) ) {
            return;
        }

        // Get email from request
        $email = isset( $_POST['ne'] ) ? sanitize_email( $_POST['ne'] ) : '';
        
        if ( empty( $email ) ) {
            return;
        }

        // Validate email
        $validator = new Wikis_Email_Cleaner_Validator();
        $validation = $validator->validate_email( $email, false );

        $minimum_score = isset( $settings['subscription_minimum_score'] ) ? intval( $settings['subscription_minimum_score'] ) : 30;

        // If email fails validation, stop the subscription process
        if ( ! $validation['is_valid'] || $validation['score'] < $minimum_score ) {
            wp_die( 
                esc_html__( 'The email address provided appears to be invalid. Please check and try again.', 'wikis-email-cleaner' ),
                esc_html__( 'Invalid Email', 'wikis-email-cleaner' ),
                array( 'response' => 400 )
            );
        }
    }

    /**
     * Unsubscribe user
     *
     * @param int    $user_id User ID
     * @param string $reason  Reason for unsubscription
     */
    private function unsubscribe_user( $user_id, $reason ) {
        global $wpdb;

        $newsletter_table = $wpdb->prefix . 'newsletter';

        // Update user status
        $wpdb->update(
            $newsletter_table,
            array( 'status' => 'U' ),
            array( 'id' => $user_id ),
            array( '%s' ),
            array( '%d' )
        );

        // Trigger Newsletter Plugin action if available
        if ( class_exists( 'Newsletter' ) ) {
            $newsletter = Newsletter::instance();
            $user = $newsletter->get_user( $user_id );
            if ( $user ) {
                do_action( 'newsletter_user_unsubscribed', $user );
            }
        }

        /**
         * Action fired when a user is automatically unsubscribed by email cleaner
         *
         * @since 1.0.0
         * @param int    $user_id User ID
         * @param string $reason  Reason for unsubscription
         */
        do_action( 'wikis_email_cleaner_auto_unsubscribed', $user_id, $reason );
    }

    /**
     * Handle problematic email (bounced/complained)
     *
     * @param object $user User object
     * @param string $type Problem type
     */
    private function handle_problematic_email( $user, $type ) {
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        // Check if we should take action on bounces/complaints
        if ( empty( $settings['handle_bounces_complaints'] ) ) {
            return;
        }

        // Perform validation to get more details
        $validator = new Wikis_Email_Cleaner_Validator();
        $validation = $validator->validate_email( $user->email, true );

        // Update validation log with additional context
        $logger = new Wikis_Email_Cleaner_Logger();
        $validation['action_taken'] = $type . '_handled';
        $validation['errors'][] = ucfirst( $type ) . ' email detected';
        
        $logger->log_validation( $user->id, $validation );

        /**
         * Action fired when a problematic email is detected
         *
         * @since 1.0.0
         * @param object $user       User object
         * @param string $type       Problem type (bounced/complained)
         * @param array  $validation Validation result
         */
        do_action( 'wikis_email_cleaner_problematic_email', $user, $type, $validation );
    }

    /**
     * Get Newsletter Plugin subscribers for validation
     *
     * @param array $args Query arguments
     * @return array Subscribers with enhanced data
     */
    public function get_subscribers_for_validation( $args = array() ) {
        global $wpdb;

        $newsletter_table = $wpdb->prefix . 'newsletter';

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$newsletter_table}'" ) !== $newsletter_table ) {
            return array();
        }

        $defaults = array(
            'status' => 'C', // Confirmed subscribers only
            'limit' => 100,
            'offset' => 0,
            'include_stats' => false,
            'exclude_recently_validated' => false
        );

        $args = wp_parse_args( $args, $defaults );

        $where_clauses = array();
        $where_values = array();

        if ( ! empty( $args['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        // Exclude recently validated emails if requested
        if ( $args['exclude_recently_validated'] ) {
            $logs_table = $wpdb->prefix . 'wikis_email_cleaner_logs';
            $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

            $where_clauses[] = "email NOT IN (
                SELECT email FROM {$logs_table}
                WHERE created_at > %s
            )";
            $where_values[] = $cutoff_date;
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $limit_sql = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );

        // Enhanced query with additional subscriber data
        $select_fields = 'id, email, status, name, surname, created, updated';
        if ( $args['include_stats'] ) {
            $select_fields .= ', token, bounced, complained';
        }

        $query = "SELECT {$select_fields} FROM {$newsletter_table} {$where_sql} ORDER BY id ASC {$limit_sql}";

        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $query, $where_values );
        }

        $subscribers = $wpdb->get_results( $query, ARRAY_A );

        // Add validation history if available
        if ( ! empty( $subscribers ) ) {
            $subscribers = $this->add_validation_history( $subscribers );
        }

        return $subscribers;
    }

    /**
     * Add validation history to subscribers
     *
     * @param array $subscribers Array of subscribers
     * @return array Subscribers with validation history
     */
    private function add_validation_history( $subscribers ) {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'wikis_email_cleaner_logs';

        // Check if logs table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$logs_table}'" ) !== $logs_table ) {
            return $subscribers;
        }

        $emails = array_column( $subscribers, 'email' );
        $email_placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );

        $validation_history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT email, is_valid, score, created_at, action_taken
                 FROM {$logs_table}
                 WHERE email IN ({$email_placeholders})
                 ORDER BY created_at DESC",
                ...$emails
            ),
            ARRAY_A
        );

        // Group by email
        $history_by_email = array();
        foreach ( $validation_history as $record ) {
            $history_by_email[ $record['email'] ][] = $record;
        }

        // Add history to subscribers
        foreach ( $subscribers as &$subscriber ) {
            $subscriber['validation_history'] = $history_by_email[ $subscriber['email'] ] ?? array();
            $subscriber['last_validation'] = ! empty( $subscriber['validation_history'] )
                ? $subscriber['validation_history'][0]
                : null;
        }

        return $subscribers;
    }

    /**
     * Get subscriber count
     *
     * @param string $status Subscriber status
     * @return int Subscriber count
     */
    public function get_subscriber_count( $status = 'C' ) {
        global $wpdb;

        $newsletter_table = $wpdb->prefix . 'newsletter';

        // Check if Newsletter plugin table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$newsletter_table}'" ) !== $newsletter_table ) {
            return 0;
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$newsletter_table} WHERE status = %s",
                $status
            )
        );

        return $count !== null ? (int) $count : 0;
    }
}
