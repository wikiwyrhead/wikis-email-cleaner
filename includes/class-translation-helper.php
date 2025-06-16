<?php
/**
 * Translation helper class for safe translation loading
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Translation helper class
 */
class Wikis_Email_Cleaner_Translation_Helper {

    /**
     * Cached translations
     *
     * @var array
     */
    private static $translations = array();

    /**
     * Check if translations are available
     *
     * @return bool True if translations can be loaded
     */
    public static function can_translate() {
        return did_action( 'init' ) || doing_action( 'init' );
    }

    /**
     * Safe translation function
     *
     * @param string $text Text to translate
     * @param string $domain Text domain
     * @return string Translated text or original text
     */
    public static function translate( $text, $domain = 'wikis-email-cleaner' ) {
        // If translations are not available yet, return original text
        if ( ! self::can_translate() ) {
            return $text;
        }

        // Use WordPress translation function
        return __( $text, $domain );
    }

    /**
     * Safe HTML escaped translation function
     *
     * @param string $text Text to translate
     * @param string $domain Text domain
     * @return string Translated and escaped text
     */
    public static function translate_esc_html( $text, $domain = 'wikis-email-cleaner' ) {
        // If translations are not available yet, return escaped original text
        if ( ! self::can_translate() ) {
            return esc_html( $text );
        }

        // Use WordPress translation function
        return esc_html__( $text, $domain );
    }

    /**
     * Safe attribute escaped translation function
     *
     * @param string $text Text to translate
     * @param string $domain Text domain
     * @return string Translated and escaped text
     */
    public static function translate_esc_attr( $text, $domain = 'wikis-email-cleaner' ) {
        // If translations are not available yet, return escaped original text
        if ( ! self::can_translate() ) {
            return esc_attr( $text );
        }

        // Use WordPress translation function
        return esc_attr__( $text, $domain );
    }

    /**
     * Safe sprintf translation function
     *
     * @param string $text Text to translate with placeholders
     * @param mixed  ...$args Arguments for sprintf
     * @return string Translated and formatted text
     */
    public static function translate_sprintf( $text, ...$args ) {
        $translated = self::translate( $text );
        return sprintf( $translated, ...$args );
    }

    /**
     * Get predefined error messages
     *
     * @return array Error messages
     */
    public static function get_error_messages() {
        return array(
            'invalid_format' => self::translate( 'Invalid email format' ),
            'local_too_long' => self::translate( 'Local part too long (max 64 characters)' ),
            'domain_too_long' => self::translate( 'Domain part too long (max 253 characters)' ),
            'consecutive_dots' => self::translate( 'Consecutive dots not allowed' ),
            'local_dot_position' => self::translate( 'Local part cannot start or end with a dot' ),
            'invalid_characters' => self::translate( 'Invalid characters in local part' ),
            'invalid_domain_format' => self::translate( 'Invalid domain format' ),
            'domain_not_exist' => self::translate( 'Domain does not exist' ),
            'no_mx_record' => self::translate( 'No MX record found for domain' ),
            'smtp_connection_failed' => self::translate( 'Cannot connect to SMTP server: %s' ),
            'smtp_not_ready' => self::translate( 'SMTP server not ready' ),
            'email_rejected' => self::translate( 'Email address rejected by server' ),
            'no_mx_record' => self::translate( 'No MX record found for domain' ),
            'test_email_detected' => self::translate( 'Test email address detected' ),
            'example_domain' => self::translate( 'Example domain not allowed' ),
            'fake_pattern' => self::translate( 'Fake email pattern detected' ),
            'sequential_numbers' => self::translate( 'Sequential number email pattern' ),
            'random_string' => self::translate( 'Random string email pattern' ),
        );
    }

    /**
     * Get predefined warning messages
     *
     * @return array Warning messages
     */
    public static function get_warning_messages() {
        return array(
            'disposable_email' => self::translate( 'Disposable email address' ),
            'role_based_email' => self::translate( 'Role-based email address' ),
            'domain_typo' => self::translate( 'Possible typo in domain. Did you mean: %s?' ),
            'temporary_error' => self::translate( 'Temporary server error' ),
            'smtp_not_ready' => self::translate( 'SMTP server not ready' ),
        );
    }

    /**
     * Get predefined recommendation messages
     *
     * @return array Recommendation messages
     */
    public static function get_recommendation_messages() {
        return array(
            'remove_critical' => self::translate( 'Remove this email - it contains critical errors' ),
            'remove_disposable' => self::translate( 'Consider removing - disposable email addresses are temporary' ),
            'monitor_role_based' => self::translate( 'Monitor engagement - role-based emails may have low engagement' ),
            'confirm_typo' => self::translate( 'Contact subscriber to confirm correct email: %s' ),
            'monitor_delivery' => self::translate( 'Monitor delivery rates - email may have deliverability issues' ),
            'email_valid' => self::translate( 'Email appears valid - no action needed' ),
        );
    }

    /**
     * Get error message by key
     *
     * @param string $key Message key
     * @param mixed  ...$args Arguments for sprintf
     * @return string Error message
     */
    public static function get_error_message( $key, ...$args ) {
        $messages = self::get_error_messages();
        $message = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
        
        if ( ! empty( $args ) ) {
            return sprintf( $message, ...$args );
        }
        
        return $message;
    }

    /**
     * Get warning message by key
     *
     * @param string $key Message key
     * @param mixed  ...$args Arguments for sprintf
     * @return string Warning message
     */
    public static function get_warning_message( $key, ...$args ) {
        $messages = self::get_warning_messages();
        $message = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
        
        if ( ! empty( $args ) ) {
            return sprintf( $message, ...$args );
        }
        
        return $message;
    }

    /**
     * Get recommendation message by key
     *
     * @param string $key Message key
     * @param mixed  ...$args Arguments for sprintf
     * @return string Recommendation message
     */
    public static function get_recommendation_message( $key, ...$args ) {
        $messages = self::get_recommendation_messages();
        $message = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
        
        if ( ! empty( $args ) ) {
            return sprintf( $message, ...$args );
        }
        
        return $message;
    }

    /**
     * Initialize translations (called at init hook)
     */
    public static function init_translations() {
        // Pre-load commonly used translations
        self::get_error_messages();
        self::get_warning_messages();
        self::get_recommendation_messages();
    }
}
