<?php

/**
 * Email validation class
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Email validator class
 */
class Wikis_Email_Cleaner_Validator
{

    /**
     * Validation results cache
     *
     * @var array
     */
    private $cache = array();

    /**
     * Disposable email domains
     *
     * @var array
     */
    private $disposable_domains = array();

    /**
     * Fake email patterns
     *
     * @var array
     */
    private $fake_patterns = array();

    /**
     * Common typo domains
     *
     * @var array
     */
    private $typo_domains = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_disposable_domains();
        $this->load_fake_patterns();
        $this->load_typo_domains();
    }

    /**
     * Validate email address
     *
     * @param string $email Email address to validate
     * @param bool   $deep  Whether to perform deep validation
     * @return array Validation result
     */
    public function validate_email($email, $deep = false)
    {
        $email = sanitize_email($email);

        // Check cache first
        $cache_key = md5($email . ($deep ? '_deep' : ''));
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $result = array(
            'email'       => $email,
            'is_valid'    => false,
            'errors'      => array(),
            'warnings'    => array(),
            'score'       => 50, // Start with neutral score
            'checked_at'  => current_time('mysql'),
            'validation_details' => array(),
        );

        // Enhanced syntax validation
        $syntax_check = $this->enhanced_syntax_validation($email);
        if (! $syntax_check['valid']) {
            $result['errors'] = array_merge($result['errors'], $syntax_check['errors']);
            $result['score'] = 0;
            $this->cache[$cache_key] = $result;
            return $result;
        }
        $result['score'] += $syntax_check['score'];
        $result['validation_details']['syntax'] = $syntax_check;

        // Check for fake/suspicious patterns
        $fake_check = $this->check_fake_patterns($email);
        if (! $fake_check['valid']) {
            $result['errors'] = array_merge($result['errors'], $fake_check['errors']);
            $result['score'] -= 30;
        }
        $result['validation_details']['fake_patterns'] = $fake_check;

        // Check for disposable email
        $disposable_check = $this->check_disposable_email($email);
        if ($disposable_check['is_disposable']) {
            $result['warnings'][] = Wikis_Email_Cleaner_Translation_Helper::get_warning_message('disposable_email');
            $result['score'] -= 25;
        }
        $result['validation_details']['disposable'] = $disposable_check;

        // Check for role-based email with nuanced scoring
        $role_check = $this->check_role_based_email($email);
        if ($role_check['is_role_based']) {
            $result['warnings'][] = Wikis_Email_Cleaner_Translation_Helper::get_warning_message('role_based_email');

            // Apply different penalties based on role category
            switch ($role_check['role_category']) {
                case 'critical':
                    $result['score'] -= 25; // Heavy penalty for noreply, etc.
                    break;
                case 'technical':
                    $result['score'] -= 15; // Moderate penalty for technical roles
                    break;
                case 'business':
                    $result['score'] -= 5;  // Light penalty for business contacts
                    break;
                default:
                    $result['score'] -= 10; // Default penalty
            }
        }
        $result['validation_details']['role_based'] = $role_check;

        // Check for common typos
        $typo_check = $this->check_typo_domains($email);
        if ($typo_check['has_typo']) {
            $result['warnings'][] = Wikis_Email_Cleaner_Translation_Helper::get_warning_message('domain_typo', $typo_check['suggestion']);
            $result['score'] -= 10;
        }
        $result['validation_details']['typo'] = $typo_check;

        // Check domain validity
        $domain_check = $this->validate_domain($email);
        if (! $domain_check['valid']) {
            $result['errors'] = array_merge($result['errors'], $domain_check['errors']);
            $result['score'] -= 20;
        } else {
            $result['score'] += 20;
        }
        $result['validation_details']['domain'] = $domain_check;

        // Deep validation (MX record check, SMTP validation)
        if ($deep) {
            $deep_check = $this->deep_validate($email);
            $result['score'] += $deep_check['score'];
            $result['warnings'] = array_merge($result['warnings'], $deep_check['warnings']);
            $result['errors'] = array_merge($result['errors'], $deep_check['errors']);
            $result['validation_details']['deep'] = $deep_check;
        }

        // Apply scoring adjustments based on validation results
        $result = $this->apply_scoring_logic($result);

        // Calculate final validity with enhanced logic
        $result['is_valid'] = $this->determine_validity($result);

        // Cache result
        $this->cache[$cache_key] = $result;

        return $result;
    }

    /**
     * Validate multiple emails
     *
     * @param array $emails Array of email addresses
     * @param bool  $deep   Whether to perform deep validation
     * @return array Validation results
     */
    public function validate_emails($emails, $deep = false)
    {
        $results = array();

        foreach ($emails as $email) {
            $results[] = $this->validate_email($email, $deep);
        }

        return $results;
    }

    /**
     * Enhanced syntax validation
     *
     * @param string $email Email address
     * @return array Validation result
     */
    private function enhanced_syntax_validation($email)
    {
        $result = array(
            'valid' => false,
            'errors' => array(),
            'score' => 0
        );

        // Basic WordPress validation
        if (! is_email($email)) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('invalid_format');
            return $result;
        }

        // Additional syntax checks
        $local_part = substr($email, 0, strpos($email, '@'));
        $domain_part = substr(strrchr($email, '@'), 1);

        // Check local part length (max 64 characters)
        if (strlen($local_part) > 64) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('local_too_long');
            return $result;
        }

        // Check domain part length (max 253 characters)
        if (strlen($domain_part) > 253) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('domain_too_long');
            return $result;
        }

        // Check for consecutive dots
        if (strpos($email, '..') !== false) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('consecutive_dots');
            return $result;
        }

        // Check for leading/trailing dots in local part
        if ($local_part[0] === '.' || substr($local_part, -1) === '.') {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('local_dot_position');
            return $result;
        }

        // Check for valid characters in local part
        if (! preg_match('/^[a-zA-Z0-9._%+-]+$/', $local_part)) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('invalid_characters');
            return $result;
        }

        // Check domain format
        if (! preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain_part)) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('invalid_domain_format');
            return $result;
        }

        $result['valid'] = true;
        $result['score'] = 10; // Base score for valid syntax
        return $result;
    }

    /**
     * Check for fake/suspicious email patterns
     *
     * @param string $email Email address
     * @return array Check result
     */
    private function check_fake_patterns($email)
    {
        $result = array(
            'valid' => true,
            'errors' => array(),
            'patterns_found' => array()
        );

        $email_lower = strtolower($email);

        foreach ($this->fake_patterns as $pattern) {
            if (preg_match($pattern['regex'], $email_lower)) {
                $result['valid'] = false;
                $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message($pattern['message']);
                $result['patterns_found'][] = $pattern['name'];
            }
        }

        return $result;
    }

    /**
     * Check if email is from a disposable domain
     *
     * @param string $email Email address
     * @return array Disposable check result
     */
    private function check_disposable_email($email)
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        $is_disposable = in_array($domain, $this->disposable_domains, true);

        return array(
            'is_disposable' => $is_disposable,
            'domain' => $domain,
            'service' => $is_disposable ? $this->get_disposable_service_name($domain) : null
        );
    }

    /**
     * Check if email is role-based with improved categorization
     *
     * @param string $email Email address
     * @return array Role-based check result
     */
    private function check_role_based_email($email)
    {
        // Critical role-based (should be flagged more strictly)
        $critical_roles = array(
            'noreply',
            'no-reply',
            'donotreply',
            'do-not-reply',
            'postmaster',
            'hostmaster',
            'abuse',
            'mailer-daemon'
        );

        // Business role-based (legitimate business contacts)
        $business_roles = array(
            'info',
            'contact',
            'support',
            'help',
            'sales',
            'marketing',
            'service',
            'billing',
            'accounts',
            'orders',
            'admin',
            'administrator',
            'office',
            'team',
            'staff',
            'hr',
            'jobs',
            'careers',
            'press',
            'media',
            'legal',
            'privacy',
            'security'
        );

        // Technical role-based (less likely to be personal)
        $technical_roles = array(
            'webmaster',
            'www',
            'ftp',
            'mail',
            'email',
            'root',
            'system'
        );

        $local_part = strtolower(substr($email, 0, strpos($email, '@')));

        $role_category = null;
        $is_role_based = false;

        if (in_array($local_part, $critical_roles, true)) {
            $is_role_based = true;
            $role_category = 'critical';
        } elseif (in_array($local_part, $business_roles, true)) {
            $is_role_based = true;
            $role_category = 'business';
        } elseif (in_array($local_part, $technical_roles, true)) {
            $is_role_based = true;
            $role_category = 'technical';
        }

        return array(
            'is_role_based' => $is_role_based,
            'local_part' => $local_part,
            'role_type' => $is_role_based ? $local_part : null,
            'role_category' => $role_category
        );
    }

    /**
     * Check for common domain typos
     *
     * @param string $email Email address
     * @return array Typo check result
     */
    private function check_typo_domains($email)
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));

        foreach ($this->typo_domains as $typo => $correct) {
            if ($domain === $typo) {
                return array(
                    'has_typo' => true,
                    'typo_domain' => $typo,
                    'suggestion' => $correct,
                    'corrected_email' => str_replace('@' . $typo, '@' . $correct, $email)
                );
            }
        }

        return array(
            'has_typo' => false,
            'domain' => $domain
        );
    }

    /**
     * Validate email domain
     *
     * @param string $email Email address
     * @return array Domain validation result
     */
    private function validate_domain($email)
    {
        $domain = substr(strrchr($email, '@'), 1);
        $result = array(
            'valid'  => false,
            'errors' => array(),
        );

        // Check if domain exists
        if (! checkdnsrr($domain, 'ANY')) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('domain_not_exist');
            return $result;
        }

        // Check for MX record
        if (! checkdnsrr($domain, 'MX')) {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('no_mx_record');
            return $result;
        }

        $result['valid'] = true;
        return $result;
    }

    /**
     * Apply enhanced scoring logic with improved false positive reduction
     *
     * @param array $result Validation result
     * @return array Updated result with adjusted score
     */
    private function apply_scoring_logic($result)
    {
        // Bonus points for good indicators
        $domain = substr(strrchr($result['email'], '@'), 1);

        // Expanded trusted providers list with more international services
        $trusted_providers = array(
            'gmail.com',
            'yahoo.com',
            'hotmail.com',
            'outlook.com',
            'aol.com',
            'icloud.com',
            'protonmail.com',
            'zoho.com',
            'fastmail.com',
            'live.com',
            'msn.com',
            'yahoo.co.uk',
            'yahoo.ca',
            'yahoo.com.au',
            'googlemail.com',
            'me.com',
            'mac.com',
            'yandex.com',
            'mail.ru',
            'qq.com',
            '163.com',
            '126.com',
            'sina.com',
            'sohu.com'
        );

        if (in_array(strtolower($domain), $trusted_providers, true)) {
            $result['score'] += 20; // Increased bonus for trusted providers
            $result['validation_details']['trusted_provider'] = true;
        }

        // Corporate domains (with proper MX) get bonus points
        if (
            isset($result['validation_details']['domain']['valid']) &&
            $result['validation_details']['domain']['valid'] &&
            ! in_array(strtolower($domain), $trusted_providers, true)
        ) {
            $result['score'] += 15; // Increased bonus for valid corporate domains
            $result['validation_details']['corporate_domain'] = true;
        }

        // Age-based domain scoring (if we can determine domain age)
        $result = $this->apply_domain_reputation_scoring($result, $domain);

        // Reduced penalty for multiple minor issues (warnings vs errors)
        $error_count = count($result['errors']);
        $warning_count = count($result['warnings']);

        // Only penalize for multiple errors, not warnings
        if ($error_count > 1) {
            $result['score'] -= ($error_count - 1) * 3; // Reduced penalty
        }

        // Very light penalty for excessive warnings
        if ($warning_count > 3) {
            $result['score'] -= ($warning_count - 3) * 1;
        }

        // Ensure score stays within bounds
        $result['score'] = max(0, min(100, $result['score']));

        return $result;
    }

    /**
     * Apply domain reputation scoring
     *
     * @param array  $result Validation result
     * @param string $domain Domain name
     * @return array Updated result
     */
    private function apply_domain_reputation_scoring($result, $domain)
    {
        // Check for common business domain patterns
        $business_patterns = array(
            '/\.(com|org|net|edu|gov)$/',
            '/\.(co\.|com\.)[a-z]{2}$/', // Country-specific business domains
            '/\.(inc|corp|ltd|llc)\./',
        );

        foreach ($business_patterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                $result['score'] += 5;
                $result['validation_details']['business_domain'] = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Determine email validity based on comprehensive analysis with improved logic
     *
     * @param array $result Validation result
     * @return bool True if email is considered valid
     */
    private function determine_validity($result)
    {
        // Hard failures (syntax errors, non-existent domains)
        if (! empty($result['errors'])) {
            // Exception: Allow temporary SMTP errors to pass if other indicators are good
            $has_critical_errors = false;
            foreach ($result['errors'] as $error) {
                if (
                    strpos($error, 'temporary') === false &&
                    strpos($error, '451') === false &&
                    strpos($error, '452') === false
                ) {
                    $has_critical_errors = true;
                    break;
                }
            }

            if ($has_critical_errors) {
                return false;
            }
        }

        // Adaptive scoring thresholds based on email characteristics
        $min_score = $this->calculate_adaptive_threshold($result);

        return $result['score'] >= $min_score;
    }

    /**
     * Calculate adaptive threshold based on email characteristics
     *
     * @param array $result Validation result
     * @return int Minimum score threshold
     */
    private function calculate_adaptive_threshold($result)
    {
        $base_threshold = 45; // Lowered base threshold

        // Trusted providers get significant leeway
        if (
            isset($result['validation_details']['trusted_provider']) &&
            $result['validation_details']['trusted_provider']
        ) {
            return 35; // Very low threshold for Gmail, Yahoo, etc.
        }

        // Corporate domains with valid MX get moderate leeway
        if (
            isset($result['validation_details']['corporate_domain']) &&
            $result['validation_details']['corporate_domain']
        ) {
            return 40;
        }

        // Business domain patterns get slight leeway
        if (
            isset($result['validation_details']['business_domain']) &&
            $result['validation_details']['business_domain']
        ) {
            $base_threshold -= 5;
        }

        // Disposable emails need higher scores, but not impossibly high
        if (
            isset($result['validation_details']['disposable']['is_disposable']) &&
            $result['validation_details']['disposable']['is_disposable']
        ) {
            return 65; // Reduced from 80 to 65
        }

        // Role-based emails get different thresholds based on category
        if (
            isset($result['validation_details']['role_based']['is_role_based']) &&
            $result['validation_details']['role_based']['is_role_based']
        ) {
            $role_category = $result['validation_details']['role_based']['role_category'] ?? 'unknown';
            switch ($role_category) {
                case 'critical':
                    $base_threshold += 20; // High threshold for noreply, etc.
                    break;
                case 'technical':
                    $base_threshold += 15; // Moderate increase for technical roles
                    break;
                case 'business':
                    $base_threshold += 5;  // Small increase for business contacts
                    break;
                default:
                    $base_threshold += 10; // Default increase
            }
        }

        return $base_threshold;
    }

    /**
     * Perform deep email validation
     *
     * @param string $email Email address
     * @return array Deep validation result
     */
    private function deep_validate($email)
    {
        $result = array(
            'score'    => 0,
            'warnings' => array(),
            'errors'   => array(),
            'mx_records' => array(),
            'smtp_test' => false,
            'response_time' => 0
        );

        $domain = substr(strrchr($email, '@'), 1);
        $start_time = microtime(true);

        // Enhanced MX record check
        $mx_records = array();
        $mx_weights = array();

        if (getmxrr($domain, $mx_records, $mx_weights)) {
            $result['score'] += 15;
            $result['mx_records'] = array_combine($mx_records, $mx_weights);

            // Sort by priority (lower weight = higher priority)
            asort($result['mx_records']);
            $primary_mx = array_keys($result['mx_records'])[0];

            // Enhanced SMTP validation
            $smtp_result = $this->enhanced_smtp_validation($primary_mx, $email);
            $result['score'] += $smtp_result['score'];
            $result['smtp_test'] = $smtp_result['success'];
            $result['warnings'] = array_merge($result['warnings'], $smtp_result['warnings']);
            $result['errors'] = array_merge($result['errors'], $smtp_result['errors']);
        } else {
            $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('no_mx_record');
            $result['score'] -= 20;
        }

        $result['response_time'] = round((microtime(true) - $start_time) * 1000, 2);

        return $result;
    }

    /**
     * Enhanced SMTP validation with improved error handling
     *
     * @param string $mx_host MX host
     * @param string $email Email address to test
     * @return array SMTP validation result
     */
    private function enhanced_smtp_validation($mx_host, $email)
    {
        $result = array(
            'success' => false,
            'score' => 0,
            'warnings' => array(),
            'errors' => array(),
            'smtp_codes' => array(),
            'connection_type' => 'failed'
        );

        // Increased timeout and better error handling
        $timeout = 15; // Increased from 10 to 15 seconds
        $socket = @fsockopen($mx_host, 25, $errno, $errstr, $timeout);

        if (! $socket) {
            // Categorize connection failures
            if ($errno == 110 || strpos($errstr, 'timeout') !== false) {
                $result['warnings'][] = Wikis_Email_Cleaner_Translation_Helper::get_warning_message('smtp_timeout');
                $result['score'] += 5; // Give benefit of doubt for timeouts
                $result['connection_type'] = 'timeout';
            } elseif ($errno == 111 || strpos($errstr, 'refused') !== false) {
                $result['warnings'][] = Wikis_Email_Cleaner_Translation_Helper::get_warning_message('smtp_refused');
                $result['score'] += 3; // Slight benefit for connection refused
                $result['connection_type'] = 'refused';
            } else {
                $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('smtp_connection_failed', $errstr);
                $result['connection_type'] = 'error';
            }
            return $result;
        }

        $result['connection_type'] = 'connected';

        // Read initial response
        $response = fgets($socket, 1024);
        $result['smtp_codes'][] = $response;

        if (! preg_match('/^220/', $response)) {
            $result['warnings'][] = Wikis_Email_Cleaner_Translation_Helper::get_warning_message('smtp_not_ready');
            fclose($socket);
            return $result;
        }

        // HELO command
        fputs($socket, "HELO " . $_SERVER['HTTP_HOST'] . "\r\n");
        $response = fgets($socket, 1024);
        $result['smtp_codes'][] = $response;

        if (preg_match('/^250/', $response)) {
            $result['score'] += 10;

            // MAIL FROM command
            fputs($socket, "MAIL FROM: <test@" . $_SERVER['HTTP_HOST'] . ">\r\n");
            $response = fgets($socket, 1024);
            $result['smtp_codes'][] = $response;

            if (preg_match('/^250/', $response)) {
                $result['score'] += 10;

                // RCPT TO command (the actual email test)
                fputs($socket, "RCPT TO: <" . $email . ">\r\n");
                $response = fgets($socket, 1024);
                $result['smtp_codes'][] = $response;

                if (preg_match('/^250/', $response)) {
                    $result['success'] = true;
                    $result['score'] += 20;
                } elseif (preg_match('/^550/', $response)) {
                    $result['errors'][] = Wikis_Email_Cleaner_Translation_Helper::get_error_message('email_rejected');
                } elseif (preg_match('/^451|^452/', $response)) {
                    $result['warnings'][] = Wikis_Email_Cleaner_Translation_Helper::get_warning_message('temporary_error');
                    $result['score'] += 5; // Benefit of doubt
                }
            }
        }

        // QUIT command
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return $result;
    }

    /**
     * Check if SMTP server is reachable (legacy method)
     *
     * @param string $mx_host MX host
     * @return bool True if reachable
     */
    private function can_connect_smtp($mx_host)
    {
        $timeout = 5;
        $socket = @fsockopen($mx_host, 25, $errno, $errstr, $timeout);

        if ($socket) {
            fclose($socket);
            return true;
        }

        return false;
    }

    /**
     * Load disposable email domains
     */
    private function load_disposable_domains()
    {
        // Load from option or use default list
        $this->disposable_domains = get_option('wikis_email_cleaner_disposable_domains', $this->get_default_disposable_domains());
    }

    /**
     * Load fake email patterns with improved accuracy
     */
    private function load_fake_patterns()
    {
        $this->fake_patterns = array(
            array(
                'name' => 'obvious_test_emails',
                'regex' => '/^(test|testing|tester)[\d]*@/',
                'message' => 'test_email_detected'
            ),
            array(
                'name' => 'example_emails',
                'regex' => '/^.*@example\.(com|org|net)$/',
                'message' => 'example_domain'
            ),
            array(
                'name' => 'clearly_fake_patterns',
                'regex' => '/^(fake|dummy|invalid|null|void|bogus|notreal)[\d]*@/',
                'message' => 'fake_pattern'
            ),
            array(
                'name' => 'excessive_sequential_numbers',
                'regex' => '/^[\d]{8,}@/', // Increased from 5 to 8 digits
                'message' => 'sequential_numbers'
            ),
            array(
                'name' => 'excessive_random_strings',
                'regex' => '/^[a-z]{25,}@/', // Increased from 20 to 25 characters
                'message' => 'random_string'
            ),
            array(
                'name' => 'keyboard_mashing',
                'regex' => '/^(asdf|qwer|zxcv|hjkl|uiop|bnm|fgh|rty|cvb){3,}@/',
                'message' => 'keyboard_pattern'
            )
        );
    }

    /**
     * Load common typo domains
     */
    private function load_typo_domains()
    {
        $this->typo_domains = array(
            'gmial.com' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'gmil.com' => 'gmail.com',
            'yahooo.com' => 'yahoo.com',
            'yaho.com' => 'yahoo.com',
            'hotmial.com' => 'hotmail.com',
            'hotmil.com' => 'hotmail.com',
            'outlok.com' => 'outlook.com',
            'outloo.com' => 'outlook.com',
            'gmai.co' => 'gmail.com',
            'yahoo.co' => 'yahoo.com'
        );
    }

    /**
     * Get default disposable domains list (comprehensive)
     *
     * @return array Default disposable domains
     */
    private function get_default_disposable_domains()
    {
        return array(
            // Popular disposable services
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
            'tempmail.org',
            'yopmail.com',
            'throwaway.email',
            'temp-mail.org',
            'getnada.com',
            'maildrop.cc',
            'sharklasers.com',
            'guerrillamailblock.com',

            // Additional disposable services
            'mohmal.com',
            'emailondeck.com',
            'fakeinbox.com',
            'spamgourmet.com',
            'trashmail.com',
            '33mail.com',
            'mailcatch.com',
            'mailnesia.com',
            'tempail.com',
            'dispostable.com',
            'tempinbox.com',
            'minuteinbox.com',
            'emailtemporanea.com',
            'temporaryemail.net',
            'tempemails.net',

            // Burner email services
            'burnermail.io',
            'guerrillamail.org',
            'guerrillamail.net',
            'guerrillamail.biz',
            'spam4.me',
            'grr.la',
            'guerrillamail.de',
            'trbvm.com',
            'sharklasers.com',

            // Temporary services
            'mailtemp.info',
            'tempmail.ninja',
            'tempmail.email',
            'temp-mail.io',
            'tempmailo.com',
            'tempmailaddress.com',
            'tempmail.altmails.com',
            'tempmail.plus',

            // Testing domains
            'example.com',
            'example.org',
            'example.net',
            'test.com',
            'testing.com',
            'localhost.com'
        );
    }

    /**
     * Get disposable service name
     *
     * @param string $domain Domain name
     * @return string Service name
     */
    private function get_disposable_service_name($domain)
    {
        $services = array(
            '10minutemail.com' => '10 Minute Mail',
            'guerrillamail.com' => 'Guerrilla Mail',
            'mailinator.com' => 'Mailinator',
            'tempmail.org' => 'TempMail',
            'yopmail.com' => 'YOPmail',
            'throwaway.email' => 'Throwaway Email'
        );

        return isset($services[$domain]) ? $services[$domain] : 'Unknown Disposable Service';
    }

    /**
     * Update disposable domains list
     *
     * @param array $domains Array of domains
     * @return bool True if updated
     */
    public function update_disposable_domains($domains)
    {
        $this->disposable_domains = array_map('strtolower', $domains);
        return update_option('wikis_email_cleaner_disposable_domains', $this->disposable_domains);
    }

    /**
     * Clear validation cache
     */
    public function clear_cache()
    {
        $this->cache = array();
    }

    /**
     * Get validation statistics
     *
     * @return array Validation statistics
     */
    public function get_validation_stats()
    {
        return array(
            'cache_size' => count($this->cache),
            'disposable_domains_count' => count($this->disposable_domains),
            'fake_patterns_count' => count($this->fake_patterns),
            'typo_domains_count' => count($this->typo_domains)
        );
    }

    /**
     * Validate email with detailed report
     *
     * @param string $email Email address
     * @param bool   $deep  Whether to perform deep validation
     * @return array Detailed validation report
     */
    public function get_detailed_report($email, $deep = false)
    {
        $validation = $this->validate_email($email, $deep);

        $report = array(
            'email' => $email,
            'overall_result' => $validation['is_valid'] ? 'VALID' : 'INVALID',
            'confidence_score' => $validation['score'],
            'risk_level' => $this->get_risk_level($validation['score']),
            'issues_found' => array_merge($validation['errors'], $validation['warnings']),
            'recommendations' => $this->get_recommendations($validation),
            'technical_details' => $validation['validation_details'] ?? array(),
            'checked_at' => $validation['checked_at']
        );

        return $report;
    }

    /**
     * Get risk level based on score
     *
     * @param int $score Validation score
     * @return string Risk level
     */
    private function get_risk_level($score)
    {
        if ($score >= 80) {
            return 'LOW';
        } elseif ($score >= 60) {
            return 'MEDIUM';
        } elseif ($score >= 40) {
            return 'HIGH';
        } else {
            return 'CRITICAL';
        }
    }

    /**
     * Get recommendations based on validation result
     *
     * @param array $validation Validation result
     * @return array Recommendations
     */
    private function get_recommendations($validation)
    {
        $recommendations = array();

        if (! empty($validation['errors'])) {
            $recommendations[] = Wikis_Email_Cleaner_Translation_Helper::get_recommendation_message('remove_critical');
        }

        if (
            isset($validation['validation_details']['disposable']['is_disposable']) &&
            $validation['validation_details']['disposable']['is_disposable']
        ) {
            $recommendations[] = Wikis_Email_Cleaner_Translation_Helper::get_recommendation_message('remove_disposable');
        }

        if (
            isset($validation['validation_details']['role_based']['is_role_based']) &&
            $validation['validation_details']['role_based']['is_role_based']
        ) {
            $recommendations[] = Wikis_Email_Cleaner_Translation_Helper::get_recommendation_message('monitor_role_based');
        }

        if (
            isset($validation['validation_details']['typo']['has_typo']) &&
            $validation['validation_details']['typo']['has_typo']
        ) {
            $recommendations[] = Wikis_Email_Cleaner_Translation_Helper::get_recommendation_message(
                'confirm_typo',
                $validation['validation_details']['typo']['suggestion']
            );
        }

        if ($validation['score'] < 60 && empty($validation['errors'])) {
            $recommendations[] = Wikis_Email_Cleaner_Translation_Helper::get_recommendation_message('monitor_delivery');
        }

        if (empty($recommendations) && $validation['is_valid']) {
            $recommendations[] = Wikis_Email_Cleaner_Translation_Helper::get_recommendation_message('email_valid');
        }

        return $recommendations;
    }

    /**
     * Batch validate emails with progress tracking
     *
     * @param array $emails Array of email addresses
     * @param bool  $deep   Whether to perform deep validation
     * @param callable $progress_callback Optional progress callback
     * @return array Batch validation results
     */
    public function batch_validate($emails, $deep = false, $progress_callback = null)
    {
        $results = array();
        $total = count($emails);

        foreach ($emails as $index => $email) {
            $results[] = $this->validate_email($email, $deep);

            if ($progress_callback && is_callable($progress_callback)) {
                call_user_func($progress_callback, $index + 1, $total);
            }

            // Prevent timeout on large batches
            if (($index + 1) % 50 === 0) {
                usleep(100000); // 0.1 second pause
            }
        }

        return $results;
    }
}
