<?php

/**
 * Main plugin class
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Main Wikis Email Cleaner class
 */
class Wikis_Email_Cleaner
{

    /**
     * Plugin instance
     *
     * @var Wikis_Email_Cleaner
     */
    private static $instance = null;

    /**
     * Email validator instance
     *
     * @var Wikis_Email_Cleaner_Validator
     */
    public $validator;

    /**
     * Admin instance
     *
     * @var Wikis_Email_Cleaner_Admin
     */
    public $admin;

    /**
     * Scheduler instance
     *
     * @var Wikis_Email_Cleaner_Scheduler
     */
    public $scheduler;

    /**
     * Logger instance
     *
     * @var Wikis_Email_Cleaner_Logger
     */
    public $logger;

    /**
     * Newsletter integration instance
     *
     * @var Wikis_Email_Cleaner_Newsletter_Integration
     */
    public $newsletter_integration;

    /**
     * Revalidation admin instance
     *
     * @var Wikis_Email_Cleaner_Revalidation_Admin
     */
    public $revalidation_admin;

    /**
     * Revalidation scheduler instance
     *
     * @var Wikis_Email_Cleaner_Revalidation_Scheduler
     */
    public $revalidation_scheduler;

    /**
     * Get plugin instance
     *
     * @return Wikis_Email_Cleaner
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_components();
        $this->init_revalidation_system();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies()
    {
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-email-validator.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-scheduler.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-logger.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-newsletter-integration.php';

        // Revalidation system dependencies
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-revalidation-schema.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-revalidation-queue.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-revalidation-processor.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-revalidation-admin.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-revalidation-scheduler.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-revalidation-migration.php';
        require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-improved-settings.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_filter('plugin_action_links_' . WIKIS_EMAIL_CLEANER_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Initialize plugin components
     */
    private function init_components()
    {
        $this->validator = new Wikis_Email_Cleaner_Validator();
        $this->logger = new Wikis_Email_Cleaner_Logger();
        $this->scheduler = new Wikis_Email_Cleaner_Scheduler();
        $this->newsletter_integration = new Wikis_Email_Cleaner_Newsletter_Integration();

        if (is_admin()) {
            $this->admin = new Wikis_Email_Cleaner_Admin();
        }
    }

    /**
     * Initialize revalidation system
     */
    private function init_revalidation_system()
    {
        // Check if migration is needed
        if (Wikis_Email_Cleaner_Revalidation_Migration::is_migration_needed()) {
            add_action('admin_notices', array($this, 'show_migration_notice'));
        }

        // Initialize revalidation components
        if (is_admin()) {
            $this->revalidation_admin = new Wikis_Email_Cleaner_Revalidation_Admin();
        }

        $this->revalidation_scheduler = new Wikis_Email_Cleaner_Revalidation_Scheduler();
    }

    /**
     * Show migration notice
     */
    public function show_migration_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $migration_url = admin_url('admin.php?page=wikis-email-revalidation&action=migrate');
?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php _e('Wikis Email Cleaner:', 'wikis-email-cleaner'); ?></strong>
                <?php _e('A new revalidation system is available that can recover legitimate subscribers who were incorrectly unsubscribed.', 'wikis-email-cleaner'); ?>
                <a href="<?php echo esc_url($migration_url); ?>" class="button button-primary">
                    <?php _e('Set Up Revalidation', 'wikis-email-cleaner'); ?>
                </a>
            </p>
        </div>
<?php
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Hook into Newsletter Plugin events
        $this->newsletter_integration->init();

        // Initialize scheduler
        $this->scheduler->init();

        /**
         * Plugin initialized
         *
         * @since 1.0.0
         */
        do_action('wikis_email_cleaner_init');
    }

    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_public_scripts()
    {
        // Only enqueue if needed
        if (! $this->should_enqueue_public_assets()) {
            return;
        }

        wp_enqueue_style(
            'wikis-email-cleaner-public',
            WIKIS_EMAIL_CLEANER_PLUGIN_URL . 'assets/css/public.css',
            array(),
            WIKIS_EMAIL_CLEANER_VERSION
        );

        wp_enqueue_script(
            'wikis-email-cleaner-public',
            WIKIS_EMAIL_CLEANER_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            WIKIS_EMAIL_CLEANER_VERSION,
            true
        );
    }

    /**
     * Check if public assets should be enqueued
     *
     * @return bool
     */
    private function should_enqueue_public_assets()
    {
        // Add logic to determine when to load public assets
        return false; // For now, no public assets needed
    }

    /**
     * Add action links to plugin page
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wikis-email-cleaner'),
            esc_html__('Settings', 'wikis-email-cleaner')
        );

        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Get plugin option
     *
     * @param string $option_name Option name
     * @param mixed  $default     Default value
     * @return mixed Option value
     */
    public function get_option($option_name, $default = false)
    {
        return get_option('wikis_email_cleaner_' . $option_name, $default);
    }

    /**
     * Update plugin option
     *
     * @param string $option_name Option name
     * @param mixed  $value       Option value
     * @return bool True if updated, false otherwise
     */
    public function update_option($option_name, $value)
    {
        return update_option('wikis_email_cleaner_' . $option_name, $value);
    }

    /**
     * Delete plugin option
     *
     * @param string $option_name Option name
     * @return bool True if deleted, false otherwise
     */
    public function delete_option($option_name)
    {
        return delete_option('wikis_email_cleaner_' . $option_name);
    }

    /**
     * Get plugin version
     *
     * @return string Plugin version
     */
    public function get_version()
    {
        return WIKIS_EMAIL_CLEANER_VERSION;
    }

    /**
     * Get plugin directory path
     *
     * @return string Plugin directory path
     */
    public function get_plugin_dir()
    {
        return WIKIS_EMAIL_CLEANER_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     *
     * @return string Plugin URL
     */
    public function get_plugin_url()
    {
        return WIKIS_EMAIL_CLEANER_PLUGIN_URL;
    }
}
