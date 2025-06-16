<?php
/**
 * Main admin page template
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
$scheduler = $plugin->scheduler;
$newsletter_integration = $plugin->newsletter_integration;

// Get statistics
$stats = $logger->get_statistics();
$scan_summaries = $logger->get_scan_summaries( 5 );
$next_scan = $scheduler->get_next_scheduled_time();
$is_scanning = $scheduler->is_scanning();

// Get Newsletter subscriber count
$total_subscribers = $newsletter_integration->get_subscriber_count( 'C' );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Email Cleaner Dashboard', 'wikis-email-cleaner' ); ?></h1>

    <?php if ( $is_scanning ) : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e( 'Email scan is currently running...', 'wikis-email-cleaner' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="wikis-email-cleaner-dashboard">
        <!-- Statistics Cards -->
        <div class="wikis-stats-grid">
            <div class="wikis-stat-card">
                <h3><?php esc_html_e( 'Total Subscribers', 'wikis-email-cleaner' ); ?></h3>
                <div class="stat-number"><?php echo esc_html( number_format( (int) $total_subscribers ) ); ?></div>
            </div>

            <div class="wikis-stat-card">
                <h3><?php esc_html_e( 'Emails Validated', 'wikis-email-cleaner' ); ?></h3>
                <div class="stat-number"><?php echo esc_html( number_format( (int) $stats['total_logs'] ) ); ?></div>
            </div>

            <div class="wikis-stat-card">
                <h3><?php esc_html_e( 'Valid Emails', 'wikis-email-cleaner' ); ?></h3>
                <div class="stat-number valid"><?php echo esc_html( number_format( (int) $stats['valid_emails'] ) ); ?></div>
            </div>

            <div class="wikis-stat-card">
                <h3><?php esc_html_e( 'Invalid Emails', 'wikis-email-cleaner' ); ?></h3>
                <div class="stat-number invalid"><?php echo esc_html( number_format( (int) $stats['invalid_emails'] ) ); ?></div>
            </div>

            <div class="wikis-stat-card">
                <h3><?php esc_html_e( 'Average Score', 'wikis-email-cleaner' ); ?></h3>
                <div class="stat-number"><?php echo esc_html( number_format( (float) $stats['average_score'], 1 ) ); ?></div>
            </div>

            <div class="wikis-stat-card">
                <h3><?php esc_html_e( 'Recent Activity', 'wikis-email-cleaner' ); ?></h3>
                <div class="stat-number"><?php echo esc_html( number_format( (int) $stats['recent_scans'] ) ); ?></div>
                <small><?php esc_html_e( 'Last 7 days', 'wikis-email-cleaner' ); ?></small>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="wikis-actions">
            <h2><?php esc_html_e( 'Actions', 'wikis-email-cleaner' ); ?></h2>
            
            <div class="wikis-action-buttons">
                <button id="wikis-scan-emails" class="button button-primary" <?php echo $is_scanning ? 'disabled' : ''; ?>>
                    <?php esc_html_e( 'Scan All Emails', 'wikis-email-cleaner' ); ?>
                </button>

                <button id="wikis-export-results" class="button button-secondary">
                    <?php esc_html_e( 'Export Results', 'wikis-email-cleaner' ); ?>
                </button>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wikis-email-cleaner-settings' ) ); ?>" class="button">
                    <?php esc_html_e( 'Settings', 'wikis-email-cleaner' ); ?>
                </a>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wikis-email-cleaner-logs' ) ); ?>" class="button">
                    <?php esc_html_e( 'View Logs', 'wikis-email-cleaner' ); ?>
                </a>
            </div>
        </div>

        <!-- Schedule Information -->
        <div class="wikis-schedule-info">
            <h2><?php esc_html_e( 'Schedule Information', 'wikis-email-cleaner' ); ?></h2>
            
            <?php if ( $next_scan ) : ?>
                <p>
                    <?php esc_html_e( 'Next scheduled scan:', 'wikis-email-cleaner' ); ?>
                    <strong><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $next_scan ) ); ?></strong>
                </p>
            <?php else : ?>
                <p><?php esc_html_e( 'No scheduled scans. Enable automatic cleaning in settings.', 'wikis-email-cleaner' ); ?></p>
            <?php endif; ?>
        </div>

        <!-- Recent Scan Results -->
        <div class="wikis-recent-scans">
            <h2><?php esc_html_e( 'Recent Scan Results', 'wikis-email-cleaner' ); ?></h2>
            
            <?php if ( ! empty( $scan_summaries ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'wikis-email-cleaner' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'wikis-email-cleaner' ); ?></th>
                            <th><?php esc_html_e( 'Processed', 'wikis-email-cleaner' ); ?></th>
                            <th><?php esc_html_e( 'Invalid', 'wikis-email-cleaner' ); ?></th>
                            <th><?php esc_html_e( 'Unsubscribed', 'wikis-email-cleaner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $scan_summaries as $summary ) : ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $summary['scan_date'] ) ) ); ?></td>
                                <td><?php echo esc_html( ucfirst( $summary['scan_type'] ) ); ?></td>
                                <td><?php echo esc_html( number_format( $summary['total_processed'] ) ); ?></td>
                                <td><?php echo esc_html( number_format( $summary['total_invalid'] ) ); ?></td>
                                <td><?php echo esc_html( number_format( $summary['total_unsubscribed'] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No scan results available yet.', 'wikis-email-cleaner' ); ?></p>
            <?php endif; ?>
        </div>

        <!-- Progress Bar (hidden by default) -->
        <div id="wikis-scan-progress" class="wikis-progress-container" style="display: none;">
            <h3><?php esc_html_e( 'Scanning Progress', 'wikis-email-cleaner' ); ?></h3>
            <div class="wikis-progress-bar">
                <div class="wikis-progress-fill"></div>
            </div>
            <div class="wikis-progress-text">
                <span id="wikis-progress-current">0</span> / <span id="wikis-progress-total">0</span>
                <?php esc_html_e( 'emails processed', 'wikis-email-cleaner' ); ?>
            </div>
        </div>

        <!-- Results Container -->
        <div id="wikis-scan-results" class="wikis-results-container" style="display: none;">
            <h3><?php esc_html_e( 'Scan Results', 'wikis-email-cleaner' ); ?></h3>
            <div id="wikis-results-content"></div>
        </div>

        <!-- Donation Section -->
        <?php if ( ! get_user_meta( get_current_user_id(), 'wikis_email_cleaner_hide_donation', true ) ) : ?>
        <div id="wikis-donation-section" class="wikis-donation-container">
            <div class="wikis-donation-content">
                <button type="button" class="wikis-donation-dismiss" onclick="wikisDismissDonation()" title="<?php esc_attr_e( 'Hide this message', 'wikis-email-cleaner' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>

                <div class="wikis-donation-header">
                    <span class="wikis-heart">❤️</span>
                    <h3><?php esc_html_e( 'Support the Development', 'wikis-email-cleaner' ); ?></h3>
                </div>

                <p class="wikis-donation-message">
                    <?php esc_html_e( 'If you find this plugin useful, please consider making a donation to support continued development and maintenance. Your contribution helps keep this plugin updated and compatible with the latest WordPress versions.', 'wikis-email-cleaner' ); ?>
                </p>

                <div class="wikis-donation-actions">
                    <a href="https://www.paypal.com/paypalme/arnelborresgo"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="wikis-paypal-button">
                        <?php esc_html_e( 'Donate with PayPal', 'wikis-email-cleaner' ); ?>
                    </a>

                    <p class="wikis-paypal-email">
                        <small><?php esc_html_e( 'PayPal.me:', 'wikis-email-cleaner' ); ?> <code>paypal.me/arnelborresgo</code></small>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.wikis-email-cleaner-dashboard {
    max-width: 1200px;
}

.wikis-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.wikis-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wikis-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #23282d;
    margin-bottom: 5px;
}

.stat-number.valid {
    color: #46b450;
}

.stat-number.invalid {
    color: #dc3232;
}

.wikis-actions {
    margin-bottom: 30px;
}

.wikis-action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.wikis-schedule-info,
.wikis-recent-scans {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.wikis-progress-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.wikis-progress-bar {
    width: 100%;
    height: 20px;
    background: #f1f1f1;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.wikis-progress-fill {
    height: 100%;
    background: #0073aa;
    width: 0%;
    transition: width 0.3s ease;
}

.wikis-progress-text {
    text-align: center;
    font-weight: bold;
}

.wikis-results-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}
</style>
