<?php
/**
 * Plugin activation class
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin activator class
 */
class Wikis_Email_Cleaner_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule initial cron job
        self::schedule_cron_jobs();
        
        // Create upload directory
        self::create_upload_directory();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option( 'wikis_email_cleaner_activated', true );
        
        // Log activation
        error_log( 'Wikis Email Cleaner plugin activated successfully' );
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Validation logs table
        $logs_table = $wpdb->prefix . 'wikis_email_cleaner_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) unsigned NOT NULL,
            email varchar(255) NOT NULL,
            is_valid tinyint(1) NOT NULL DEFAULT 0,
            score int(3) NOT NULL DEFAULT 0,
            errors text,
            warnings text,
            action_taken varchar(50) NOT NULL DEFAULT 'none',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY email (email),
            KEY is_valid (is_valid),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Scan summary table
        $summary_table = $wpdb->prefix . 'wikis_email_cleaner_scan_summary';
        $summary_sql = "CREATE TABLE $summary_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            total_processed int(10) unsigned NOT NULL DEFAULT 0,
            total_invalid int(10) unsigned NOT NULL DEFAULT 0,
            total_unsubscribed int(10) unsigned NOT NULL DEFAULT 0,
            scan_date datetime NOT NULL,
            scan_type varchar(20) NOT NULL DEFAULT 'manual',
            PRIMARY KEY (id),
            KEY scan_date (scan_date),
            KEY scan_type (scan_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $logs_sql );
        dbDelta( $summary_sql );

        // Update database version
        update_option( 'wikis_email_cleaner_db_version', '1.0.0' );
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_settings = array(
            'enable_auto_clean' => false,
            'enable_deep_validation' => false,
            'minimum_score' => 50,
            'schedule_frequency' => 'daily',
            'validate_on_subscription' => true,
            'subscription_minimum_score' => 30,
            'deep_validate_on_confirm' => false,
            'handle_bounces_complaints' => true,
            'send_notifications' => false,
            'notification_email' => get_option( 'admin_email' ),
            'log_retention_days' => 30,
            'batch_size' => 100
        );

        // Only set defaults if settings don't exist
        if ( ! get_option( 'wikis_email_cleaner_settings' ) ) {
            update_option( 'wikis_email_cleaner_settings', $default_settings );
        }

        // Set default disposable domains if not exists
        if ( ! get_option( 'wikis_email_cleaner_disposable_domains' ) ) {
            $default_disposable_domains = array(
                '10minutemail.com',
                'guerrillamail.com',
                'mailinator.com',
                'tempmail.org',
                'yopmail.com',
                'throwaway.email',
                'temp-mail.org',
                'getnada.com',
                'maildrop.cc',
                'sharklasers.com',
                'guerrillamailblock.com',
                'mohmal.com',
                'emailondeck.com',
                'fakeinbox.com',
                'spamgourmet.com'
            );
            
            update_option( 'wikis_email_cleaner_disposable_domains', $default_disposable_domains );
        }
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
        // Clear any existing schedules
        $timestamp = wp_next_scheduled( 'wikis_email_cleaner_scheduled_scan' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wikis_email_cleaner_scheduled_scan' );
        }

        // Schedule cleanup job (weekly)
        if ( ! wp_next_scheduled( 'wikis_email_cleaner_cleanup' ) ) {
            wp_schedule_event( time(), 'weekly', 'wikis_email_cleaner_cleanup' );
        }
    }

    /**
     * Create upload directory for exports
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/wikis-email-cleaner';

        if ( ! file_exists( $plugin_upload_dir ) ) {
            wp_mkdir_p( $plugin_upload_dir );
            
            // Create .htaccess file to protect directory
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents( $plugin_upload_dir . '/.htaccess', $htaccess_content );
            
            // Create index.php file
            file_put_contents( $plugin_upload_dir . '/index.php', '<?php // Silence is golden' );
        }
    }

    /**
     * Check if plugin can be activated (for manual checks)
     *
     * @return bool|WP_Error True if can activate, WP_Error otherwise
     */
    public static function can_activate() {
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.5', '<' ) ) {
            return new WP_Error(
                'wp_version',
                'Wikis Email Cleaner requires WordPress 5.5 or higher.'
            );
        }

        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            return new WP_Error(
                'php_version',
                'Wikis Email Cleaner requires PHP 7.4 or higher.'
            );
        }

        return true;
    }

    /**
     * Upgrade database if needed
     */
    public static function upgrade_database() {
        $current_version = get_option( 'wikis_email_cleaner_db_version', '0.0.0' );
        
        if ( version_compare( $current_version, '1.0.0', '<' ) ) {
            self::create_tables();
        }
        
        // Add future upgrade logic here
    }

    /**
     * Create admin notice for successful activation
     */
    public static function activation_notice() {
        if ( get_option( 'wikis_email_cleaner_activated' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php esc_html_e( 'Wikis Email Cleaner has been activated successfully!', 'wikis-email-cleaner' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wikis-email-cleaner' ) ); ?>">
                        <?php esc_html_e( 'Configure settings', 'wikis-email-cleaner' ); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_option( 'wikis_email_cleaner_activated' );
        }
    }
}
