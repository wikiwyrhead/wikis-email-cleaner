<?php
/**
 * Plugin deactivation class
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin deactivator class
 */
class Wikis_Email_Cleaner_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
        
        // Clear any transients
        self::clear_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log( 'Wikis Email Cleaner plugin deactivated' );
        
        /**
         * Action fired when plugin is deactivated
         *
         * @since 1.0.0
         */
        do_action( 'wikis_email_cleaner_deactivated' );
    }

    /**
     * Clear all scheduled cron jobs
     */
    private static function clear_cron_jobs() {
        // Clear main scanning job
        $timestamp = wp_next_scheduled( 'wikis_email_cleaner_scheduled_scan' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wikis_email_cleaner_scheduled_scan' );
        }

        // Clear cleanup job
        $cleanup_timestamp = wp_next_scheduled( 'wikis_email_cleaner_cleanup' );
        if ( $cleanup_timestamp ) {
            wp_unschedule_event( $cleanup_timestamp, 'wikis_email_cleaner_cleanup' );
        }

        // Clear all instances of our cron jobs
        wp_clear_scheduled_hook( 'wikis_email_cleaner_scheduled_scan' );
        wp_clear_scheduled_hook( 'wikis_email_cleaner_cleanup' );
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        // Clear scanning flag
        delete_transient( 'wikis_email_cleaner_scanning' );
        
        // Clear any cached validation results
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wikis_email_cleaner_%' 
             OR option_name LIKE '_transient_timeout_wikis_email_cleaner_%'"
        );
    }

    /**
     * Cleanup temporary files
     */
    private static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/wikis-email-cleaner';

        if ( file_exists( $plugin_upload_dir ) ) {
            // Remove old CSV exports (older than 1 day)
            $files = glob( $plugin_upload_dir . '/email-cleaner-logs-*.csv' );
            $cutoff_time = time() - DAY_IN_SECONDS;

            foreach ( $files as $file ) {
                if ( filemtime( $file ) < $cutoff_time ) {
                    unlink( $file );
                }
            }
        }
    }

    /**
     * Send deactivation notification
     */
    private static function send_deactivation_notification() {
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        if ( empty( $settings['send_notifications'] ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            __( '[%s] Email Cleaner Plugin Deactivated', 'wikis-email-cleaner' ),
            $site_name
        );

        $message = sprintf(
            __( "The Wikis Email Cleaner plugin has been deactivated on %s.\n\nDeactivated at: %s\n\nAll scheduled email cleaning has been stopped.", 'wikis-email-cleaner' ),
            $site_name,
            current_time( 'Y-m-d H:i:s' )
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Create deactivation feedback option
     */
    public static function create_feedback_option() {
        // Store deactivation timestamp for potential feedback
        update_option( 'wikis_email_cleaner_deactivated_at', current_time( 'timestamp' ) );
    }

    /**
     * Check if safe to deactivate
     *
     * @return bool|WP_Error True if safe, WP_Error otherwise
     */
    public static function can_deactivate() {
        // Check if scan is currently running
        if ( get_transient( 'wikis_email_cleaner_scanning' ) ) {
            return new WP_Error(
                'scan_running',
                __( 'Cannot deactivate while email scan is running. Please wait for the scan to complete.', 'wikis-email-cleaner' )
            );
        }

        return true;
    }

    /**
     * Preserve important data during deactivation
     */
    private static function preserve_data() {
        // Get current settings
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        // Store backup of settings
        update_option( 'wikis_email_cleaner_settings_backup', $settings );
        
        // Store deactivation info
        $deactivation_info = array(
            'deactivated_at' => current_time( 'mysql' ),
            'version' => WIKIS_EMAIL_CLEANER_VERSION,
            'wp_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION
        );
        
        update_option( 'wikis_email_cleaner_deactivation_info', $deactivation_info );
    }
}
