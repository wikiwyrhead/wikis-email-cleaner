<?php
/**
 * Revalidation processor
 *
 * @package Wikis_Email_Cleaner
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Revalidation processor class
 */
class Wikis_Email_Cleaner_Revalidation_Processor {

    /**
     * Email validator instance
     *
     * @var Wikis_Email_Cleaner_Email_Validator
     */
    private $validator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->validator = new Wikis_Email_Cleaner_Email_Validator();
    }

    /**
     * Process revalidation queue
     *
     * @param int $batch_size Number of emails to process
     * @return array Processing results
     */
    public function process_queue( $batch_size = null ) {
        if ( ! Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'revalidation_enabled', false ) ) {
            return array(
                'success' => false,
                'message' => 'Revalidation system is disabled',
                'processed' => 0
            );
        }

        // Prevent concurrent processing
        if ( get_transient( 'wikis_email_cleaner_revalidation_processing' ) ) {
            return array(
                'success' => false,
                'message' => 'Revalidation already in progress',
                'processed' => 0
            );
        }

        set_transient( 'wikis_email_cleaner_revalidation_processing', true, HOUR_IN_SECONDS );

        try {
            $batch = Wikis_Email_Cleaner_Revalidation_Queue::get_next_batch( $batch_size );
            
            if ( empty( $batch ) ) {
                delete_transient( 'wikis_email_cleaner_revalidation_processing' );
                return array(
                    'success' => true,
                    'message' => 'No emails in queue to process',
                    'processed' => 0
                );
            }

            $results = array(
                'processed' => 0,
                'resubscribed' => 0,
                'kept_unsubscribed' => 0,
                'manual_review' => 0,
                'errors' => 0,
                'details' => array()
            );

            foreach ( $batch as $queue_item ) {
                $result = $this->process_single_email( $queue_item );
                $results['processed']++;
                $results['details'][] = $result;

                switch ( $result['action'] ) {
                    case 'resubscribed':
                        $results['resubscribed']++;
                        break;
                    case 'kept_unsubscribed':
                        $results['kept_unsubscribed']++;
                        break;
                    case 'manual_review':
                        $results['manual_review']++;
                        break;
                    case 'error':
                        $results['errors']++;
                        break;
                }

                // Update queue status
                Wikis_Email_Cleaner_Revalidation_Queue::update_status(
                    $queue_item->id,
                    $result['action'] === 'error' ? 'failed' : 'completed',
                    $result['notes']
                );
            }

            delete_transient( 'wikis_email_cleaner_revalidation_processing' );

            // Send notification if enabled
            if ( Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'send_notifications', true ) ) {
                $this->send_processing_notification( $results );
            }

            return array(
                'success' => true,
                'message' => sprintf( 'Processed %d emails', $results['processed'] ),
                'results' => $results
            );

        } catch ( Exception $e ) {
            delete_transient( 'wikis_email_cleaner_revalidation_processing' );
            
            return array(
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage(),
                'processed' => 0
            );
        }
    }

    /**
     * Process single email revalidation
     *
     * @param object $queue_item Queue item
     * @return array Processing result
     */
    private function process_single_email( $queue_item ) {
        try {
            // Check if domain is whitelisted
            $domain = substr( strrchr( $queue_item->email, '@' ), 1 );
            $whitelist = Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'whitelist_domains', array() );
            
            if ( in_array( strtolower( $domain ), array_map( 'strtolower', $whitelist ), true ) ) {
                $this->resubscribe_user( $queue_item->subscriber_id, 'whitelisted' );
                return array(
                    'email' => $queue_item->email,
                    'action' => 'resubscribed',
                    'reason' => 'whitelisted_domain',
                    'old_score' => $queue_item->original_score,
                    'new_score' => 100,
                    'notes' => 'Domain is whitelisted'
                );
            }

            // Get original validation details
            $original_validation = $this->get_original_validation_details( $queue_item->original_validation_id );

            // Perform new validation with improved algorithm
            $new_validation = $this->validator->validate_email( $queue_item->email, false );

            // Calculate improvement
            $score_improvement = $new_validation['score'] - $queue_item->original_score;
            $improvement_factors = $this->analyze_improvements( $original_validation, $new_validation );

            // Determine action based on improvement
            $action_result = $this->determine_action( $queue_item, $new_validation, $score_improvement );

            // Log the revalidation result
            $this->log_revalidation_result( $queue_item, $new_validation, $action_result, $improvement_factors );

            // Execute the action
            if ( $action_result['action'] === 'resubscribe' ) {
                $this->resubscribe_user( $queue_item->subscriber_id, 'revalidated' );
            }

            return array(
                'email' => $queue_item->email,
                'action' => $action_result['action'] === 'resubscribe' ? 'resubscribed' : $action_result['action'],
                'reason' => $action_result['reason'],
                'old_score' => $queue_item->original_score,
                'new_score' => $new_validation['score'],
                'improvement' => $score_improvement,
                'confidence' => $new_validation['confidence'] ?? 'unknown',
                'notes' => $action_result['notes']
            );

        } catch ( Exception $e ) {
            return array(
                'email' => $queue_item->email,
                'action' => 'error',
                'reason' => 'processing_error',
                'old_score' => $queue_item->original_score,
                'new_score' => 0,
                'notes' => 'Error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Determine action based on revalidation results
     *
     * @param object $queue_item      Queue item
     * @param array  $new_validation  New validation result
     * @param int    $score_improvement Score improvement
     * @return array Action to take
     */
    private function determine_action( $queue_item, $new_validation, $score_improvement ) {
        $score_threshold = Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'score_improvement_threshold', 15 );
        $manual_review_threshold = Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'manual_review_threshold', 10 );

        // Check if email now passes validation
        if ( $new_validation['is_valid'] && $score_improvement >= $score_threshold ) {
            return array(
                'action' => 'resubscribe',
                'reason' => 'significant_improvement',
                'notes' => sprintf( 'Score improved by %d points and now passes validation', $score_improvement )
            );
        }

        // Check for moderate improvement requiring manual review
        if ( $score_improvement >= $manual_review_threshold ) {
            return array(
                'action' => 'manual_review',
                'reason' => 'moderate_improvement',
                'notes' => sprintf( 'Score improved by %d points but requires manual review', $score_improvement )
            );
        }

        // Check for specific improvement factors that might warrant resubscription
        if ( $this->has_significant_improvement_factors( $new_validation ) ) {
            return array(
                'action' => 'manual_review',
                'reason' => 'qualitative_improvement',
                'notes' => 'Significant qualitative improvements detected'
            );
        }

        // No significant improvement
        return array(
            'action' => 'kept_unsubscribed',
            'reason' => 'insufficient_improvement',
            'notes' => sprintf( 'Score improvement of %d points insufficient for resubscription', $score_improvement )
        );
    }

    /**
     * Check for significant improvement factors
     *
     * @param array $validation Validation result
     * @return bool True if significant improvements found
     */
    private function has_significant_improvement_factors( $validation ) {
        $details = $validation['validation_details'] ?? array();

        // Check for trusted provider recognition
        if ( isset( $details['trusted_provider'] ) && $details['trusted_provider'] ) {
            return true;
        }

        // Check for valid corporate domain
        if ( isset( $details['corporate_domain'] ) && $details['corporate_domain'] ) {
            return true;
        }

        // Check for reduced error count
        if ( count( $validation['errors'] ?? array() ) === 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Resubscribe user in Newsletter plugin
     *
     * @param int    $subscriber_id Subscriber ID
     * @param string $reason        Reason for resubscription
     * @return bool Success
     */
    private function resubscribe_user( $subscriber_id, $reason ) {
        global $wpdb;
        $newsletter_table = $wpdb->prefix . 'newsletter';

        // Update status to confirmed
        $result = $wpdb->update(
            $newsletter_table,
            array( 'status' => 'C' ),
            array( 'id' => $subscriber_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $result ) {
            // Get user details for audit log
            $user = $wpdb->get_row(
                $wpdb->prepare( "SELECT email FROM $newsletter_table WHERE id = %d", $subscriber_id )
            );

            if ( $user ) {
                $this->log_audit_action(
                    $subscriber_id,
                    $user->email,
                    'resubscribed',
                    'U',
                    'C',
                    null,
                    null,
                    "Resubscribed due to: $reason"
                );
            }
        }

        return (bool) $result;
    }

    /**
     * Get original validation details
     *
     * @param int $validation_id Validation ID
     * @return array|null Original validation details
     */
    private function get_original_validation_details( $validation_id ) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'wikis_email_cleaner_logs';

        $validation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT validation_details FROM $logs_table WHERE id = %d",
                $validation_id
            )
        );

        return $validation ? json_decode( $validation->validation_details, true ) : null;
    }

    /**
     * Analyze improvements between old and new validation
     *
     * @param array $old_validation Old validation
     * @param array $new_validation New validation
     * @return array Improvement factors
     */
    private function analyze_improvements( $old_validation, $new_validation ) {
        $improvements = array();

        // Compare error counts
        $old_errors = count( $old_validation['errors'] ?? array() );
        $new_errors = count( $new_validation['errors'] ?? array() );
        if ( $new_errors < $old_errors ) {
            $improvements[] = sprintf( 'Errors reduced from %d to %d', $old_errors, $new_errors );
        }

        // Check for new positive indicators
        $new_details = $new_validation['validation_details'] ?? array();
        if ( isset( $new_details['trusted_provider'] ) && $new_details['trusted_provider'] ) {
            $improvements[] = 'Now recognized as trusted provider';
        }

        if ( isset( $new_details['corporate_domain'] ) && $new_details['corporate_domain'] ) {
            $improvements[] = 'Now recognized as corporate domain';
        }

        return $improvements;
    }

    /**
     * Log revalidation result
     *
     * @param object $queue_item         Queue item
     * @param array  $new_validation     New validation result
     * @param array  $action_result      Action result
     * @param array  $improvement_factors Improvement factors
     */
    private function log_revalidation_result( $queue_item, $new_validation, $action_result, $improvement_factors ) {
        global $wpdb;
        $results_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_results';

        $wpdb->insert(
            $results_table,
            array(
                'queue_id' => $queue_item->id,
                'subscriber_id' => $queue_item->subscriber_id,
                'email' => $queue_item->email,
                'old_score' => $queue_item->original_score,
                'new_score' => $new_validation['score'],
                'old_validation_details' => '', // Would need original details
                'new_validation_details' => json_encode( $new_validation ),
                'action_taken' => $action_result['action'] === 'resubscribe' ? 'resubscribed' : $action_result['action'],
                'confidence_level' => $new_validation['confidence'] ?? 'unknown',
                'improvement_factors' => json_encode( $improvement_factors ),
                'admin_notes' => $action_result['notes'],
                'processed_by' => 'system',
                'processed_at' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Send processing notification
     *
     * @param array $results Processing results
     */
    private function send_processing_notification( $results ) {
        $notification_email = Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'notification_email' );
        
        if ( empty( $notification_email ) ) {
            return;
        }

        $subject = sprintf( 
            '[%s] Email Revalidation Results - %d Processed', 
            get_bloginfo( 'name' ), 
            $results['processed'] 
        );

        $message = sprintf(
            "Email revalidation processing completed:\n\n" .
            "Processed: %d emails\n" .
            "Resubscribed: %d emails\n" .
            "Kept unsubscribed: %d emails\n" .
            "Manual review required: %d emails\n" .
            "Errors: %d emails\n\n" .
            "View detailed results in the WordPress admin dashboard.",
            $results['processed'],
            $results['resubscribed'],
            $results['kept_unsubscribed'],
            $results['manual_review'],
            $results['errors']
        );

        wp_mail( $notification_email, $subject, $message );
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
    private function log_audit_action( $subscriber_id, $email, $action, $old_status, $new_status, $old_score, $new_score, $reason ) {
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
