<?php
/**
 * Scheduler class for automated email cleaning
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scheduler class
 */
class Wikis_Email_Cleaner_Scheduler {

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'wikis_email_cleaner_scheduled_scan';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_scan' ) );
        add_action( 'wikis_email_cleaner_cleanup', array( $this, 'run_cleanup' ) );
        add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );
    }

    /**
     * Initialize scheduler
     */
    public function init() {
        $this->schedule_next_scan();
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_custom_schedules( $schedules ) {
        $schedules['wikis_email_cleaner_hourly'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display'  => __( 'Every Hour (Email Cleaner)', 'wikis-email-cleaner' )
        );

        $schedules['wikis_email_cleaner_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display'  => __( 'Daily (Email Cleaner)', 'wikis-email-cleaner' )
        );

        $schedules['wikis_email_cleaner_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Weekly (Email Cleaner)', 'wikis-email-cleaner' )
        );

        return $schedules;
    }

    /**
     * Schedule next scan
     */
    public function schedule_next_scan() {
        // Clear existing schedule
        $this->clear_scheduled_scan();

        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        // Check if auto cleaning is enabled
        if ( empty( $settings['enable_auto_clean'] ) ) {
            return;
        }

        $frequency = isset( $settings['schedule_frequency'] ) ? $settings['schedule_frequency'] : 'daily';
        $schedule_name = 'wikis_email_cleaner_' . $frequency;

        // Schedule the event
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), $schedule_name, self::CRON_HOOK );
        }
    }

    /**
     * Clear scheduled scan
     */
    public function clear_scheduled_scan() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Run scheduled email scan
     */
    public function run_scheduled_scan() {
        // Prevent multiple simultaneous scans
        if ( get_transient( 'wikis_email_cleaner_scanning' ) ) {
            return;
        }

        // Set scanning flag
        set_transient( 'wikis_email_cleaner_scanning', true, HOUR_IN_SECONDS );

        try {
            $this->perform_scan();
        } catch ( Exception $e ) {
            error_log( 'Wikis Email Cleaner scheduled scan error: ' . $e->getMessage() );
        } finally {
            // Clear scanning flag
            delete_transient( 'wikis_email_cleaner_scanning' );
        }
    }

    /**
     * Perform email scan
     */
    private function perform_scan() {
        global $wpdb;

        // Get Newsletter subscribers
        $newsletter_table = $wpdb->prefix . 'newsletter';
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$newsletter_table}'" ) !== $newsletter_table ) {
            return;
        }

        $batch_size = 100; // Process in batches to avoid memory issues
        $offset = 0;
        $total_processed = 0;
        $total_invalid = 0;
        $total_unsubscribed = 0;

        $validator = new Wikis_Email_Cleaner_Validator();
        $logger = new Wikis_Email_Cleaner_Logger();
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        $minimum_score = isset( $settings['minimum_score'] ) ? intval( $settings['minimum_score'] ) : 50;
        $deep_validation = ! empty( $settings['enable_deep_validation'] );

        do {
            $subscribers = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, email, status FROM {$newsletter_table} WHERE status = 'C' LIMIT %d OFFSET %d",
                    $batch_size,
                    $offset
                ),
                ARRAY_A
            );

            if ( empty( $subscribers ) ) {
                break;
            }

            foreach ( $subscribers as $subscriber ) {
                $validation = $validator->validate_email( $subscriber['email'], $deep_validation );
                $total_processed++;

                // Log validation result
                $logger->log_validation( $subscriber['id'], $validation );

                // Check if email should be unsubscribed
                if ( ! $validation['is_valid'] || $validation['score'] < $minimum_score ) {
                    $this->unsubscribe_invalid_email( $subscriber['id'], $subscriber['email'], $validation );
                    $total_invalid++;
                    $total_unsubscribed++;
                }

                // Prevent timeout
                if ( $total_processed % 50 === 0 ) {
                    sleep( 1 );
                }
            }

            $offset += $batch_size;

        } while ( count( $subscribers ) === $batch_size );

        // Log scan summary
        $logger->log_scan_summary( array(
            'total_processed' => $total_processed,
            'total_invalid' => $total_invalid,
            'total_unsubscribed' => $total_unsubscribed,
            'scan_date' => current_time( 'mysql' )
        ) );

        // Send email notification if configured
        $this->send_scan_notification( $total_processed, $total_invalid, $total_unsubscribed );

        // Clean up old logs
        $logger->cleanup_old_logs();
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

        // Trigger Newsletter Plugin action
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
     * Send scan notification email
     *
     * @param int $total_processed    Total emails processed
     * @param int $total_invalid      Total invalid emails found
     * @param int $total_unsubscribed Total emails unsubscribed
     */
    private function send_scan_notification( $total_processed, $total_invalid, $total_unsubscribed ) {
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        if ( empty( $settings['send_notifications'] ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            __( '[%s] Email Cleaning Report', 'wikis-email-cleaner' ),
            $site_name
        );

        $message = sprintf(
            __( "Email cleaning scan completed.\n\nSummary:\n- Total emails processed: %d\n- Invalid emails found: %d\n- Emails unsubscribed: %d\n\nScan completed at: %s", 'wikis-email-cleaner' ),
            $total_processed,
            $total_invalid,
            $total_unsubscribed,
            current_time( 'Y-m-d H:i:s' )
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Get next scheduled scan time
     *
     * @return int|false Next scheduled time or false if not scheduled
     */
    public function get_next_scheduled_time() {
        return wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Check if scan is currently running
     *
     * @return bool True if scanning
     */
    public function is_scanning() {
        return (bool) get_transient( 'wikis_email_cleaner_scanning' );
    }

    /**
     * Manually trigger a scan
     *
     * @return bool True if scan started successfully
     */
    public function trigger_manual_scan() {
        if ( $this->is_scanning() ) {
            return false;
        }

        // Schedule immediate scan
        wp_schedule_single_event( time(), self::CRON_HOOK );
        return true;
    }

    /**
     * Run cleanup job
     */
    public function run_cleanup() {
        $logger = new Wikis_Email_Cleaner_Logger();
        $logger->cleanup_old_logs();
    }
}
