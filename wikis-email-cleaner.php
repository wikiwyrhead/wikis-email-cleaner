<?php
/**
 * Plugin Name: Wikis Email Cleaner
 * Plugin URI: https://github.com/wikiwyrhead/wikis-email-cleaner
 * Description: A professional email cleaning tool for The Newsletter Plugin. Validates emails, unsubscribes invalid ones, logs actions, and exports to CSV. Includes admin interface, scheduled scans, and email alerts.
 * Version: 1.0.0
 * Author: wikiwyrhead
 * Author URI: https://github.com/wikiwyrhead
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.5
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Text Domain: wikis-email-cleaner
 * Domain Path: /languages
 * Network: false
 *
 * @package Wikis_Email_Cleaner
 * @version 1.0.0
 * @author wikiwyrhead
 * @license GPLv2 or later
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WIKIS_EMAIL_CLEANER_VERSION', '1.0.0' );
define( 'WIKIS_EMAIL_CLEANER_PLUGIN_FILE', __FILE__ );
define( 'WIKIS_EMAIL_CLEANER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WIKIS_EMAIL_CLEANER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WIKIS_EMAIL_CLEANER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check for required PHP version
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', 'wikis_email_cleaner_php_version_notice' );
    return;
}

/**
 * Display PHP version notice
 */
function wikis_email_cleaner_php_version_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'Wikis Email Cleaner requires PHP 7.4 or higher. Please update your PHP version.', 'wikis-email-cleaner' ); ?></p>
    </div>
    <?php
}

// Check if The Newsletter Plugin is active
add_action( 'admin_init', 'wikis_email_cleaner_check_newsletter_plugin' );

/**
 * Check if The Newsletter Plugin is active
 */
function wikis_email_cleaner_check_newsletter_plugin() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! is_plugin_active( 'newsletter/plugin.php' ) && ! class_exists( 'Newsletter' ) ) {
        add_action( 'admin_notices', 'wikis_email_cleaner_newsletter_plugin_notice' );
        // Only deactivate if we're in admin and not during activation
        if ( is_admin() && ! ( isset( $_GET['action'] ) && $_GET['action'] === 'activate' ) ) {
            deactivate_plugins( WIKIS_EMAIL_CLEANER_PLUGIN_BASENAME );
        }
    }
}

/**
 * Display Newsletter Plugin requirement notice
 */
function wikis_email_cleaner_newsletter_plugin_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'Wikis Email Cleaner requires The Newsletter Plugin to be installed and activated.', 'wikis-email-cleaner' ); ?></p>
    </div>
    <?php
}

// Include required files for activation/deactivation
require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-translation-helper.php';
require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-activator.php';
require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-uninstaller.php';

/**
 * Initialize the plugin
 */
function wikis_email_cleaner_init() {
    // Include main plugin files
    require_once WIKIS_EMAIL_CLEANER_PLUGIN_DIR . 'includes/class-wikis-email-cleaner.php';

    // Initialize main plugin class
    Wikis_Email_Cleaner::get_instance();
}
add_action( 'plugins_loaded', 'wikis_email_cleaner_init' );

/**
 * Initialize translations at the proper time
 */
function wikis_email_cleaner_init_translations() {
    // Load text domain
    load_plugin_textdomain( 'wikis-email-cleaner', false, dirname( WIKIS_EMAIL_CLEANER_PLUGIN_BASENAME ) . '/languages' );

    // Initialize translation helper
    Wikis_Email_Cleaner_Translation_Helper::init_translations();
}
add_action( 'init', 'wikis_email_cleaner_init_translations' );

// Add activation notice
add_action( 'admin_notices', array( 'Wikis_Email_Cleaner_Activator', 'activation_notice' ) );

/**
 * Plugin activation hook
 */
function wikis_email_cleaner_activate() {
    // Basic checks first (without translations during activation)
    if ( version_compare( get_bloginfo( 'version' ), '5.5', '<' ) ) {
        wp_die( 'Wikis Email Cleaner requires WordPress 5.5 or higher.' );
    }

    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        wp_die( 'Wikis Email Cleaner requires PHP 7.4 or higher.' );
    }

    // Create database tables and set default options
    Wikis_Email_Cleaner_Activator::activate();
}
register_activation_hook( __FILE__, 'wikis_email_cleaner_activate' );

/**
 * Plugin deactivation hook
 */
function wikis_email_cleaner_deactivate() {
    Wikis_Email_Cleaner_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'wikis_email_cleaner_deactivate' );

/**
 * Plugin uninstall hook
 */
function wikis_email_cleaner_uninstall() {
    Wikis_Email_Cleaner_Uninstaller::uninstall();
}
register_uninstall_hook( __FILE__, 'wikis_email_cleaner_uninstall' );