<?php
/**
 * Revalidation migration tool
 *
 * @package Wikis_Email_Cleaner
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Revalidation migration tool class
 */
class Wikis_Email_Cleaner_Revalidation_Migration {

    /**
     * Migration version
     */
    const MIGRATION_VERSION = '1.1.0';

    /**
     * Run migration
     *
     * @return array Migration results
     */
    public static function run_migration() {
        $current_version = get_option( 'wikis_email_cleaner_revalidation_version', '0.0.0' );
        
        if ( version_compare( $current_version, self::MIGRATION_VERSION, '>=' ) ) {
            return array(
                'success' => true,
                'message' => 'Migration not needed - already up to date',
                'migrated' => 0
            );
        }

        $results = array(
            'success' => true,
            'message' => '',
            'migrated' => 0,
            'errors' => array(),
            'steps_completed' => array()
        );

        try {
            // Step 1: Create database tables
            self::create_revalidation_tables();
            $results['steps_completed'][] = 'Created revalidation database tables';

            // Step 2: Migrate existing settings
            $migrated_settings = self::migrate_existing_settings();
            $results['steps_completed'][] = "Migrated {$migrated_settings} settings";

            // Step 3: Analyze existing validation logs
            $analysis = self::analyze_existing_logs();
            $results['steps_completed'][] = "Analyzed {$analysis['total_logs']} existing validation logs";

            // Step 4: Create initial revalidation queue
            $queue_result = self::create_initial_queue( $analysis );
            $results['migrated'] = $queue_result['queued'];
            $results['steps_completed'][] = "Queued {$queue_result['queued']} emails for revalidation";

            // Step 5: Set up scheduler
            self::setup_scheduler();
            $results['steps_completed'][] = 'Set up revalidation scheduler';

            // Step 6: Update version
            update_option( 'wikis_email_cleaner_revalidation_version', self::MIGRATION_VERSION );
            update_option( 'wikis_email_cleaner_revalidation_migration_date', current_time( 'mysql' ) );
            $results['steps_completed'][] = 'Updated version information';

            $results['message'] = sprintf( 
                'Migration completed successfully. %d emails queued for revalidation.', 
                $results['migrated'] 
            );

        } catch ( Exception $e ) {
            $results['success'] = false;
            $results['message'] = 'Migration failed: ' . $e->getMessage();
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Create revalidation tables
     */
    private static function create_revalidation_tables() {
        Wikis_Email_Cleaner_Revalidation_Schema::create_tables();
    }

    /**
     * Migrate existing settings
     *
     * @return int Number of settings migrated
     */
    private static function migrate_existing_settings() {
        $existing_settings = get_option( 'wikis_email_cleaner_settings', array() );
        $migrated_count = 0;

        // Map old settings to new revalidation settings
        $setting_mappings = array(
            'minimum_score' => array(
                'new_key' => 'score_improvement_threshold',
                'transform' => function( $value ) {
                    // Convert minimum score to improvement threshold
                    return max( 10, 60 - intval( $value ) );
                }
            ),
            'batch_size' => array(
                'new_key' => 'batch_size',
                'transform' => function( $value ) {
                    return min( 100, max( 10, intval( $value ) ) );
                }
            ),
            'notification_email' => array(
                'new_key' => 'notification_email',
                'transform' => function( $value ) {
                    return sanitize_email( $value );
                }
            ),
            'send_notifications' => array(
                'new_key' => 'send_notifications',
                'transform' => function( $value ) {
                    return (bool) $value;
                }
            )
        );

        foreach ( $setting_mappings as $old_key => $mapping ) {
            if ( isset( $existing_settings[ $old_key ] ) ) {
                $new_value = $mapping['transform']( $existing_settings[ $old_key ] );
                $type = is_bool( $new_value ) ? 'boolean' : ( is_numeric( $new_value ) ? 'integer' : 'string' );
                
                Wikis_Email_Cleaner_Revalidation_Schema::update_setting( 
                    $mapping['new_key'], 
                    $new_value, 
                    $type 
                );
                $migrated_count++;
            }
        }

        // Set default values for new settings
        $default_settings = array(
            'revalidation_enabled' => false, // Start disabled for safety
            'manual_review_threshold' => 10,
            'max_age_days' => 90,
            'whitelist_domains' => array()
        );

        foreach ( $default_settings as $key => $value ) {
            $type = is_bool( $value ) ? 'boolean' : ( is_array( $value ) ? 'array' : ( is_numeric( $value ) ? 'integer' : 'string' ) );
            Wikis_Email_Cleaner_Revalidation_Schema::update_setting( $key, $value, $type );
            $migrated_count++;
        }

        return $migrated_count;
    }

    /**
     * Analyze existing validation logs
     *
     * @return array Analysis results
     */
    private static function analyze_existing_logs() {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'wikis_email_cleaner_logs';

        // Get statistics about existing logs
        $analysis = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_logs,
                COUNT(CASE WHEN action = 'auto_unsubscribed' THEN 1 END) as auto_unsubscribed,
                COUNT(CASE WHEN score BETWEEN 30 AND 60 THEN 1 END) as borderline_scores,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) as recent_logs,
                AVG(score) as avg_score,
                MIN(score) as min_score,
                MAX(score) as max_score
            FROM $logs_table",
            ARRAY_A
        );

        if ( ! $analysis ) {
            return array(
                'total_logs' => 0,
                'auto_unsubscribed' => 0,
                'borderline_scores' => 0,
                'recent_logs' => 0,
                'avg_score' => 0,
                'candidates_for_revalidation' => 0
            );
        }

        // Estimate candidates for revalidation
        $candidates = $wpdb->get_var(
            "SELECT COUNT(DISTINCT wcl.email)
            FROM $logs_table wcl
            INNER JOIN {$wpdb->prefix}newsletter nu ON wcl.email = nu.email
            WHERE wcl.action = 'auto_unsubscribed'
            AND nu.status = 'U'
            AND wcl.score BETWEEN 20 AND 60
            AND wcl.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        $analysis['candidates_for_revalidation'] = intval( $candidates );

        return $analysis;
    }

    /**
     * Create initial revalidation queue
     *
     * @param array $analysis Log analysis results
     * @return array Queue creation results
     */
    private static function create_initial_queue( $analysis ) {
        // Only create queue if there are reasonable candidates
        if ( $analysis['candidates_for_revalidation'] < 1 ) {
            return array(
                'success' => true,
                'queued' => 0,
                'message' => 'No suitable candidates found for initial queue'
            );
        }

        // Use conservative criteria for initial migration
        $criteria = array(
            'max_age_days' => 60, // More recent emails first
            'min_original_score' => 25, // Only emails that weren't completely invalid
            'max_original_score' => 55, // Only emails that were close to passing
            'limit' => min( 500, $analysis['candidates_for_revalidation'] ), // Reasonable limit
            'force_repopulate' => true
        );

        return Wikis_Email_Cleaner_Revalidation_Queue::populate_queue( $criteria );
    }

    /**
     * Setup scheduler
     */
    private static function setup_scheduler() {
        $scheduler = new Wikis_Email_Cleaner_Revalidation_Scheduler();
        $scheduler->schedule_events();
    }

    /**
     * Check if migration is needed
     *
     * @return bool True if migration is needed
     */
    public static function is_migration_needed() {
        $current_version = get_option( 'wikis_email_cleaner_revalidation_version', '0.0.0' );
        return version_compare( $current_version, self::MIGRATION_VERSION, '<' );
    }

    /**
     * Get migration status
     *
     * @return array Migration status information
     */
    public static function get_migration_status() {
        $current_version = get_option( 'wikis_email_cleaner_revalidation_version', '0.0.0' );
        $migration_date = get_option( 'wikis_email_cleaner_revalidation_migration_date', null );
        
        return array(
            'current_version' => $current_version,
            'target_version' => self::MIGRATION_VERSION,
            'is_migrated' => version_compare( $current_version, self::MIGRATION_VERSION, '>=' ),
            'migration_date' => $migration_date,
            'migration_needed' => self::is_migration_needed()
        );
    }

    /**
     * Rollback migration (emergency use only)
     *
     * @return array Rollback results
     */
    public static function rollback_migration() {
        $results = array(
            'success' => true,
            'message' => '',
            'steps_completed' => array(),
            'errors' => array()
        );

        try {
            // Step 1: Unschedule events
            $scheduler = new Wikis_Email_Cleaner_Revalidation_Scheduler();
            $scheduler->unschedule_all();
            $results['steps_completed'][] = 'Unscheduled revalidation events';

            // Step 2: Clear revalidation settings
            self::clear_revalidation_settings();
            $results['steps_completed'][] = 'Cleared revalidation settings';

            // Step 3: Drop revalidation tables (optional - commented out for safety)
            // Wikis_Email_Cleaner_Revalidation_Schema::drop_tables();
            // $results['steps_completed'][] = 'Dropped revalidation tables';

            // Step 4: Reset version
            delete_option( 'wikis_email_cleaner_revalidation_version' );
            delete_option( 'wikis_email_cleaner_revalidation_migration_date' );
            $results['steps_completed'][] = 'Reset version information';

            $results['message'] = 'Migration rollback completed successfully';

        } catch ( Exception $e ) {
            $results['success'] = false;
            $results['message'] = 'Rollback failed: ' . $e->getMessage();
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Clear revalidation settings
     */
    private static function clear_revalidation_settings() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_settings';
        
        // Check if table exists before trying to clear it
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$settings_table'" );
        if ( $table_exists ) {
            $wpdb->query( "DELETE FROM $settings_table" );
        }
    }

    /**
     * Validate migration prerequisites
     *
     * @return array Validation results
     */
    public static function validate_prerequisites() {
        $issues = array();
        $warnings = array();

        // Check if Newsletter plugin is active
        if ( ! class_exists( 'Newsletter' ) && ! function_exists( 'newsletter_get_user' ) ) {
            $issues[] = 'Newsletter plugin is not active or installed';
        }

        // Check database permissions
        global $wpdb;
        $test_table = $wpdb->prefix . 'wikis_test_permissions';
        $create_result = $wpdb->query( "CREATE TABLE IF NOT EXISTS $test_table (id INT)" );
        if ( $create_result === false ) {
            $issues[] = 'Insufficient database permissions to create tables';
        } else {
            $wpdb->query( "DROP TABLE IF EXISTS $test_table" );
        }

        // Check if there are existing logs to migrate
        $logs_table = $wpdb->prefix . 'wikis_email_cleaner_logs';
        $log_count = $wpdb->get_var( "SELECT COUNT(*) FROM $logs_table" );
        if ( $log_count < 1 ) {
            $warnings[] = 'No existing validation logs found - migration will create empty revalidation system';
        }

        // Check available disk space (rough estimate)
        $estimated_space_needed = $log_count * 1024; // 1KB per log entry estimate
        if ( $estimated_space_needed > 10485760 ) { // 10MB
            $warnings[] = 'Migration may require significant disk space for revalidation tables';
        }

        // Check memory limit
        $memory_limit = ini_get( 'memory_limit' );
        $memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
        if ( $memory_bytes < 134217728 ) { // 128MB
            $warnings[] = 'Low memory limit may cause issues during migration of large datasets';
        }

        return array(
            'can_migrate' => empty( $issues ),
            'issues' => $issues,
            'warnings' => $warnings,
            'estimated_candidates' => $log_count > 0 ? intval( $log_count * 0.3 ) : 0 // Rough estimate
        );
    }

    /**
     * Get migration progress
     *
     * @return array Migration progress information
     */
    public static function get_migration_progress() {
        // This would be used during a long-running migration
        // For now, return basic status
        $status = self::get_migration_status();
        
        if ( $status['is_migrated'] ) {
            $queue_stats = Wikis_Email_Cleaner_Revalidation_Queue::get_statistics();
            return array(
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Migration completed',
                'queue_populated' => $queue_stats['total'] > 0,
                'emails_queued' => $queue_stats['total']
            );
        }

        return array(
            'status' => 'not_started',
            'progress' => 0,
            'message' => 'Migration not started',
            'queue_populated' => false,
            'emails_queued' => 0
        );
    }
}
