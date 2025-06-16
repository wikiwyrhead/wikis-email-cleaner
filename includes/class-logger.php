<?php
/**
 * Logger class for email cleaning activities
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logger class
 */
class Wikis_Email_Cleaner_Logger {

    /**
     * Log table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wikis_email_cleaner_logs';
    }

    /**
     * Log email validation result
     *
     * @param int   $subscriber_id Subscriber ID
     * @param array $validation    Validation result
     */
    public function log_validation( $subscriber_id, $validation ) {
        global $wpdb;

        $wpdb->insert(
            $this->table_name,
            array(
                'subscriber_id' => $subscriber_id,
                'email' => $validation['email'],
                'is_valid' => $validation['is_valid'] ? 1 : 0,
                'score' => $validation['score'],
                'errors' => maybe_serialize( $validation['errors'] ),
                'warnings' => maybe_serialize( $validation['warnings'] ),
                'action_taken' => $validation['is_valid'] ? 'none' : 'unsubscribed',
                'created_at' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Log scan summary
     *
     * @param array $summary Scan summary data
     */
    public function log_scan_summary( $summary ) {
        global $wpdb;

        $summary_table = $wpdb->prefix . 'wikis_email_cleaner_scan_summary';

        $wpdb->insert(
            $summary_table,
            array(
                'total_processed' => $summary['total_processed'],
                'total_invalid' => $summary['total_invalid'],
                'total_unsubscribed' => $summary['total_unsubscribed'],
                'scan_date' => $summary['scan_date'],
                'scan_type' => 'scheduled'
            ),
            array( '%d', '%d', '%d', '%s', '%s' )
        );
    }

    /**
     * Get validation logs
     *
     * @param array $args Query arguments
     * @return array Log entries
     */
    public function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'email' => '',
            'is_valid' => null,
            'date_from' => '',
            'date_to' => ''
        );

        $args = wp_parse_args( $args, $defaults );

        $where_clauses = array();
        $where_values = array();

        // Filter by email
        if ( ! empty( $args['email'] ) ) {
            $where_clauses[] = 'email LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( $args['email'] ) . '%';
        }

        // Filter by validity
        if ( $args['is_valid'] !== null ) {
            $where_clauses[] = 'is_valid = %d';
            $where_values[] = $args['is_valid'] ? 1 : 0;
        }

        // Filter by date range
        if ( ! empty( $args['date_from'] ) ) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $order_sql = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby( $args['orderby'] ),
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $limit_sql = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );

        $query = "SELECT * FROM {$this->table_name} {$where_sql} {$order_sql} {$limit_sql}";

        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $query, $where_values );
        }

        $results = $wpdb->get_results( $query, ARRAY_A );

        // Unserialize data
        foreach ( $results as &$result ) {
            $result['errors'] = maybe_unserialize( $result['errors'] );
            $result['warnings'] = maybe_unserialize( $result['warnings'] );
        }

        return $results;
    }

    /**
     * Get scan summaries
     *
     * @param int $limit Number of summaries to retrieve
     * @return array Scan summaries
     */
    public function get_scan_summaries( $limit = 10 ) {
        global $wpdb;

        $summary_table = $wpdb->prefix . 'wikis_email_cleaner_scan_summary';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$summary_table} ORDER BY scan_date DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get log statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;

        $stats = array();

        // Check if table exists first
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) !== $this->table_name ) {
            return array(
                'total_logs' => 0,
                'valid_emails' => 0,
                'invalid_emails' => 0,
                'recent_scans' => 0,
                'average_score' => 0
            );
        }

        // Total logs
        $stats['total_logs'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

        // Valid vs invalid emails
        $stats['valid_emails'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE is_valid = 1" );
        $stats['invalid_emails'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE is_valid = 0" );

        // Recent activity (last 7 days)
        $week_ago = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        $stats['recent_scans'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
                $week_ago
            )
        );

        // Average score
        $average_score = $wpdb->get_var( "SELECT AVG(score) FROM {$this->table_name}" );
        $stats['average_score'] = $average_score !== null ? (float) $average_score : 0.0;

        return $stats;
    }

    /**
     * Export logs to CSV
     *
     * @param array $args Export arguments
     * @return string CSV file URL
     */
    public function export_to_csv( $args = array() ) {
        $logs = $this->get_logs( array_merge( $args, array( 'limit' => 10000 ) ) );

        $upload_dir = wp_upload_dir();
        $filename = 'email-cleaner-logs-' . date( 'Y-m-d-H-i-s' ) . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;

        $file = fopen( $filepath, 'w' );

        // CSV headers
        $headers = array(
            'Subscriber ID',
            'Email',
            'Valid',
            'Score',
            'Errors',
            'Warnings',
            'Action Taken',
            'Date'
        );

        fputcsv( $file, $headers );

        // CSV data
        foreach ( $logs as $log ) {
            $row = array(
                $log['subscriber_id'],
                $log['email'],
                $log['is_valid'] ? 'Yes' : 'No',
                $log['score'],
                is_array( $log['errors'] ) ? implode( '; ', $log['errors'] ) : '',
                is_array( $log['warnings'] ) ? implode( '; ', $log['warnings'] ) : '',
                $log['action_taken'],
                $log['created_at']
            );

            fputcsv( $file, $row );
        }

        fclose( $file );

        return $upload_dir['url'] . '/' . $filename;
    }

    /**
     * Clean up old logs
     *
     * @param int $days Number of days to keep logs (default: 30)
     */
    public function cleanup_old_logs( $days = 30 ) {
        global $wpdb;

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // Delete old validation logs
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );

        // Delete old scan summaries
        $summary_table = $wpdb->prefix . 'wikis_email_cleaner_scan_summary';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$summary_table} WHERE scan_date < %s",
                $cutoff_date
            )
        );
    }

    /**
     * Clear all logs
     */
    public function clear_all_logs() {
        global $wpdb;

        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

        $summary_table = $wpdb->prefix . 'wikis_email_cleaner_scan_summary';
        $wpdb->query( "TRUNCATE TABLE {$summary_table}" );
    }

    /**
     * Get log count
     *
     * @param array $args Query arguments
     * @return int Log count
     */
    public function get_log_count( $args = array() ) {
        global $wpdb;

        $where_clauses = array();
        $where_values = array();

        // Filter by email
        if ( ! empty( $args['email'] ) ) {
            $where_clauses[] = 'email LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( $args['email'] ) . '%';
        }

        // Filter by validity
        if ( isset( $args['is_valid'] ) && $args['is_valid'] !== null ) {
            $where_clauses[] = 'is_valid = %d';
            $where_values[] = $args['is_valid'] ? 1 : 0;
        }

        // Filter by date range
        if ( ! empty( $args['date_from'] ) ) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $query = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";

        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $query, $where_values );
        }

        return (int) $wpdb->get_var( $query );
    }
}
