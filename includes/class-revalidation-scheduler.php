<?php
/**
 * Revalidation scheduler
 *
 * @package Wikis_Email_Cleaner
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Revalidation scheduler class
 */
class Wikis_Email_Cleaner_Revalidation_Scheduler {

    /**
     * Hook name for scheduled revalidation
     */
    const HOOK_REVALIDATION = 'wikis_email_cleaner_revalidation_cron';

    /**
     * Hook name for queue population
     */
    const HOOK_POPULATE_QUEUE = 'wikis_email_cleaner_populate_queue_cron';

    /**
     * Initialize scheduler
     */
    public function __construct() {
        add_action( self::HOOK_REVALIDATION, array( $this, 'run_scheduled_revalidation' ) );
        add_action( self::HOOK_POPULATE_QUEUE, array( $this, 'run_scheduled_queue_population' ) );
        add_action( 'init', array( $this, 'schedule_events' ) );
        add_action( 'wp', array( $this, 'check_schedule_health' ) );
    }

    /**
     * Schedule recurring events
     */
    public function schedule_events() {
        // Schedule revalidation processing
        if ( ! wp_next_scheduled( self::HOOK_REVALIDATION ) ) {
            wp_schedule_event( time(), 'hourly', self::HOOK_REVALIDATION );
        }

        // Schedule queue population (less frequent)
        if ( ! wp_next_scheduled( self::HOOK_POPULATE_QUEUE ) ) {
            wp_schedule_event( time(), 'daily', self::HOOK_POPULATE_QUEUE );
        }
    }

    /**
     * Run scheduled revalidation
     */
    public function run_scheduled_revalidation() {
        // Check if revalidation is enabled
        if ( ! Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'revalidation_enabled', false ) ) {
            return;
        }

        // Prevent overlapping executions
        if ( get_transient( 'wikis_email_cleaner_revalidation_processing' ) ) {
            $this->log_scheduler_event( 'revalidation_skipped', 'Previous revalidation still in progress' );
            return;
        }

        $this->log_scheduler_event( 'revalidation_started', 'Scheduled revalidation started' );

        try {
            $processor = new Wikis_Email_Cleaner_Revalidation_Processor();
            $batch_size = Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'batch_size', 50 );
            
            // Process smaller batches in scheduled runs to avoid timeouts
            $scheduled_batch_size = min( $batch_size, 25 );
            
            $result = $processor->process_queue( $scheduled_batch_size );

            if ( $result['success'] ) {
                $this->log_scheduler_event( 
                    'revalidation_completed', 
                    sprintf( 'Processed %d emails', $result['results']['processed'] ?? 0 ),
                    $result['results'] ?? array()
                );

                // Schedule immediate follow-up if there are more items in queue
                $stats = Wikis_Email_Cleaner_Revalidation_Queue::get_statistics();
                if ( $stats['pending'] > 0 ) {
                    wp_schedule_single_event( time() + 300, self::HOOK_REVALIDATION ); // 5 minutes later
                }
            } else {
                $this->log_scheduler_event( 'revalidation_failed', $result['message'] );
            }

        } catch ( Exception $e ) {
            $this->log_scheduler_event( 'revalidation_error', 'Exception: ' . $e->getMessage() );
        }
    }

    /**
     * Run scheduled queue population
     */
    public function run_scheduled_queue_population() {
        // Check if revalidation is enabled
        if ( ! Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'revalidation_enabled', false ) ) {
            return;
        }

        $this->log_scheduler_event( 'queue_population_started', 'Scheduled queue population started' );

        try {
            // Get current queue stats
            $stats = Wikis_Email_Cleaner_Revalidation_Queue::get_statistics();
            
            // Only populate if queue is getting low
            if ( $stats['pending'] > 100 ) {
                $this->log_scheduler_event( 'queue_population_skipped', 'Queue still has sufficient items' );
                return;
            }

            $criteria = array(
                'max_age_days' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'max_age_days', 90 ),
                'limit' => 500, // Smaller limit for scheduled runs
                'force_repopulate' => false
            );

            $result = Wikis_Email_Cleaner_Revalidation_Queue::populate_queue( $criteria );

            if ( $result['success'] ) {
                $this->log_scheduler_event( 
                    'queue_population_completed', 
                    sprintf( 'Queued %d emails', $result['queued'] )
                );
            } else {
                $this->log_scheduler_event( 'queue_population_failed', $result['message'] );
            }

        } catch ( Exception $e ) {
            $this->log_scheduler_event( 'queue_population_error', 'Exception: ' . $e->getMessage() );
        }
    }

    /**
     * Check schedule health and fix if needed
     */
    public function check_schedule_health() {
        // Only run this check once per day
        if ( get_transient( 'wikis_revalidation_schedule_check' ) ) {
            return;
        }

        set_transient( 'wikis_revalidation_schedule_check', true, DAY_IN_SECONDS );

        // Check if scheduled events exist
        $revalidation_scheduled = wp_next_scheduled( self::HOOK_REVALIDATION );
        $population_scheduled = wp_next_scheduled( self::HOOK_POPULATE_QUEUE );

        $issues = array();

        if ( ! $revalidation_scheduled ) {
            wp_schedule_event( time(), 'hourly', self::HOOK_REVALIDATION );
            $issues[] = 'Revalidation schedule was missing and has been restored';
        }

        if ( ! $population_scheduled ) {
            wp_schedule_event( time(), 'daily', self::HOOK_POPULATE_QUEUE );
            $issues[] = 'Queue population schedule was missing and has been restored';
        }

        // Check for stuck processing
        $processing_transient = get_transient( 'wikis_email_cleaner_revalidation_processing' );
        if ( $processing_transient ) {
            // If processing flag has been set for more than 2 hours, clear it
            $processing_start = get_option( 'wikis_revalidation_processing_start', 0 );
            if ( $processing_start && ( time() - $processing_start ) > 7200 ) {
                delete_transient( 'wikis_email_cleaner_revalidation_processing' );
                delete_option( 'wikis_revalidation_processing_start' );
                $issues[] = 'Cleared stuck processing flag';
            }
        }

        if ( ! empty( $issues ) ) {
            $this->log_scheduler_event( 'schedule_health_check', implode( '; ', $issues ) );
        }
    }

    /**
     * Get scheduler status
     *
     * @return array Scheduler status information
     */
    public function get_status() {
        $revalidation_next = wp_next_scheduled( self::HOOK_REVALIDATION );
        $population_next = wp_next_scheduled( self::HOOK_POPULATE_QUEUE );
        $processing = get_transient( 'wikis_email_cleaner_revalidation_processing' );

        return array(
            'revalidation_enabled' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'revalidation_enabled', false ),
            'revalidation_next_run' => $revalidation_next ? date( 'Y-m-d H:i:s', $revalidation_next ) : null,
            'population_next_run' => $population_next ? date( 'Y-m-d H:i:s', $population_next ) : null,
            'currently_processing' => (bool) $processing,
            'processing_since' => $processing ? get_option( 'wikis_revalidation_processing_start', 0 ) : null,
            'last_run_log' => $this->get_last_run_log()
        );
    }

    /**
     * Get recent scheduler logs
     *
     * @param int $limit Number of logs to retrieve
     * @return array Recent scheduler logs
     */
    public function get_recent_logs( $limit = 10 ) {
        $logs = get_option( 'wikis_revalidation_scheduler_logs', array() );
        return array_slice( $logs, -$limit );
    }

    /**
     * Clear scheduler logs
     */
    public function clear_logs() {
        delete_option( 'wikis_revalidation_scheduler_logs' );
    }

    /**
     * Manually trigger revalidation
     *
     * @return array Result of manual trigger
     */
    public function manual_trigger() {
        if ( get_transient( 'wikis_email_cleaner_revalidation_processing' ) ) {
            return array(
                'success' => false,
                'message' => 'Revalidation is already in progress'
            );
        }

        // Schedule immediate execution
        wp_schedule_single_event( time() + 10, self::HOOK_REVALIDATION );

        $this->log_scheduler_event( 'manual_trigger', 'Revalidation manually triggered' );

        return array(
            'success' => true,
            'message' => 'Revalidation has been scheduled to run in 10 seconds'
        );
    }

    /**
     * Manually trigger queue population
     *
     * @return array Result of manual trigger
     */
    public function manual_populate_queue() {
        // Schedule immediate execution
        wp_schedule_single_event( time() + 10, self::HOOK_POPULATE_QUEUE );

        $this->log_scheduler_event( 'manual_populate_trigger', 'Queue population manually triggered' );

        return array(
            'success' => true,
            'message' => 'Queue population has been scheduled to run in 10 seconds'
        );
    }

    /**
     * Unschedule all events (for deactivation)
     */
    public function unschedule_all() {
        wp_clear_scheduled_hook( self::HOOK_REVALIDATION );
        wp_clear_scheduled_hook( self::HOOK_POPULATE_QUEUE );
        
        $this->log_scheduler_event( 'unscheduled_all', 'All scheduled events have been cleared' );
    }

    /**
     * Log scheduler event
     *
     * @param string $event_type Type of event
     * @param string $message    Event message
     * @param array  $data       Additional event data
     */
    private function log_scheduler_event( $event_type, $message, $data = array() ) {
        $logs = get_option( 'wikis_revalidation_scheduler_logs', array() );
        
        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'event_type' => $event_type,
            'message' => $message,
            'data' => $data,
            'memory_usage' => memory_get_usage( true ),
            'peak_memory' => memory_get_peak_usage( true )
        );

        $logs[] = $log_entry;

        // Keep only last 100 log entries
        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -100 );
        }

        update_option( 'wikis_revalidation_scheduler_logs', $logs );

        // Also log critical events to WordPress error log
        if ( in_array( $event_type, array( 'revalidation_error', 'queue_population_error' ), true ) ) {
            error_log( "Wikis Email Cleaner Revalidation: $event_type - $message" );
        }
    }

    /**
     * Get last run log entry
     *
     * @return array|null Last run log entry
     */
    private function get_last_run_log() {
        $logs = get_option( 'wikis_revalidation_scheduler_logs', array() );
        
        // Find the most recent completed run
        for ( $i = count( $logs ) - 1; $i >= 0; $i-- ) {
            if ( in_array( $logs[$i]['event_type'], array( 'revalidation_completed', 'revalidation_failed' ), true ) ) {
                return $logs[$i];
            }
        }

        return null;
    }

    /**
     * Get performance metrics
     *
     * @return array Performance metrics
     */
    public function get_performance_metrics() {
        $logs = $this->get_recent_logs( 50 );
        $completed_runs = array_filter( $logs, function( $log ) {
            return $log['event_type'] === 'revalidation_completed';
        });

        if ( empty( $completed_runs ) ) {
            return array(
                'avg_processing_time' => 0,
                'avg_emails_per_run' => 0,
                'avg_memory_usage' => 0,
                'success_rate' => 0
            );
        }

        $total_emails = 0;
        $total_memory = 0;
        $run_count = count( $completed_runs );

        foreach ( $completed_runs as $run ) {
            $total_emails += $run['data']['processed'] ?? 0;
            $total_memory += $run['memory_usage'] ?? 0;
        }

        return array(
            'avg_emails_per_run' => $run_count > 0 ? round( $total_emails / $run_count, 2 ) : 0,
            'avg_memory_usage' => $run_count > 0 ? round( $total_memory / $run_count / 1024 / 1024, 2 ) : 0, // MB
            'total_runs' => $run_count,
            'total_emails_processed' => $total_emails
        );
    }
}
