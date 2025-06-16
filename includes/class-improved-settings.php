<?php
/**
 * Improved settings class with better defaults for reduced false positives
 *
 * @package Wikis_Email_Cleaner
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Improved settings class
 */
class Wikis_Email_Cleaner_Improved_Settings {

    /**
     * Get improved default settings with reduced false positives
     *
     * @return array Improved default settings
     */
    public static function get_improved_defaults() {
        return array(
            // General settings - more conservative
            'enable_auto_clean' => false,
            'enable_deep_validation' => false, // Keep disabled by default due to network issues
            'minimum_score' => 35, // Reduced from 50 to 35
            'schedule_frequency' => 'weekly', // Changed from daily to weekly
            
            // Validation settings - more lenient
            'validate_on_subscription' => true,
            'subscription_minimum_score' => 25, // Reduced from 30 to 25
            'deep_validate_on_confirm' => false, // Keep disabled
            'handle_bounces_complaints' => true,
            
            // New improved settings
            'role_based_business_penalty' => 5,  // Light penalty for business roles
            'role_based_technical_penalty' => 15, // Moderate penalty for technical roles
            'role_based_critical_penalty' => 25,  // Heavy penalty for critical roles
            'disposable_email_penalty' => 20,     // Reduced from 25 to 20
            'fake_pattern_penalty' => 25,         // Reduced from 30 to 25
            'domain_typo_penalty' => 5,           // Reduced from 10 to 5
            
            // SMTP validation settings
            'smtp_timeout' => 15,                  // Increased timeout
            'smtp_connection_retries' => 2,       // Allow retries
            'smtp_treat_timeout_as_warning' => true, // Don't fail on timeouts
            
            // Trusted provider bonus
            'trusted_provider_bonus' => 20,       // Increased bonus
            'corporate_domain_bonus' => 15,       // Increased bonus
            'business_domain_bonus' => 5,         // New bonus for business patterns
            
            // Notification settings
            'send_notifications' => false,
            'notification_email' => get_option( 'admin_email' ),
            'log_retention_days' => 30,
            'batch_size' => 100,
            
            // New safety features
            'enable_whitelist' => true,           // Allow domain whitelisting
            'whitelist_domains' => array(),      // Domains to never flag
            'enable_confidence_scoring' => true, // Show confidence levels
            'require_manual_review_threshold' => 30, // Scores below this need manual review
        );
    }

    /**
     * Get improved disposable domain list (more conservative)
     *
     * @return array Conservative disposable domains list
     */
    public static function get_conservative_disposable_domains() {
        return array(
            // Only include clearly temporary/disposable services
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
            'tempmail.org', 'yopmail.com', 'throwaway.email',
            'temp-mail.org', 'getnada.com', 'maildrop.cc',
            'sharklasers.com', 'guerrillamailblock.com',
            
            // Obvious temporary services
            'mohmal.com', 'emailondeck.com', 'fakeinbox.com',
            'trashmail.com', 'mailcatch.com', 'mailnesia.com',
            'tempail.com', 'dispostable.com', 'tempinbox.com',
            'minuteinbox.com', 'temporaryemail.net', 'tempemails.net',
            
            // Burner services
            'burnermail.io', 'guerrillamail.org', 'guerrillamail.net',
            'guerrillamail.biz', 'spam4.me', 'grr.la',
            
            // Testing domains only
            'example.com', 'example.org', 'example.net',
            'test.com', 'testing.com', 'localhost.com'
            
            // Removed: spamgourmet.com (legitimate forwarding service)
            // Removed: 33mail.com (legitimate alias service)
        );
    }

    /**
     * Get improved typo domain mappings
     *
     * @return array Improved typo mappings
     */
    public static function get_improved_typo_domains() {
        return array(
            // Gmail variations
            'gmial.com' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'gmil.com' => 'gmail.com',
            'gmail.co' => 'gmail.com',
            'gmai.co' => 'gmail.com',
            'gmailcom' => 'gmail.com',
            
            // Yahoo variations
            'yahooo.com' => 'yahoo.com',
            'yaho.com' => 'yahoo.com',
            'yahoo.co' => 'yahoo.com',
            'yahoocom' => 'yahoo.com',
            
            // Hotmail/Outlook variations
            'hotmial.com' => 'hotmail.com',
            'hotmil.com' => 'hotmail.com',
            'hotmailcom' => 'hotmail.com',
            'outlok.com' => 'outlook.com',
            'outloo.com' => 'outlook.com',
            'outlookcom' => 'outlook.com',
            
            // Common TLD mistakes
            'gmail.con' => 'gmail.com',
            'yahoo.con' => 'yahoo.com',
            'hotmail.con' => 'hotmail.com',
            'outlook.con' => 'outlook.com',
        );
    }

    /**
     * Get validation confidence levels
     *
     * @param int $score Validation score
     * @return array Confidence information
     */
    public static function get_confidence_level( $score ) {
        if ( $score >= 80 ) {
            return array(
                'level' => 'high',
                'label' => __( 'High Confidence', 'wikis-email-cleaner' ),
                'description' => __( 'Email is very likely valid', 'wikis-email-cleaner' ),
                'color' => 'green'
            );
        } elseif ( $score >= 60 ) {
            return array(
                'level' => 'medium',
                'label' => __( 'Medium Confidence', 'wikis-email-cleaner' ),
                'description' => __( 'Email is probably valid', 'wikis-email-cleaner' ),
                'color' => 'orange'
            );
        } elseif ( $score >= 40 ) {
            return array(
                'level' => 'low',
                'label' => __( 'Low Confidence', 'wikis-email-cleaner' ),
                'description' => __( 'Email validity uncertain - manual review recommended', 'wikis-email-cleaner' ),
                'color' => 'yellow'
            );
        } else {
            return array(
                'level' => 'very_low',
                'label' => __( 'Very Low Confidence', 'wikis-email-cleaner' ),
                'description' => __( 'Email is likely invalid', 'wikis-email-cleaner' ),
                'color' => 'red'
            );
        }
    }

    /**
     * Check if domain is whitelisted
     *
     * @param string $domain Domain to check
     * @return bool True if whitelisted
     */
    public static function is_domain_whitelisted( $domain ) {
        $settings = get_option( 'wikis_email_cleaner_settings', array() );
        $whitelist = isset( $settings['whitelist_domains'] ) ? $settings['whitelist_domains'] : array();
        
        return in_array( strtolower( $domain ), array_map( 'strtolower', $whitelist ), true );
    }

    /**
     * Get recommended actions based on validation result
     *
     * @param array $validation Validation result
     * @return array Recommended actions
     */
    public static function get_recommended_actions( $validation ) {
        $actions = array();
        $score = $validation['score'];
        $confidence = self::get_confidence_level( $score );

        if ( $confidence['level'] === 'high' ) {
            $actions[] = array(
                'action' => 'keep',
                'label' => __( 'Keep Email', 'wikis-email-cleaner' ),
                'description' => __( 'Email appears valid and safe to keep', 'wikis-email-cleaner' )
            );
        } elseif ( $confidence['level'] === 'medium' ) {
            $actions[] = array(
                'action' => 'monitor',
                'label' => __( 'Monitor Email', 'wikis-email-cleaner' ),
                'description' => __( 'Keep email but monitor delivery rates', 'wikis-email-cleaner' )
            );
        } elseif ( $confidence['level'] === 'low' ) {
            $actions[] = array(
                'action' => 'review',
                'label' => __( 'Manual Review', 'wikis-email-cleaner' ),
                'description' => __( 'Email needs manual review before action', 'wikis-email-cleaner' )
            );
        } else {
            $actions[] = array(
                'action' => 'remove',
                'label' => __( 'Consider Removal', 'wikis-email-cleaner' ),
                'description' => __( 'Email is likely invalid and should be removed', 'wikis-email-cleaner' )
            );
        }

        return $actions;
    }

    /**
     * Migrate old settings to improved settings
     *
     * @return bool True if migration was performed
     */
    public static function migrate_settings() {
        $current_settings = get_option( 'wikis_email_cleaner_settings', array() );
        $improved_defaults = self::get_improved_defaults();
        
        // Check if migration is needed
        if ( isset( $current_settings['settings_version'] ) && 
             $current_settings['settings_version'] >= '1.1.0' ) {
            return false; // Already migrated
        }

        // Merge current settings with improved defaults
        $migrated_settings = array_merge( $improved_defaults, $current_settings );
        $migrated_settings['settings_version'] = '1.1.0';
        $migrated_settings['migration_date'] = current_time( 'mysql' );

        // Update settings
        update_option( 'wikis_email_cleaner_settings', $migrated_settings );
        
        // Update disposable domains to conservative list
        update_option( 'wikis_email_cleaner_disposable_domains', self::get_conservative_disposable_domains() );

        return true;
    }
}
