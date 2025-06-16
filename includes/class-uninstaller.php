<?php
/**
 * Plugin uninstall class
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin uninstaller class
 */
class Wikis_Email_Cleaner_Uninstaller {

    /**
     * Uninstall the plugin
     */
    public static function uninstall() {
        // Check if user has permission to uninstall
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        // Check if this is a multisite and if so, handle accordingly
        if ( is_multisite() ) {
            self::uninstall_multisite();
        } else {
            self::uninstall_single_site();
        }
    }

    /**
     * Uninstall for single site
     */
    private static function uninstall_single_site() {
        // Get settings to check if data should be preserved
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        $preserve_data = isset( $settings['preserve_data_on_uninstall'] ) ? $settings['preserve_data_on_uninstall'] : false;

        if ( ! $preserve_data ) {
            // Remove database tables
            self::drop_tables();
            
            // Remove all plugin options
            self::remove_options();
            
            // Remove uploaded files
            self::remove_uploaded_files();
        }

        // Always clear cron jobs and transients
        self::clear_cron_jobs();
        self::clear_transients();
        
        // Log uninstallation
        error_log( 'Wikis Email Cleaner plugin uninstalled' );
    }

    /**
     * Uninstall for multisite
     */
    private static function uninstall_multisite() {
        global $wpdb;

        // Get all blog IDs
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            self::uninstall_single_site();
            restore_current_blog();
        }
    }

    /**
     * Drop plugin database tables
     */
    private static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'wikis_email_cleaner_logs',
            $wpdb->prefix . 'wikis_email_cleaner_scan_summary'
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }

    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        global $wpdb;

        // Remove specific options
        $options = array(
            'wikis_email_cleaner_settings',
            'wikis_email_cleaner_settings_backup',
            'wikis_email_cleaner_disposable_domains',
            'wikis_email_cleaner_db_version',
            'wikis_email_cleaner_activated',
            'wikis_email_cleaner_deactivated_at',
            'wikis_email_cleaner_deactivation_info'
        );

        foreach ( $options as $option ) {
            delete_option( $option );
        }

        // Remove any options that start with our prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE 'wikis_email_cleaner_%'"
        );
    }

    /**
     * Clear all cron jobs
     */
    private static function clear_cron_jobs() {
        wp_clear_scheduled_hook( 'wikis_email_cleaner_scheduled_scan' );
        wp_clear_scheduled_hook( 'wikis_email_cleaner_cleanup' );
    }

    /**
     * Clear all transients
     */
    private static function clear_transients() {
        global $wpdb;

        // Clear plugin-specific transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wikis_email_cleaner_%' 
             OR option_name LIKE '_transient_timeout_wikis_email_cleaner_%'"
        );
    }

    /**
     * Remove uploaded files and directories
     */
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/wikis-email-cleaner';

        if ( file_exists( $plugin_upload_dir ) ) {
            self::remove_directory( $plugin_upload_dir );
        }
    }

    /**
     * Recursively remove directory and its contents
     *
     * @param string $dir Directory path
     */
    private static function remove_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = array_diff( scandir( $dir ), array( '.', '..' ) );

        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            
            if ( is_dir( $path ) ) {
                self::remove_directory( $path );
            } else {
                unlink( $path );
            }
        }

        rmdir( $dir );
    }

    /**
     * Send uninstall notification
     */
    private static function send_uninstall_notification() {
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        
        if ( empty( $settings['send_notifications'] ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            __( '[%s] Email Cleaner Plugin Uninstalled', 'wikis-email-cleaner' ),
            $site_name
        );

        $message = sprintf(
            __( "The Wikis Email Cleaner plugin has been completely uninstalled from %s.\n\nUninstalled at: %s\n\nAll plugin data has been removed.", 'wikis-email-cleaner' ),
            $site_name,
            current_time( 'Y-m-d H:i:s' )
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Create uninstall feedback data
     */
    public static function create_feedback_data() {
        $feedback_data = array(
            'uninstalled_at' => current_time( 'mysql' ),
            'version' => defined( 'WIKIS_EMAIL_CLEANER_VERSION' ) ? WIKIS_EMAIL_CLEANER_VERSION : '1.0.0',
            'wp_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'site_url' => get_site_url(),
            'admin_email' => get_option( 'admin_email' )
        );

        // Store temporarily for potential feedback collection
        set_transient( 'wikis_email_cleaner_uninstall_feedback', $feedback_data, WEEK_IN_SECONDS );
    }

    /**
     * Check if safe to uninstall
     *
     * @return bool|WP_Error True if safe, WP_Error otherwise
     */
    public static function can_uninstall() {
        // Check if scan is currently running
        if ( get_transient( 'wikis_email_cleaner_scanning' ) ) {
            return new WP_Error(
                'scan_running',
                __( 'Cannot uninstall while email scan is running. Please wait for the scan to complete.', 'wikis-email-cleaner' )
            );
        }

        // Check user permissions
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error(
                'insufficient_permissions',
                __( 'You do not have sufficient permissions to uninstall this plugin.', 'wikis-email-cleaner' )
            );
        }

        return true;
    }

    /**
     * Export data before uninstall
     *
     * @return string|false Export file path or false on failure
     */
    public static function export_data_before_uninstall() {
        if ( ! class_exists( 'Wikis_Email_Cleaner_Logger' ) ) {
            return false;
        }

        $logger = new Wikis_Email_Cleaner_Logger();
        
        try {
            $export_url = $logger->export_to_csv( array( 'limit' => 50000 ) );
            return $export_url;
        } catch ( Exception $e ) {
            error_log( 'Failed to export data before uninstall: ' . $e->getMessage() );
            return false;
        }
    }
}
