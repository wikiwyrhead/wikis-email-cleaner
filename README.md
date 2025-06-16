# Wikis Email Cleaner

A professional email cleaning tool for The Newsletter Plugin. Validates emails, unsubscribes invalid ones, logs actions, and exports to CSV. Includes admin interface, scheduled scans, and email alerts.

## Features

- **Email Validation**: Comprehensive email validation including syntax, domain, MX record, and SMTP checks
- **Newsletter Plugin Integration**: Seamless integration with The Newsletter Plugin using official hooks and filters
- **Automated Cleaning**: Scheduled scans to automatically identify and unsubscribe invalid emails
- **Real-time Validation**: Validate emails during subscription process to prevent invalid subscriptions
- **Detailed Logging**: Complete audit trail of all validation activities with CSV export
- **Admin Dashboard**: User-friendly interface for monitoring and managing email cleaning
- **Configurable Settings**: Flexible configuration options for validation rules and automation
- **Email Notifications**: Optional email reports after scan completion

## Requirements

- WordPress 5.5 or higher
- PHP 7.4 or higher
- The Newsletter Plugin (active)
- MySQL database with table creation permissions

## Installation

1. **Download and Upload**
   - Download the plugin files
   - Upload to `/wp-content/plugins/wikis-email-cleaner/`
   - Or install via WordPress admin dashboard

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Wikis Email Cleaner" and click "Activate"

3. **Configure Settings**
   - Navigate to Email Cleaner → Settings
   - Configure validation rules and automation preferences
   - Save settings

## Configuration

### General Settings

- **Enable Automatic Cleaning**: Turn on/off scheduled email cleaning
- **Minimum Valid Score**: Set threshold for email validity (0-100)
- **Scan Frequency**: Choose how often to run automatic scans (hourly/daily/weekly)

### Validation Settings

- **Enable Deep Validation**: Perform SMTP and MX record checks (slower but more accurate)
- **Validate on Subscription**: Check emails during subscription process
- **Subscription Minimum Score**: Lower threshold for new subscriptions
- **Deep Validate on Confirmation**: Run deep validation when users confirm subscription
- **Handle Bounces & Complaints**: Automatically process bounced emails and spam complaints

### Notification Settings

- **Send Email Notifications**: Receive email reports after scans
- **Notification Email**: Email address for receiving reports

### Advanced Settings

- **Log Retention Days**: How long to keep validation logs (1-365 days)
- **Batch Size**: Number of emails to process per batch (10-1000)
- **Preserve Data on Uninstall**: Keep plugin data when uninstalling

## Usage

### Manual Email Scan

1. Go to Email Cleaner dashboard
2. Click "Scan All Emails" button
3. Monitor progress and review results
4. Export results to CSV if needed

### Viewing Logs

1. Navigate to Email Cleaner → Logs
2. Use filters to find specific validation results
3. Export logs to CSV for external analysis

### Scheduled Scans

1. Enable "Automatic Cleaning" in settings
2. Choose scan frequency
3. Plugin will automatically scan and clean emails based on schedule

## Email Validation Process

The plugin uses a multi-step validation process:

1. **Syntax Check**: Validates email format using WordPress standards
2. **Disposable Email Detection**: Checks against known disposable email providers
3. **Role-based Email Detection**: Identifies generic role-based emails (admin@, info@, etc.)
4. **Domain Validation**: Verifies domain exists and has MX records
5. **SMTP Validation** (Deep mode): Tests SMTP server connectivity
6. **Scoring**: Assigns score based on validation results (0-100)

## Integration with Newsletter Plugin

The plugin integrates with The Newsletter Plugin using official hooks:

- `newsletter_subscription`: Validates emails during subscription
- `newsletter_user_confirmed`: Validates on user confirmation
- `newsletter_user_unsubscribed`: Logs unsubscription events
- `newsletter_user_bounced`: Handles bounced emails
- `newsletter_user_complained`: Processes spam complaints

## Database Tables

The plugin creates two database tables:

- `wp_wikis_email_cleaner_logs`: Stores validation results
- `wp_wikis_email_cleaner_scan_summary`: Stores scan summaries

## Security Features

- **Nonce Verification**: All AJAX requests use WordPress nonces
- **Capability Checks**: Admin functions require `manage_options` capability
- **Data Sanitization**: All input data is properly sanitized
- **SQL Injection Prevention**: Uses WordPress prepared statements
- **File Protection**: Upload directories protected with .htaccess

## Performance Considerations

- **Batch Processing**: Large email lists processed in configurable batches
- **Caching**: Validation results cached to avoid duplicate checks
- **Background Processing**: Scheduled scans run via WordPress cron
- **Memory Management**: Automatic cleanup of old logs and temporary files

## Troubleshooting

### Common Issues

1. **Plugin won't activate**
   - Ensure The Newsletter Plugin is installed and active
   - Check PHP version (7.4+ required)
   - Verify WordPress version (5.5+ required)

2. **Scans not running automatically**
   - Check if automatic cleaning is enabled in settings
   - Verify WordPress cron is working properly
   - Check server logs for any errors

3. **Deep validation not working**
   - Ensure server can make outbound connections on port 25
   - Check if hosting provider blocks SMTP connections
   - Try disabling deep validation and use basic validation only

### Debug Mode

To enable debug logging, add this to your wp-config.php:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for plugin-related messages.

## Hooks and Filters

### Actions

- `wikis_email_cleaner_init`: Fired when plugin is initialized
- `wikis_email_cleaner_auto_unsubscribed`: Fired when email is automatically unsubscribed
- `wikis_email_cleaner_problematic_email`: Fired when problematic email is detected

### Filters

- `wikis_email_cleaner_validation_score`: Modify validation score
- `wikis_email_cleaner_disposable_domains`: Modify disposable domains list
- `wikis_email_cleaner_batch_size`: Modify batch processing size

## Support

For support and bug reports:

1. Check the troubleshooting section above
2. Review WordPress and server error logs
3. Create an issue on the plugin repository
4. Contact the plugin author

## License

This plugin is licensed under the GPLv2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Email validation with scoring system
- Newsletter Plugin integration
- Automated cleaning with scheduling
- Admin dashboard and settings
- Logging and CSV export
- Email notifications

## Credits

Developed by wikiwyrhead for integration with The Newsletter Plugin.

## Contributing

Contributions are welcome! Please follow WordPress coding standards and include tests for new features.
