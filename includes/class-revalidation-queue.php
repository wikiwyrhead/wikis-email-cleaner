<?php
/**
 * Revalidation queue manager
 *
 * @package Wikis_Email_Cleaner
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Revalidation queue manager class
 */
class Wikis_Email_Cleaner_Revalidation_Queue {

    /**
     * Populate revalidation queue with previously invalidated emails
     *
     * @param array $criteria Criteria for selecting emails
     * @return array Results of queue population
     */
    public static function populate_queue( $criteria = array() ) {
        global $wpdb;
        
        $defaults = array(
            'max_age_days' => 90,
            'min_original_score' => 0,
            'max_original_score' => 60,
            'limit' => 1000,
            'force_repopulate' => false
        );
        
        $criteria = wp_parse_args( $criteria, $defaults );
        
        // Get Newsletter plugin table names
        $newsletter_users_table = $wpdb->prefix . 'newsletter';
        $logs_table = $wpdb->prefix . 'wikis_email_cleaner_logs';
        $queue_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_queue';
        
        // Check if Newsletter plugin is active
        if ( ! self::is_newsletter_plugin_active() ) {
            return array(
                'success' => false,
                'message' => 'Newsletter plugin not found or inactive',
                'queued' => 0
            );
        }
        
        // Clear existing queue if force repopulate
        if ( $criteria['force_repopulate'] ) {
            $wpdb->query( "TRUNCATE TABLE $queue_table" );
        }
        
        // Find unsubscribed users who were auto-unsubscribed by our plugin
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$criteria['max_age_days']} days" ) );
        
        $sql = "
            SELECT DISTINCT 
                nu.id as subscriber_id,
                nu.email,
                wcl.id as original_validation_id,
                wcl.score as original_score,
                wcl.created_at as original_unsubscribed_date
            FROM $newsletter_users_table nu
            INNER JOIN $logs_table wcl ON nu.email = wcl.email
            WHERE nu.status = 'U'
            AND wcl.action = 'auto_unsubscribed'
            AND wcl.score BETWEEN %d AND %d
            AND wcl.created_at >= %s
            AND nu.id NOT IN (
                SELECT subscriber_id 
                FROM $queue_table 
                WHERE queue_status IN ('pending', 'processing', 'completed')
            )
            ORDER BY wcl.score DESC, wcl.created_at DESC
            LIMIT %d
        ";
        
        $candidates = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                $criteria['min_original_score'],
                $criteria['max_original_score'],
                $cutoff_date,
                $criteria['limit']
            )
        );
        
        if ( empty( $candidates ) ) {
            return array(
                'success' => true,
                'message' => 'No eligible emails found for revalidation',
                'queued' => 0
            );
        }
        
        // Calculate priority for each candidate
        $queued_count = 0;
        foreach ( $candidates as $candidate ) {
            $priority = self::calculate_priority( $candidate );
            
            $result = $wpdb->insert(
                $queue_table,
                array(
                    'subscriber_id' => $candidate->subscriber_id,
                    'email' => $candidate->email,
                    'original_validation_id' => $candidate->original_validation_id,
                    'original_score' => $candidate->original_score,
                    'original_unsubscribed_date' => $candidate->original_unsubscribed_date,
                    'queue_status' => 'pending',
                    'priority' => $priority,
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%d', '%s', '%d', '%d', '%s', '%s', '%d', '%s' )
            );
            
            if ( $result ) {
                $queued_count++;
                
                // Log the queuing action
                self::log_audit_action(
                    $candidate->subscriber_id,
                    $candidate->email,
                    'queued',
                    null,
                    null,
                    $candidate->original_score,
                    null,
                    'Email queued for revalidation'
                );
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf( 'Successfully queued %d emails for revalidation', $queued_count ),
            'queued' => $queued_count,
            'total_candidates' => count( $candidates )
        );
    }
    
    /**
     * Calculate priority for queue item
     *
     * @param object $candidate Candidate email data
     * @return int Priority (1-100, higher = more priority)
     */
    private static function calculate_priority( $candidate ) {
        $priority = 50; // Base priority
        
        // Higher priority for emails with higher original scores (closer to passing)
        if ( $candidate->original_score >= 50 ) {
            $priority += 30;
        } elseif ( $candidate->original_score >= 40 ) {
            $priority += 20;
        } elseif ( $candidate->original_score >= 30 ) {
            $priority += 10;
        }
        
        // Higher priority for more recently unsubscribed emails
        $days_ago = ( time() - strtotime( $candidate->original_unsubscribed_date ) ) / DAY_IN_SECONDS;
        if ( $days_ago <= 7 ) {
            $priority += 15;
        } elseif ( $days_ago <= 30 ) {
            $priority += 10;
        } elseif ( $days_ago <= 60 ) {
            $priority += 5;
        }
        
        // Higher priority for business domains
        $domain = substr( strrchr( $candidate->email, '@' ), 1 );
        if ( self::is_business_domain( $domain ) ) {
            $priority += 10;
        }
        
        // Higher priority for whitelisted domains
        $whitelist = Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'whitelist_domains', array() );
        if ( in_array( strtolower( $domain ), array_map( 'strtolower', $whitelist ), true ) ) {
            $priority += 25;
        }
        
        return min( 100, max( 1, $priority ) );
    }
    
    /**
     * Get next batch of emails to process
     *
     * @param int $batch_size Number of emails to retrieve
     * @return array Queue items to process
     */
    public static function get_next_batch( $batch_size = null ) {
        global $wpdb;
        
        if ( $batch_size === null ) {
            $batch_size = Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'batch_size', 50 );
        }
        
        $queue_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_queue';
        
        // Get pending items ordered by priority and creation date
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $queue_table 
                WHERE queue_status = 'pending' 
                AND revalidation_attempts < 3
                ORDER BY priority DESC, created_at ASC 
                LIMIT %d",
                $batch_size
            )
        );
        
        // Mark items as processing
        if ( ! empty( $items ) ) {
            $ids = wp_list_pluck( $items, 'id' );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $queue_table 
                    SET queue_status = 'processing', 
                        revalidation_attempts = revalidation_attempts + 1 
                    WHERE id IN ($placeholders)",
                    ...$ids
                )
            );
        }
        
        return $items;
    }
    
    /**
     * Update queue item status
     *
     * @param int    $queue_id Queue item ID
     * @param string $status   New status
     * @param string $notes    Optional notes
     * @return bool Success
     */
    public static function update_status( $queue_id, $status, $notes = '' ) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_queue';
        
        $update_data = array(
            'queue_status' => $status,
            'processed_at' => current_time( 'mysql' )
        );
        
        if ( ! empty( $notes ) ) {
            $update_data['notes'] = $notes;
        }
        
        return $wpdb->update(
            $queue_table,
            $update_data,
            array( 'id' => $queue_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }
    
    /**
     * Get queue statistics
     *
     * @return array Queue statistics
     */
    public static function get_statistics() {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_queue';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN queue_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN queue_status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN queue_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN queue_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN queue_status = 'manual_review' THEN 1 ELSE 0 END) as manual_review,
                AVG(priority) as avg_priority
            FROM $queue_table",
            ARRAY_A
        );
        
        return $stats ?: array(
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'manual_review' => 0,
            'avg_priority' => 0
        );
    }
    
    /**
     * Check if Newsletter plugin is active
     *
     * @return bool True if active
     */
    private static function is_newsletter_plugin_active() {
        return class_exists( 'Newsletter' ) || function_exists( 'newsletter_get_user' );
    }
    
    /**
     * Check if domain appears to be a business domain
     *
     * @param string $domain Domain name
     * @return bool True if business domain
     */
    private static function is_business_domain( $domain ) {
        $business_patterns = array(
            '/\.(com|org|net|edu|gov)$/',
            '/\.(co\.|com\.)[a-z]{2}$/',
            '/\.(inc|corp|ltd|llc)\./i'
        );
        
        foreach ( $business_patterns as $pattern ) {
            if ( preg_match( $pattern, $domain ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log audit action
     *
     * @param int    $subscriber_id Subscriber ID
     * @param string $email         Email address
     * @param string $action        Action taken
     * @param string $old_status    Old status
     * @param string $new_status    New status
     * @param int    $old_score     Old score
     * @param int    $new_score     New score
     * @param string $reason        Reason for action
     */
    private static function log_audit_action( $subscriber_id, $email, $action, $old_status, $new_status, $old_score, $new_score, $reason ) {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_audit';
        
        $wpdb->insert(
            $audit_table,
            array(
                'subscriber_id' => $subscriber_id,
                'email' => $email,
                'action' => $action,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'old_score' => $old_score,
                'new_score' => $new_score,
                'reason' => $reason,
                'admin_user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
        );
    }
}
