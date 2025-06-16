# Changelog

All notable changes to the Wikis Email Cleaner plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-06-16

### Added
- Initial release of Wikis Email Cleaner plugin
- Email validation engine with multi-step validation process (syntax, domain, MX, SMTP, disposable, role-based, typo, fake pattern)
- Integration with The Newsletter Plugin
- Admin dashboard with statistics and controls
- Manual and scheduled email scanning functionality
- Comprehensive logging system with CSV export
- Settings page with configurable validation parameters
- Support for both basic and deep email validation
- Email scoring system (0-100 scale)
- Detection of disposable, role-based, and fake email addresses
- Domain typo detection and suggestions
- SMTP server and MX record validation (deep mode)
- Database tables for storing validation logs and scan summaries
- WordPress hooks and filters for extensibility
- Translation support with POT file
- Security features including nonce verification, capability checks, and input sanitization
- Batch processing to prevent timeouts
- Email notifications for scan completion
- Data preservation options during uninstall
- Debug logging support
- Responsive admin interface
- WordPress coding standards compliance

### Technical Details
- **WordPress Compatibility**: 5.5 or higher
- **PHP Compatibility**: 7.4 or higher
- **Database**: Creates two custom tables for logs and scan summaries
- **Dependencies**: Requires The Newsletter Plugin to be active
- **Text Domain**: wikis-email-cleaner
- **License**: GPLv2 or later

---

## [Unreleased]
- Planned: WooCommerce integration
- Planned: REST API endpoints for external validation
- Planned: More granular logging and reporting options
- Planned: Additional language translations

## Support

For support, bug reports, and feature requests, please visit:
- GitHub Issues: https://github.com/wikiwyrhead/wikis-email-cleaner/issues
- Plugin Support: https://wordpress.org/support/plugin/wikis-email-cleaner/

## Contributing

Contributions are welcome! Please read our contributing guidelines and submit pull requests to our GitHub repository.

## License

This plugin is licensed under the GPLv2 or later license. See the LICENSE file for details.
