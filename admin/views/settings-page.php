<?php
/**
 * Settings page template
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle form submission
if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wikis_email_cleaner_settings' ) ) {
    $settings = array();
    
    // General settings
    $settings['enable_auto_clean'] = isset( $_POST['wikis_email_cleaner_settings']['enable_auto_clean'] );
    $settings['minimum_score'] = intval( $_POST['wikis_email_cleaner_settings']['minimum_score'] );
    $settings['schedule_frequency'] = sanitize_text_field( $_POST['wikis_email_cleaner_settings']['schedule_frequency'] );
    
    // Validation settings
    $settings['enable_deep_validation'] = isset( $_POST['wikis_email_cleaner_settings']['enable_deep_validation'] );
    $settings['validate_on_subscription'] = isset( $_POST['wikis_email_cleaner_settings']['validate_on_subscription'] );
    $settings['subscription_minimum_score'] = intval( $_POST['wikis_email_cleaner_settings']['subscription_minimum_score'] );
    $settings['deep_validate_on_confirm'] = isset( $_POST['wikis_email_cleaner_settings']['deep_validate_on_confirm'] );
    $settings['handle_bounces_complaints'] = isset( $_POST['wikis_email_cleaner_settings']['handle_bounces_complaints'] );
    
    // Notification settings
    $settings['send_notifications'] = isset( $_POST['wikis_email_cleaner_settings']['send_notifications'] );
    $settings['notification_email'] = sanitize_email( $_POST['wikis_email_cleaner_settings']['notification_email'] );
    
    // Advanced settings
    $settings['log_retention_days'] = intval( $_POST['wikis_email_cleaner_settings']['log_retention_days'] );
    $settings['batch_size'] = intval( $_POST['wikis_email_cleaner_settings']['batch_size'] );
    $settings['preserve_data_on_uninstall'] = isset( $_POST['wikis_email_cleaner_settings']['preserve_data_on_uninstall'] );
    
    update_option( 'wikis_email_cleaner_settings', $settings );
    
    // Update scheduler
    $plugin = Wikis_Email_Cleaner::get_instance();
    $plugin->scheduler->schedule_next_scan();
    
    echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully!', 'wikis-email-cleaner' ) . '</p></div>';
}

// Get current settings
$settings = get_option( 'wikis_email_cleaner_settings', array() );
$defaults = array(
    'enable_auto_clean' => false,
    'minimum_score' => 50,
    'schedule_frequency' => 'daily',
    'enable_deep_validation' => false,
    'validate_on_subscription' => true,
    'subscription_minimum_score' => 30,
    'deep_validate_on_confirm' => false,
    'handle_bounces_complaints' => true,
    'send_notifications' => false,
    'notification_email' => get_option( 'admin_email' ),
    'log_retention_days' => 30,
    'batch_size' => 100,
    'preserve_data_on_uninstall' => false
);

$settings = wp_parse_args( $settings, $defaults );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Email Cleaner Settings', 'wikis-email-cleaner' ); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field( 'wikis_email_cleaner_settings' ); ?>
        
        <div class="wikis-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'wikis-email-cleaner' ); ?></a>
                <a href="#validation" class="nav-tab"><?php esc_html_e( 'Validation', 'wikis-email-cleaner' ); ?></a>
                <a href="#notifications" class="nav-tab"><?php esc_html_e( 'Notifications', 'wikis-email-cleaner' ); ?></a>
                <a href="#advanced" class="nav-tab"><?php esc_html_e( 'Advanced', 'wikis-email-cleaner' ); ?></a>
            </nav>

            <!-- General Settings -->
            <div id="general" class="tab-content active">
                <h2><?php esc_html_e( 'General Settings', 'wikis-email-cleaner' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Automatic Cleaning', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wikis_email_cleaner_settings[enable_auto_clean]" value="1" <?php checked( $settings['enable_auto_clean'] ); ?> />
                                <?php esc_html_e( 'Automatically scan and clean invalid emails', 'wikis-email-cleaner' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'When enabled, the plugin will automatically scan and unsubscribe invalid emails based on the schedule.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Minimum Valid Score', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <input type="number" name="wikis_email_cleaner_settings[minimum_score]" value="<?php echo esc_attr( $settings['minimum_score'] ); ?>" min="0" max="100" />
                            <p class="description"><?php esc_html_e( 'Emails with scores below this value will be considered invalid (0-100).', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Scan Frequency', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <select name="wikis_email_cleaner_settings[schedule_frequency]">
                                <option value="hourly" <?php selected( $settings['schedule_frequency'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'wikis-email-cleaner' ); ?></option>
                                <option value="daily" <?php selected( $settings['schedule_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'wikis-email-cleaner' ); ?></option>
                                <option value="weekly" <?php selected( $settings['schedule_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'wikis-email-cleaner' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How often to run automatic email cleaning scans.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Validation Settings -->
            <div id="validation" class="tab-content">
                <h2><?php esc_html_e( 'Validation Settings', 'wikis-email-cleaner' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Deep Validation', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wikis_email_cleaner_settings[enable_deep_validation]" value="1" <?php checked( $settings['enable_deep_validation'] ); ?> />
                                <?php esc_html_e( 'Perform SMTP and MX record validation', 'wikis-email-cleaner' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Deep validation is more accurate but slower. Use for scheduled scans.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Validate on Subscription', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wikis_email_cleaner_settings[validate_on_subscription]" value="1" <?php checked( $settings['validate_on_subscription'] ); ?> />
                                <?php esc_html_e( 'Validate emails during subscription process', 'wikis-email-cleaner' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Reject invalid emails during the subscription process.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Subscription Minimum Score', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <input type="number" name="wikis_email_cleaner_settings[subscription_minimum_score]" value="<?php echo esc_attr( $settings['subscription_minimum_score'] ); ?>" min="0" max="100" />
                            <p class="description"><?php esc_html_e( 'Minimum score required for new subscriptions (lower than main minimum score).', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Deep Validate on Confirmation', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wikis_email_cleaner_settings[deep_validate_on_confirm]" value="1" <?php checked( $settings['deep_validate_on_confirm'] ); ?> />
                                <?php esc_html_e( 'Perform deep validation when users confirm subscription', 'wikis-email-cleaner' ); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Handle Bounces & Complaints', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wikis_email_cleaner_settings[handle_bounces_complaints]" value="1" <?php checked( $settings['handle_bounces_complaints'] ); ?> />
                                <?php esc_html_e( 'Automatically process bounced emails and spam complaints', 'wikis-email-cleaner' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Notification Settings -->
            <div id="notifications" class="tab-content">
                <h2><?php esc_html_e( 'Notification Settings', 'wikis-email-cleaner' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Send Email Notifications', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wikis_email_cleaner_settings[send_notifications]" value="1" <?php checked( $settings['send_notifications'] ); ?> />
                                <?php esc_html_e( 'Send email reports after scans', 'wikis-email-cleaner' ); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Notification Email', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <input type="email" name="wikis_email_cleaner_settings[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Email address to receive scan reports and notifications.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Advanced Settings -->
            <div id="advanced" class="tab-content">
                <h2><?php esc_html_e( 'Advanced Settings', 'wikis-email-cleaner' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Log Retention (Days)', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <input type="number" name="wikis_email_cleaner_settings[log_retention_days]" value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" min="1" max="365" />
                            <p class="description"><?php esc_html_e( 'Number of days to keep validation logs before automatic cleanup.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Batch Size', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <input type="number" name="wikis_email_cleaner_settings[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" min="10" max="1000" />
                            <p class="description"><?php esc_html_e( 'Number of emails to process in each batch during scans.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Preserve Data on Uninstall', 'wikis-email-cleaner' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wikis_email_cleaner_settings[preserve_data_on_uninstall]" value="1" <?php checked( $settings['preserve_data_on_uninstall'] ); ?> />
                                <?php esc_html_e( 'Keep plugin data when uninstalling', 'wikis-email-cleaner' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'If enabled, logs and settings will be preserved when the plugin is uninstalled.', 'wikis-email-cleaner' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>

    <!-- Donation Section (Compact Version for Settings Page) -->
    <?php if ( ! get_user_meta( get_current_user_id(), 'wikis_email_cleaner_hide_donation', true ) ) : ?>
    <div class="wikis-donation-container" style="margin-top: 30px;">
        <div class="wikis-donation-content">
            <button type="button" class="wikis-donation-dismiss" onclick="wikisDismissDonation()" title="<?php esc_attr_e( 'Hide this message', 'wikis-email-cleaner' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>

            <div class="wikis-donation-header">
                <span class="wikis-heart">❤️</span>
                <h3><?php esc_html_e( 'Support the Development', 'wikis-email-cleaner' ); ?></h3>
            </div>

            <p class="wikis-donation-message">
                <?php esc_html_e( 'If you find this plugin useful, please consider making a donation to support continued development and maintenance.', 'wikis-email-cleaner' ); ?>
            </p>

            <div class="wikis-donation-actions">
                <a href="https://www.paypal.com/paypalme/arnelborresgo"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="wikis-paypal-button">
                    <?php esc_html_e( 'Donate with PayPal', 'wikis-email-cleaner' ); ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.wikis-settings-tabs {
    margin-top: 20px;
}

.tab-content {
    display: none;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
}

.tab-content.active {
    display: block;
}

.nav-tab-wrapper {
    border-bottom: 1px solid #ccd0d4;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
});
</script>
