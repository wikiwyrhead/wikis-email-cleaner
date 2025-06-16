<?php
/**
 * Revalidation system database schema
 *
 * @package Wikis_Email_Cleaner
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Revalidation schema class
 */
class Wikis_Email_Cleaner_Revalidation_Schema {

    /**
     * Create revalidation tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Revalidation queue table
        $revalidation_queue_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_queue';
        $queue_sql = "CREATE TABLE $revalidation_queue_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) unsigned NOT NULL,
            email varchar(255) NOT NULL,
            original_validation_id bigint(20) unsigned,
            original_score int(3) NOT NULL DEFAULT 0,
            original_unsubscribed_date datetime NOT NULL,
            queue_status enum('pending', 'processing', 'completed', 'failed', 'manual_review') DEFAULT 'pending',
            priority int(3) NOT NULL DEFAULT 50,
            revalidation_attempts int(3) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            processed_at datetime NULL,
            notes text,
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY email (email),
            KEY queue_status (queue_status),
            KEY priority (priority),
            KEY created_at (created_at),
            UNIQUE KEY unique_subscriber (subscriber_id)
        ) $charset_collate;";

        // Revalidation results table
        $revalidation_results_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_results';
        $results_sql = "CREATE TABLE $revalidation_results_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            queue_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            email varchar(255) NOT NULL,
            old_score int(3) NOT NULL DEFAULT 0,
            new_score int(3) NOT NULL DEFAULT 0,
            old_validation_details text,
            new_validation_details text,
            action_taken enum('resubscribed', 'kept_unsubscribed', 'manual_review', 'whitelisted') NOT NULL,
            confidence_level enum('very_low', 'low', 'medium', 'high') NOT NULL,
            improvement_factors text,
            admin_notes text,
            processed_by varchar(100),
            processed_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY queue_id (queue_id),
            KEY subscriber_id (subscriber_id),
            KEY email (email),
            KEY action_taken (action_taken),
            KEY processed_at (processed_at),
            FOREIGN KEY (queue_id) REFERENCES $revalidation_queue_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Revalidation audit log table
        $audit_log_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_audit';
        $audit_sql = "CREATE TABLE $audit_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) unsigned NOT NULL,
            email varchar(255) NOT NULL,
            action enum('queued', 'revalidated', 'resubscribed', 'manual_review', 'rollback') NOT NULL,
            old_status varchar(10),
            new_status varchar(10),
            old_score int(3),
            new_score int(3),
            reason text,
            admin_user_id bigint(20) unsigned,
            ip_address varchar(45),
            user_agent text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY email (email),
            KEY action (action),
            KEY created_at (created_at),
            KEY admin_user_id (admin_user_id)
        ) $charset_collate;";

        // Revalidation settings table
        $settings_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext,
            setting_type enum('string', 'integer', 'boolean', 'array', 'object') DEFAULT 'string',
            description text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY setting_name (setting_name)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $queue_sql );
        dbDelta( $results_sql );
        dbDelta( $audit_sql );
        dbDelta( $settings_sql );

        // Insert default settings
        self::insert_default_settings();
    }

    /**
     * Insert default revalidation settings
     */
    private static function insert_default_settings() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_settings';

        $default_settings = array(
            array(
                'setting_name' => 'revalidation_enabled',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable automatic revalidation system'
            ),
            array(
                'setting_name' => 'batch_size',
                'setting_value' => '50',
                'setting_type' => 'integer',
                'description' => 'Number of emails to process per batch'
            ),
            array(
                'setting_name' => 'score_improvement_threshold',
                'setting_value' => '15',
                'setting_type' => 'integer',
                'description' => 'Minimum score improvement required for resubscription'
            ),
            array(
                'setting_name' => 'manual_review_threshold',
                'setting_value' => '10',
                'setting_type' => 'integer',
                'description' => 'Score improvement threshold for manual review'
            ),
            array(
                'setting_name' => 'max_age_days',
                'setting_value' => '90',
                'setting_type' => 'integer',
                'description' => 'Maximum age of unsubscribed emails to consider for revalidation'
            ),
            array(
                'setting_name' => 'whitelist_domains',
                'setting_value' => '[]',
                'setting_type' => 'array',
                'description' => 'Domains to automatically resubscribe without validation'
            ),
            array(
                'setting_name' => 'notification_email',
                'setting_value' => get_option('admin_email'),
                'setting_type' => 'string',
                'description' => 'Email address for revalidation notifications'
            ),
            array(
                'setting_name' => 'send_notifications',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Send email notifications about revalidation results'
            )
        );

        foreach ($default_settings as $setting) {
            $wpdb->insert(
                $settings_table,
                array_merge($setting, array(
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Drop revalidation tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'wikis_email_cleaner_revalidation_audit',
            $wpdb->prefix . 'wikis_email_cleaner_revalidation_results',
            $wpdb->prefix . 'wikis_email_cleaner_revalidation_queue',
            $wpdb->prefix . 'wikis_email_cleaner_revalidation_settings'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Get revalidation setting
     *
     * @param string $setting_name Setting name
     * @param mixed  $default      Default value
     * @return mixed Setting value
     */
    public static function get_setting($setting_name, $default = null) {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_settings';

        $setting = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT setting_value, setting_type FROM $settings_table WHERE setting_name = %s",
                $setting_name
            )
        );

        if (!$setting) {
            return $default;
        }

        // Convert based on type
        switch ($setting->setting_type) {
            case 'boolean':
                return (bool) $setting->setting_value;
            case 'integer':
                return (int) $setting->setting_value;
            case 'array':
                return json_decode($setting->setting_value, true) ?: array();
            case 'object':
                return json_decode($setting->setting_value);
            default:
                return $setting->setting_value;
        }
    }

    /**
     * Update revalidation setting
     *
     * @param string $setting_name  Setting name
     * @param mixed  $setting_value Setting value
     * @param string $setting_type  Setting type
     * @return bool Success
     */
    public static function update_setting($setting_name, $setting_value, $setting_type = 'string') {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_settings';

        // Convert value based on type
        if ($setting_type === 'array' || $setting_type === 'object') {
            $setting_value = json_encode($setting_value);
        } elseif ($setting_type === 'boolean') {
            $setting_value = $setting_value ? '1' : '0';
        }

        return $wpdb->replace(
            $settings_table,
            array(
                'setting_name' => $setting_name,
                'setting_value' => $setting_value,
                'setting_type' => $setting_type,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
}
