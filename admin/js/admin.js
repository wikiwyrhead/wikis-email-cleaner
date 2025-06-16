/**
 * Admin JavaScript for Wikis Email Cleaner
 *
 * @package Wikis_Email_Cleaner
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main admin object
    var WikisEmailCleaner = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Scan emails button
            $('#wikis-scan-emails').on('click', this.scanEmails);
            
            // Export results button
            $('#wikis-export-results').on('click', this.exportResults);
            
            // Export logs button
            $('#wikis-export-logs').on('click', this.exportLogs);
            
            // Settings form validation
            $('form[action=""]').on('submit', this.validateSettings);
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
                
                // Store active tab in localStorage
                localStorage.setItem('wikis_email_cleaner_active_tab', target);
            });
            
            // Restore active tab from localStorage
            var activeTab = localStorage.getItem('wikis_email_cleaner_active_tab');
            if (activeTab && $(activeTab).length) {
                $('.nav-tab[href="' + activeTab + '"]').trigger('click');
            }
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to help text
            $('.description').each(function() {
                var $this = $(this);
                if ($this.text().length > 100) {
                    $this.attr('title', $this.text());
                }
            });
        },

        /**
         * Scan emails via AJAX
         */
        scanEmails: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Disable button and show loading
            $button.prop('disabled', true)
                   .html('<span class="wikis-spinner"></span>' + wikisEmailCleaner.strings.scanning);
            
            // Show progress container
            $('#wikis-scan-progress').show();
            $('#wikis-scan-results').hide();
            
            // Reset progress
            WikisEmailCleaner.updateProgress(0, 0);
            
            $.ajax({
                url: wikisEmailCleaner.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wikis_email_cleaner_scan',
                    nonce: wikisEmailCleaner.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WikisEmailCleaner.showScanResults(response.data);
                    } else {
                        WikisEmailCleaner.showError(response.data || wikisEmailCleaner.strings.error);
                    }
                },
                error: function() {
                    WikisEmailCleaner.showError(wikisEmailCleaner.strings.error);
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text(originalText);
                    $('#wikis-scan-progress').hide();
                }
            });
        },

        /**
         * Export results via AJAX
         */
        exportResults: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true)
                   .html('<span class="wikis-spinner"></span>Exporting...');
            
            $.ajax({
                url: wikisEmailCleaner.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wikis_email_cleaner_export',
                    nonce: wikisEmailCleaner.nonce
                },
                success: function(response) {
                    if (response.success && response.data.export_url) {
                        // Create download link
                        var link = document.createElement('a');
                        link.href = response.data.export_url;
                        link.download = '';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        WikisEmailCleaner.showSuccess('Export completed successfully!');
                    } else {
                        WikisEmailCleaner.showError(response.data || 'Export failed');
                    }
                },
                error: function() {
                    WikisEmailCleaner.showError('Export failed');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Export logs (same as export results but for logs page)
         */
        exportLogs: function(e) {
            WikisEmailCleaner.exportResults.call(this, e);
        },

        /**
         * Update progress bar
         */
        updateProgress: function(current, total) {
            var percentage = total > 0 ? (current / total) * 100 : 0;
            
            $('.wikis-progress-fill').css('width', percentage + '%');
            $('#wikis-progress-current').text(current);
            $('#wikis-progress-total').text(total);
        },

        /**
         * Show enhanced scan results
         */
        showScanResults: function(data) {
            var html = '<div class="wikis-scan-summary">';
            html += '<h4>📊 Scan Complete!</h4>';

            // Summary statistics
            html += '<div class="wikis-scan-stats">';
            html += '<div class="stat-grid">';
            html += '<div class="stat-item"><span class="number">' + data.total_scanned + '</span><span class="label">Total Scanned</span></div>';
            html += '<div class="stat-item invalid"><span class="number">' + data.invalid_count + '</span><span class="label">Invalid</span></div>';

            if (data.questionable_count !== undefined) {
                html += '<div class="stat-item warning"><span class="number">' + data.questionable_count + '</span><span class="label">Questionable</span></div>';
            }

            if (data.unsubscribed_count !== undefined && data.unsubscribed_count > 0) {
                html += '<div class="stat-item action"><span class="number">' + data.unsubscribed_count + '</span><span class="label">Unsubscribed</span></div>';
            }
            html += '</div></div>';

            // Categories breakdown
            if (data.categories) {
                html += '<div class="wikis-categories">';
                html += '<h5>📋 Issues Found:</h5>';
                html += '<ul class="category-list">';

                Object.keys(data.categories).forEach(function(category) {
                    if (data.categories[category] > 0) {
                        var categoryName = category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        html += '<li><strong>' + categoryName + ':</strong> ' + data.categories[category] + '</li>';
                    }
                });

                html += '</ul></div>';
            }

            // Settings used
            if (data.settings_used) {
                html += '<div class="wikis-settings-used">';
                html += '<h5>⚙️ Validation Settings:</h5>';
                html += '<ul>';
                html += '<li><strong>Deep Validation:</strong> ' + (data.settings_used.deep_validation ? 'Enabled' : 'Disabled') + '</li>';
                html += '<li><strong>Minimum Score:</strong> ' + data.settings_used.minimum_score + '</li>';
                html += '<li><strong>Auto-Clean:</strong> ' + (data.settings_used.auto_clean_enabled ? 'Enabled' : 'Disabled') + '</li>';
                html += '</ul></div>';
            }

            html += '</div>';

            // Detailed results table
            if (data.results && data.results.length > 0) {
                html += '<div class="wikis-invalid-emails">';
                html += '<h4>🚫 Invalid Emails Details:</h4>';
                html += '<div class="table-responsive">';
                html += '<table class="wp-list-table widefat striped">';
                html += '<thead><tr><th>Email</th><th>Score</th><th>Risk Level</th><th>Issues</th><th>Subscriber ID</th></tr></thead>';
                html += '<tbody>';

                data.results.forEach(function(result) {
                    var riskLevel = WikisEmailCleaner.getRiskLevel(result.score);
                    var riskClass = riskLevel.toLowerCase();

                    html += '<tr>';
                    html += '<td><code>' + result.email + '</code></td>';
                    html += '<td><span class="score score-' + riskClass + '">' + result.score + '</span></td>';
                    html += '<td><span class="risk-level risk-' + riskClass + '">' + riskLevel + '</span></td>';
                    html += '<td class="issues-cell">';

                    if (result.errors && result.errors.length > 0) {
                        html += '<div class="error-list"><strong>❌ Errors:</strong><ul>';
                        result.errors.forEach(function(error) {
                            html += '<li>' + error + '</li>';
                        });
                        html += '</ul></div>';
                    }

                    if (result.warnings && result.warnings.length > 0) {
                        html += '<div class="warning-list"><strong>⚠️ Warnings:</strong><ul>';
                        result.warnings.forEach(function(warning) {
                            html += '<li>' + warning + '</li>';
                        });
                        html += '</ul></div>';
                    }

                    html += '</td>';
                    html += '<td>' + (result.subscriber_id || 'N/A') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                html += '</div></div>';
            }

            $('#wikis-results-content').html(html);
            $('#wikis-scan-results').show();

            // Scroll to results
            $('html, body').animate({
                scrollTop: $('#wikis-scan-results').offset().top - 50
            }, 500);
        },

        /**
         * Get risk level based on score
         */
        getRiskLevel: function(score) {
            if (score >= 80) return 'LOW';
            if (score >= 60) return 'MEDIUM';
            if (score >= 40) return 'HIGH';
            return 'CRITICAL';
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after h1
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);
        },

        /**
         * Validate settings form
         */
        validateSettings: function(e) {
            var isValid = true;
            var errors = [];
            
            // Validate minimum score
            var minScore = parseInt($('input[name="wikis_email_cleaner_settings[minimum_score]"]').val());
            if (isNaN(minScore) || minScore < 0 || minScore > 100) {
                errors.push('Minimum score must be between 0 and 100.');
                isValid = false;
            }
            
            // Validate subscription minimum score
            var subMinScore = parseInt($('input[name="wikis_email_cleaner_settings[subscription_minimum_score]"]').val());
            if (isNaN(subMinScore) || subMinScore < 0 || subMinScore > 100) {
                errors.push('Subscription minimum score must be between 0 and 100.');
                isValid = false;
            }
            
            // Validate email
            var email = $('input[name="wikis_email_cleaner_settings[notification_email]"]').val();
            if (email && !WikisEmailCleaner.isValidEmail(email)) {
                errors.push('Please enter a valid notification email address.');
                isValid = false;
            }
            
            // Validate log retention days
            var retentionDays = parseInt($('input[name="wikis_email_cleaner_settings[log_retention_days]"]').val());
            if (isNaN(retentionDays) || retentionDays < 1 || retentionDays > 365) {
                errors.push('Log retention days must be between 1 and 365.');
                isValid = false;
            }
            
            // Validate batch size
            var batchSize = parseInt($('input[name="wikis_email_cleaner_settings[batch_size]"]').val());
            if (isNaN(batchSize) || batchSize < 10 || batchSize > 1000) {
                errors.push('Batch size must be between 10 and 1000.');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                WikisEmailCleaner.showError('Please fix the following errors:<br>• ' + errors.join('<br>• '));
                return false;
            }
            
            return true;
        },

        /**
         * Validate email address
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Confirm dangerous actions
         */
        confirmAction: function(message) {
            return confirm(message || 'Are you sure you want to perform this action?');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WikisEmailCleaner.init();
    });

    // Make WikisEmailCleaner available globally
    window.WikisEmailCleaner = WikisEmailCleaner;

})(jQuery);

/**
 * Dismiss donation section
 */
function wikisDismissDonation() {
    if (confirm('Are you sure you want to hide the donation message? You can always support the development later.')) {
        // Hide the donation section
        document.getElementById('wikis-donation-section').style.display = 'none';

        // Save preference via AJAX
        jQuery.ajax({
            url: wikisEmailCleaner.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wikis_email_cleaner_dismiss_donation',
                nonce: wikisEmailCleaner.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Donation message dismissed successfully');
                }
            },
            error: function() {
                console.log('Failed to save donation dismissal preference');
            }
        });
    }
}
