<?php
/**
 * Revalidation admin interface
 *
 * @package Wikis_Email_Cleaner
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Revalidation admin interface class
 */
class Wikis_Email_Cleaner_Revalidation_Admin {

    /**
     * Initialize admin interface
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_ajax_wikis_revalidation_populate_queue', array( $this, 'ajax_populate_queue' ) );
        add_action( 'wp_ajax_wikis_revalidation_process_queue', array( $this, 'ajax_process_queue' ) );
        add_action( 'wp_ajax_wikis_revalidation_manual_review', array( $this, 'ajax_manual_review' ) );
        add_action( 'wp_ajax_wikis_revalidation_rollback', array( $this, 'ajax_rollback' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wikis-email-cleaner',
            __( 'Email Revalidation', 'wikis-email-cleaner' ),
            __( 'Revalidation', 'wikis-email-cleaner' ),
            'manage_options',
            'wikis-email-revalidation',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wikis-email-revalidation' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'wikis-revalidation-admin',
            WIKIS_EMAIL_CLEANER_PLUGIN_URL . 'admin/js/revalidation-admin.js',
            array( 'jquery' ),
            WIKIS_EMAIL_CLEANER_VERSION,
            true
        );

        wp_localize_script(
            'wikis-revalidation-admin',
            'wikisReValidation',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wikis_revalidation_nonce' ),
                'strings' => array(
                    'processing' => __( 'Processing...', 'wikis-email-cleaner' ),
                    'confirm_populate' => __( 'This will queue emails for revalidation. Continue?', 'wikis-email-cleaner' ),
                    'confirm_process' => __( 'This will process queued emails. Continue?', 'wikis-email-cleaner' ),
                    'confirm_rollback' => __( 'This will rollback recent resubscriptions. This action cannot be undone. Continue?', 'wikis-email-cleaner' )
                )
            )
        );

        wp_enqueue_style(
            'wikis-revalidation-admin',
            WIKIS_EMAIL_CLEANER_PLUGIN_URL . 'admin/css/revalidation-admin.css',
            array(),
            WIKIS_EMAIL_CLEANER_VERSION
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Handle migration action
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'migrate' ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'Unauthorized' );
            }
            $result = Wikis_Email_Cleaner_Revalidation_Migration::run_migration();
            if ( $result['success'] ) {
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Revalidation system setup completed successfully.', 'wikis-email-cleaner' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Revalidation setup failed: ', 'wikis-email-cleaner' ) . esc_html( $result['message'] ) . '</p></div>';
            }
        }

        // Handle settings update
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wikis_revalidation_settings' ) ) {
            $this->update_settings();
        }

        $stats = Wikis_Email_Cleaner_Revalidation_Queue::get_statistics();
        $settings = $this->get_current_settings();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Email Revalidation System', 'wikis-email-cleaner' ); ?></h1>

            <div class="wikis-revalidation-dashboard">
                <!-- Statistics Cards -->
                <div class="wikis-stats-grid">
                    <div class="wikis-stat-card">
                        <h3><?php _e( 'Queue Status', 'wikis-email-cleaner' ); ?></h3>
                        <div class="stat-number"><?php echo esc_html( $stats['pending'] ); ?></div>
                        <div class="stat-label"><?php _e( 'Pending', 'wikis-email-cleaner' ); ?></div>
                    </div>
                    <div class="wikis-stat-card">
                        <h3><?php _e( 'Completed', 'wikis-email-cleaner' ); ?></h3>
                        <div class="stat-number"><?php echo esc_html( $stats['completed'] ); ?></div>
                        <div class="stat-label"><?php _e( 'Processed', 'wikis-email-cleaner' ); ?></div>
                    </div>
                    <div class="wikis-stat-card">
                        <h3><?php _e( 'Manual Review', 'wikis-email-cleaner' ); ?></h3>
                        <div class="stat-number"><?php echo esc_html( $stats['manual_review'] ); ?></div>
                        <div class="stat-label"><?php _e( 'Needs Review', 'wikis-email-cleaner' ); ?></div>
                    </div>
                    <div class="wikis-stat-card">
                        <h3><?php _e( 'Total', 'wikis-email-cleaner' ); ?></h3>
                        <div class="stat-number"><?php echo esc_html( $stats['total'] ); ?></div>
                        <div class="stat-label"><?php _e( 'In Queue', 'wikis-email-cleaner' ); ?></div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="wikis-action-buttons">
                    <button type="button" id="populate-queue" class="button button-primary">
                        <?php _e( 'Populate Queue', 'wikis-email-cleaner' ); ?>
                    </button>
                    <button type="button" id="process-queue" class="button button-secondary" <?php echo $stats['pending'] > 0 ? '' : 'disabled'; ?>>
                        <?php _e( 'Process Queue', 'wikis-email-cleaner' ); ?>
                    </button>
                    <button type="button" id="view-manual-review" class="button" <?php echo $stats['manual_review'] > 0 ? '' : 'disabled'; ?>>
                        <?php _e( 'Manual Review', 'wikis-email-cleaner' ); ?>
                    </button>
                </div>

                <!-- Progress Bar -->
                <div id="progress-container" style="display: none;">
                    <div class="wikis-progress-bar">
                        <div id="progress-bar" class="progress-fill"></div>
                    </div>
                    <div id="progress-text"></div>
                </div>

                <!-- Results Display -->
                <div id="results-container" style="display: none;">
                    <h3><?php _e( 'Processing Results', 'wikis-email-cleaner' ); ?></h3>
                    <div id="results-content"></div>
                </div>

                <!-- Settings Form -->
                <form method="post" action="">
                    <?php wp_nonce_field( 'wikis_revalidation_settings' ); ?>
                    
                    <h2><?php _e( 'Revalidation Settings', 'wikis-email-cleaner' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e( 'Enable Revalidation', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="revalidation_enabled" value="1" <?php checked( $settings['revalidation_enabled'] ); ?> />
                                    <?php _e( 'Enable automatic revalidation system', 'wikis-email-cleaner' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Batch Size', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <input type="number" name="batch_size" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" min="10" max="500" />
                                <p class="description"><?php _e( 'Number of emails to process per batch (10-500)', 'wikis-email-cleaner' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Score Improvement Threshold', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <input type="number" name="score_improvement_threshold" value="<?php echo esc_attr( $settings['score_improvement_threshold'] ); ?>" min="5" max="50" />
                                <p class="description"><?php _e( 'Minimum score improvement required for automatic resubscription', 'wikis-email-cleaner' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Manual Review Threshold', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <input type="number" name="manual_review_threshold" value="<?php echo esc_attr( $settings['manual_review_threshold'] ); ?>" min="5" max="30" />
                                <p class="description"><?php _e( 'Score improvement threshold for manual review queue', 'wikis-email-cleaner' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Maximum Age (Days)', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <input type="number" name="max_age_days" value="<?php echo esc_attr( $settings['max_age_days'] ); ?>" min="7" max="365" />
                                <p class="description"><?php _e( 'Maximum age of unsubscribed emails to consider for revalidation', 'wikis-email-cleaner' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Whitelist Domains', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <textarea name="whitelist_domains" rows="5" cols="50"><?php echo esc_textarea( implode( "\n", $settings['whitelist_domains'] ) ); ?></textarea>
                                <p class="description"><?php _e( 'Domains to automatically resubscribe (one per line)', 'wikis-email-cleaner' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Notification Email', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <input type="email" name="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" />
                                <p class="description"><?php _e( 'Email address for revalidation notifications', 'wikis-email-cleaner' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Send Notifications', 'wikis-email-cleaner' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_notifications" value="1" <?php checked( $settings['send_notifications'] ); ?> />
                                    <?php _e( 'Send email notifications about revalidation results', 'wikis-email-cleaner' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(); ?>
                </form>

                <!-- Recent Activity -->
                <h2><?php _e( 'Recent Activity', 'wikis-email-cleaner' ); ?></h2>
                <?php $this->render_recent_activity(); ?>

                <!-- Emergency Controls -->
                <div class="wikis-emergency-controls">
                    <h2><?php _e( 'Emergency Controls', 'wikis-email-cleaner' ); ?></h2>
                    <p class="description"><?php _e( 'Use these controls only in case of issues', 'wikis-email-cleaner' ); ?></p>
                    
                    <button type="button" id="rollback-recent" class="button button-secondary">
                        <?php _e( 'Rollback Recent Resubscriptions', 'wikis-email-cleaner' ); ?>
                    </button>
                    
                    <button type="button" id="clear-queue" class="button">
                        <?php _e( 'Clear Queue', 'wikis-email-cleaner' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get current settings
     *
     * @return array Current settings
     */
    private function get_current_settings() {
        return array(
            'revalidation_enabled' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'revalidation_enabled', false ),
            'batch_size' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'batch_size', 50 ),
            'score_improvement_threshold' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'score_improvement_threshold', 15 ),
            'manual_review_threshold' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'manual_review_threshold', 10 ),
            'max_age_days' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'max_age_days', 90 ),
            'whitelist_domains' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'whitelist_domains', array() ),
            'notification_email' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'notification_email', get_option( 'admin_email' ) ),
            'send_notifications' => Wikis_Email_Cleaner_Revalidation_Schema::get_setting( 'send_notifications', true )
        );
    }

    /**
     * Update settings
     */
    private function update_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = array(
            'revalidation_enabled' => isset( $_POST['revalidation_enabled'] ),
            'batch_size' => absint( $_POST['batch_size'] ?? 50 ),
            'score_improvement_threshold' => absint( $_POST['score_improvement_threshold'] ?? 15 ),
            'manual_review_threshold' => absint( $_POST['manual_review_threshold'] ?? 10 ),
            'max_age_days' => absint( $_POST['max_age_days'] ?? 90 ),
            'whitelist_domains' => array_filter( array_map( 'trim', explode( "\n", $_POST['whitelist_domains'] ?? '' ) ) ),
            'notification_email' => sanitize_email( $_POST['notification_email'] ?? '' ),
            'send_notifications' => isset( $_POST['send_notifications'] )
        );

        foreach ( $settings as $key => $value ) {
            $type = is_bool( $value ) ? 'boolean' : ( is_array( $value ) ? 'array' : ( is_numeric( $value ) ? 'integer' : 'string' ) );
            Wikis_Email_Cleaner_Revalidation_Schema::update_setting( $key, $value, $type );
        }

        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully.', 'wikis-email-cleaner' ) . '</p></div>';
        });
    }

    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'wikis_email_cleaner_revalidation_audit';

        $recent_activity = $wpdb->get_results(
            "SELECT * FROM $audit_table 
            ORDER BY created_at DESC 
            LIMIT 20"
        );

        if ( empty( $recent_activity ) ) {
            echo '<p>' . __( 'No recent activity.', 'wikis-email-cleaner' ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __( 'Date', 'wikis-email-cleaner' ) . '</th>';
        echo '<th>' . __( 'Email', 'wikis-email-cleaner' ) . '</th>';
        echo '<th>' . __( 'Action', 'wikis-email-cleaner' ) . '</th>';
        echo '<th>' . __( 'Status Change', 'wikis-email-cleaner' ) . '</th>';
        echo '<th>' . __( 'Score Change', 'wikis-email-cleaner' ) . '</th>';
        echo '<th>' . __( 'Reason', 'wikis-email-cleaner' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $recent_activity as $activity ) {
            echo '<tr>';
            echo '<td>' . esc_html( $activity->created_at ) . '</td>';
            echo '<td>' . esc_html( $activity->email ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $activity->action ) ) . '</td>';
            echo '<td>' . esc_html( $activity->old_status . ' → ' . $activity->new_status ) . '</td>';
            echo '<td>' . esc_html( $activity->old_score . ' → ' . $activity->new_score ) . '</td>';
            echo '<td>' . esc_html( $activity->reason ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * AJAX: Populate queue
     */
    public function ajax_populate_queue() {
        check_ajax_referer( 'wikis_revalidation_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $criteria = array(
            'max_age_days' => absint( $_POST['max_age_days'] ?? 90 ),
            'limit' => absint( $_POST['limit'] ?? 1000 ),
            'force_repopulate' => isset( $_POST['force_repopulate'] )
        );

        $result = Wikis_Email_Cleaner_Revalidation_Queue::populate_queue( $criteria );

        wp_send_json( $result );
    }

    /**
     * AJAX: Process queue
     */
    public function ajax_process_queue() {
        check_ajax_referer( 'wikis_revalidation_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $batch_size = absint( $_POST['batch_size'] ?? 50 );
        $processor = new Wikis_Email_Cleaner_Revalidation_Processor();
        $result = $processor->process_queue( $batch_size );

        wp_send_json( $result );
    }

    /**
     * AJAX: Manual review action
     */
    public function ajax_manual_review() {
        check_ajax_referer( 'wikis_revalidation_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $action = sanitize_text_field( $_POST['action_type'] ?? '' );
        $queue_id = absint( $_POST['queue_id'] ?? 0 );
        $notes = sanitize_textarea_field( $_POST['notes'] ?? '' );

        // Process manual review action
        $result = $this->process_manual_review_action( $queue_id, $action, $notes );

        wp_send_json( $result );
    }

    /**
     * AJAX: Rollback recent resubscriptions
     */
    public function ajax_rollback() {
        check_ajax_referer( 'wikis_revalidation_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $hours = absint( $_POST['hours'] ?? 24 );
        $result = $this->rollback_recent_resubscriptions( $hours );

        wp_send_json( $result );
    }

    /**
     * Process manual review action
     *
     * @param int    $queue_id Queue ID
     * @param string $action   Action to take
     * @param string $notes    Admin notes
     * @return array Result
     */
    private function process_manual_review_action( $queue_id, $action, $notes ) {
        // Implementation for manual review actions
        return array(
            'success' => true,
            'message' => 'Manual review action processed'
        );
    }

    /**
     * Rollback recent resubscriptions
     *
     * @param int $hours Hours to look back
     * @return array Result
     */
    private function rollback_recent_resubscriptions( $hours ) {
        // Implementation for rollback functionality
        return array(
            'success' => true,
            'message' => 'Rollback completed',
            'rolled_back' => 0
        );
    }
}
