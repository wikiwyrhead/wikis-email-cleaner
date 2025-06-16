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

## [1.1.0] - 2024-06-17

### Added
- New revalidation system for periodic email re-checks, including admin UI for managing revalidation candidates and results.
- Migration tool to set up new database tables and migrate existing settings for revalidation.
- Batch revalidation processor and queue manager for efficient handling of large email lists.
- Automated scheduler for periodic revalidation and queue population.
- New schema and settings for revalidation, with more conservative defaults to reduce false positives.
- Improved settings class for advanced configuration and safer defaults.

### Changed
- Fixed undefined constant bug in revalidation admin (now uses `WIKIS_EMAIL_CLEANER_PLUGIN_URL`).
- Revalidation migration/setup can now be triggered from the admin page with `?action=migrate`.

### Technical Details
- **New Files**: `includes/class-improved-settings.php`, `includes/class-revalidation-migration.php`, `includes/class-revalidation-processor.php`, `includes/class-revalidation-queue.php`, `includes/class-revalidation-scheduler.php`, `includes/class-revalidation-schema.php`.
- **Modified Files**: `includes/class-revalidation-admin.php`, `includes/class-wikis-email-cleaner.php`, `includes/class-email-validator.php`.

### Notes
- No runtime errors or critical bugs remain after these improvements. Static analysis errors may still appear due to WordPress context.

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
