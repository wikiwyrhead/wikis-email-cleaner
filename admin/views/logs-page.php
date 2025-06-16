<?php
/**
 * Logs page template
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get plugin instance
$plugin = Wikis_Email_Cleaner::get_instance();
$logger = $plugin->logger;

// Handle pagination and filtering
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 50;
$offset = ( $current_page - 1 ) * $per_page;

// Filter parameters
$filter_email = isset( $_GET['filter_email'] ) ? sanitize_text_field( $_GET['filter_email'] ) : '';
$filter_valid = isset( $_GET['filter_valid'] ) ? sanitize_text_field( $_GET['filter_valid'] ) : '';
$filter_date_from = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( $_GET['filter_date_from'] ) : '';
$filter_date_to = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( $_GET['filter_date_to'] ) : '';

// Build filter args
$filter_args = array(
    'limit' => $per_page,
    'offset' => $offset,
    'orderby' => 'created_at',
    'order' => 'DESC'
);

if ( ! empty( $filter_email ) ) {
    $filter_args['email'] = $filter_email;
}

if ( $filter_valid !== '' ) {
    $filter_args['is_valid'] = $filter_valid === '1';
}

if ( ! empty( $filter_date_from ) ) {
    $filter_args['date_from'] = $filter_date_from . ' 00:00:00';
}

if ( ! empty( $filter_date_to ) ) {
    $filter_args['date_to'] = $filter_date_to . ' 23:59:59';
}

// Get logs and total count
$logs = $logger->get_logs( $filter_args );
$total_logs = $logger->get_log_count( $filter_args );
$total_pages = ceil( $total_logs / $per_page );

// Handle bulk actions
if ( isset( $_POST['action'] ) && $_POST['action'] === 'clear_logs' && wp_verify_nonce( $_POST['_wpnonce'], 'wikis_email_cleaner_logs' ) ) {
    $logger->clear_all_logs();
    echo '<div class="notice notice-success"><p>' . esc_html__( 'All logs have been cleared.', 'wikis-email-cleaner' ) . '</p></div>';
    $logs = array();
    $total_logs = 0;
}
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Email Validation Logs', 'wikis-email-cleaner' ); ?></h1>

    <!-- Filters -->
    <div class="wikis-logs-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wikis-email-cleaner-logs" />
            
            <div class="filter-row">
                <input type="text" name="filter_email" value="<?php echo esc_attr( $filter_email ); ?>" placeholder="<?php esc_attr_e( 'Filter by email...', 'wikis-email-cleaner' ); ?>" />
                
                <select name="filter_valid">
                    <option value=""><?php esc_html_e( 'All Validations', 'wikis-email-cleaner' ); ?></option>
                    <option value="1" <?php selected( $filter_valid, '1' ); ?>><?php esc_html_e( 'Valid Only', 'wikis-email-cleaner' ); ?></option>
                    <option value="0" <?php selected( $filter_valid, '0' ); ?>><?php esc_html_e( 'Invalid Only', 'wikis-email-cleaner' ); ?></option>
                </select>
                
                <input type="date" name="filter_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>" placeholder="<?php esc_attr_e( 'From date', 'wikis-email-cleaner' ); ?>" />
                
                <input type="date" name="filter_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>" placeholder="<?php esc_attr_e( 'To date', 'wikis-email-cleaner' ); ?>" />
                
                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'wikis-email-cleaner' ); ?>" />
                
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wikis-email-cleaner-logs' ) ); ?>" class="button"><?php esc_html_e( 'Clear Filters', 'wikis-email-cleaner' ); ?></a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="wikis-bulk-actions">
        <form method="post" action="" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all logs? This action cannot be undone.', 'wikis-email-cleaner' ); ?>');">
            <?php wp_nonce_field( 'wikis_email_cleaner_logs' ); ?>
            <input type="hidden" name="action" value="clear_logs" />
            <input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Clear All Logs', 'wikis-email-cleaner' ); ?>" />
        </form>
        
        <button id="wikis-export-logs" class="button button-secondary"><?php esc_html_e( 'Export to CSV', 'wikis-email-cleaner' ); ?></button>
    </div>

    <!-- Results Summary -->
    <div class="wikis-results-summary">
        <p>
            <?php
            printf(
                esc_html__( 'Showing %d of %d total logs', 'wikis-email-cleaner' ),
                count( $logs ),
                $total_logs
            );
            ?>
        </p>
    </div>

    <!-- Logs Table -->
    <?php if ( ! empty( $logs ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Email', 'wikis-email-cleaner' ); ?></th>
                    <th><?php esc_html_e( 'Valid', 'wikis-email-cleaner' ); ?></th>
                    <th><?php esc_html_e( 'Score', 'wikis-email-cleaner' ); ?></th>
                    <th><?php esc_html_e( 'Issues', 'wikis-email-cleaner' ); ?></th>
                    <th><?php esc_html_e( 'Action Taken', 'wikis-email-cleaner' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'wikis-email-cleaner' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $log['email'] ); ?></strong>
                            <?php if ( $log['subscriber_id'] > 0 ) : ?>
                                <br><small><?php printf( esc_html__( 'Subscriber ID: %d', 'wikis-email-cleaner' ), $log['subscriber_id'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $log['is_valid'] ) : ?>
                                <span class="wikis-status valid"><?php esc_html_e( 'Valid', 'wikis-email-cleaner' ); ?></span>
                            <?php else : ?>
                                <span class="wikis-status invalid"><?php esc_html_e( 'Invalid', 'wikis-email-cleaner' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="wikis-score score-<?php echo esc_attr( $log['score'] >= 70 ? 'high' : ( $log['score'] >= 40 ? 'medium' : 'low' ) ); ?>">
                                <?php echo esc_html( $log['score'] ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( ! empty( $log['errors'] ) && is_array( $log['errors'] ) ) : ?>
                                <div class="wikis-issues errors">
                                    <strong><?php esc_html_e( 'Errors:', 'wikis-email-cleaner' ); ?></strong>
                                    <ul>
                                        <?php foreach ( $log['errors'] as $error ) : ?>
                                            <li><?php echo esc_html( $error ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( ! empty( $log['warnings'] ) && is_array( $log['warnings'] ) ) : ?>
                                <div class="wikis-issues warnings">
                                    <strong><?php esc_html_e( 'Warnings:', 'wikis-email-cleaner' ); ?></strong>
                                    <ul>
                                        <?php foreach ( $log['warnings'] as $warning ) : ?>
                                            <li><?php echo esc_html( $warning ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( empty( $log['errors'] ) && empty( $log['warnings'] ) ) : ?>
                                <span class="wikis-no-issues"><?php esc_html_e( 'No issues', 'wikis-email-cleaner' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="wikis-action-<?php echo esc_attr( $log['action_taken'] ); ?>">
                                <?php echo esc_html( ucwords( str_replace( '_', ' ', $log['action_taken'] ) ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log['created_at'] ) ) ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="wikis-pagination">
                <?php
                $pagination_args = array(
                    'base' => add_query_arg( 'paged', '%#%' ),
                    'format' => '',
                    'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'wikis-email-cleaner' ),
                    'next_text' => esc_html__( 'Next', 'wikis-email-cleaner' ) . ' &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'show_all' => false,
                    'end_size' => 1,
                    'mid_size' => 2,
                    'type' => 'plain'
                );

                echo paginate_links( $pagination_args );
                ?>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <div class="wikis-no-logs">
            <p><?php esc_html_e( 'No validation logs found.', 'wikis-email-cleaner' ); ?></p>
            <?php if ( empty( $filter_email ) && empty( $filter_valid ) && empty( $filter_date_from ) && empty( $filter_date_to ) ) : ?>
                <p><?php esc_html_e( 'Run an email scan to see validation results here.', 'wikis-email-cleaner' ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Try adjusting your filters to see more results.', 'wikis-email-cleaner' ); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.wikis-logs-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-row input[type="text"],
.filter-row input[type="date"],
.filter-row select {
    min-width: 150px;
}

.wikis-bulk-actions {
    margin-bottom: 15px;
    display: flex;
    gap: 10px;
}

.wikis-results-summary {
    margin-bottom: 10px;
    font-style: italic;
}

.wikis-status {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.wikis-status.valid {
    background: #d4edda;
    color: #155724;
}

.wikis-status.invalid {
    background: #f8d7da;
    color: #721c24;
}

.wikis-score {
    padding: 3px 8px;
    border-radius: 3px;
    font-weight: bold;
}

.wikis-score.score-high {
    background: #d4edda;
    color: #155724;
}

.wikis-score.score-medium {
    background: #fff3cd;
    color: #856404;
}

.wikis-score.score-low {
    background: #f8d7da;
    color: #721c24;
}

.wikis-issues {
    margin-bottom: 10px;
}

.wikis-issues ul {
    margin: 5px 0 0 20px;
    font-size: 12px;
}

.wikis-issues.errors {
    color: #721c24;
}

.wikis-issues.warnings {
    color: #856404;
}

.wikis-no-issues {
    color: #6c757d;
    font-style: italic;
}

.wikis-action-unsubscribed {
    color: #dc3545;
    font-weight: bold;
}

.wikis-action-none {
    color: #6c757d;
}

.wikis-pagination {
    margin-top: 20px;
    text-align: center;
}

.wikis-no-logs {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 40px;
    text-align: center;
    color: #6c757d;
}
</style>
