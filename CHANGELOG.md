# Changelog

All notable changes to the Wikis Email Cleaner plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-06-16

### Added
- Initial release of Wikis Email Cleaner plugin
- Email validation engine with multi-step validation process
- Integration with The Newsletter Plugin
- Admin dashboard with statistics and controls
- Manual email scanning functionality
- Automated scheduled email cleaning
- Comprehensive logging system
- CSV export functionality for validation results and logs
- Settings page with configurable validation parameters
- Support for both basic and deep email validation
- Email scoring system (0-100 scale)
- Detection of disposable email addresses
- Role-based email identification
- Domain typo detection and suggestions
- Fake email pattern recognition
- SMTP server validation (deep mode)
- MX record verification
- Database tables for storing validation logs and scan summaries
- WordPress hooks and filters for extensibility
- Translation support with POT file
- Security features including nonce verification and capability checks
- Batch processing to prevent timeouts
- Email notifications for scan completion
- Data preservation options during uninstall
- Debug logging support
- Responsive admin interface
- WordPress coding standards compliance

### Features
- **Email Validation**: Multi-step validation including syntax, disposable domains, role-based emails, domain verification, and SMTP testing
- **Automatic Cleaning**: Optional automatic unsubscription of invalid emails based on configurable thresholds
- **Manual Scanning**: On-demand email validation with real-time progress tracking
- **Scheduled Scans**: Automated email cleaning on hourly, daily, or weekly schedules
- **Comprehensive Logging**: Detailed validation logs with export capabilities
- **Dashboard Analytics**: Statistics showing total subscribers, validation results, and average scores
- **Settings Management**: Configurable validation rules, thresholds, and automation preferences
- **Newsletter Integration**: Seamless integration with The Newsletter Plugin using official hooks
- **Export Functionality**: CSV export for validation results and historical logs
- **Security**: Proper sanitization, validation, and capability checks throughout
- **Performance**: Batch processing and caching to handle large subscriber lists
- **Extensibility**: WordPress hooks and filters for custom integrations

### Technical Details
- **WordPress Compatibility**: 5.5 or higher
- **PHP Compatibility**: 7.4 or higher
- **Database**: Creates two custom tables for logs and scan summaries
- **Dependencies**: Requires The Newsletter Plugin to be active
- **Text Domain**: wikis-email-cleaner
- **License**: GPLv2 or later

### Security
- All user inputs are properly sanitized and validated
- Database operations use prepared statements
- Capability checks ensure only authorized users can access features
- Nonce verification for all AJAX requests
- Output escaping for all displayed data
- Secure file handling for exports

### Performance
- Batch processing prevents timeouts on large subscriber lists
- Validation result caching reduces redundant processing
- Configurable batch sizes for optimal performance
- Sleep intervals during processing to prevent server overload
- Efficient database queries with proper indexing

### Internationalization
- Full translation support with comprehensive POT file
- All user-facing strings are properly internationalized
- Support for RTL languages
- Contextual translation strings for better accuracy

## [Unreleased]

### Planned Features
- Advanced email reputation checking
- Integration with external email validation services
- Bulk email import/export functionality
- Advanced reporting and analytics
- Email engagement tracking
- Whitelist/blacklist management
- API endpoints for external integrations
- Multi-site network support
- Advanced scheduling options
- Email template validation
- Bounce handling improvements
- Spam complaint processing enhancements

---

## Version History

- **1.0.0** - Initial release with core email validation and cleaning functionality

## Support

For support, bug reports, and feature requests, please visit:
- GitHub Issues: https://github.com/wikiwyrhead/wikis-email-cleaner/issues
- Plugin Support: https://wordpress.org/support/plugin/wikis-email-cleaner/

## Contributing

Contributions are welcome! Please read our contributing guidelines and submit pull requests to our GitHub repository.

## License

This plugin is licensed under the GPLv2 or later license. See the LICENSE file for details.
