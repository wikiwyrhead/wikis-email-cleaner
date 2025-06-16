# Translation Loading Fix for Wikis Email Cleaner

## Issue Description
WordPress 6.7.0+ introduced stricter requirements for translation loading, requiring that all translation functions (`__()`, `esc_html__()`, etc.) be called only at the 'init' action or later. The plugin was triggering PHP notices due to early translation calls.

## Root Causes Identified

### 1. Plugin Activation Hook
- Translation functions were called directly in `wikis_email_cleaner_activate()`
- Activation hooks run before 'init', causing the notices

### 2. Class Constructors and Early Methods
- Email validator class was calling translation functions in methods that could be executed before 'init'
- Fake pattern definitions included direct translation calls
- Error and warning messages used immediate translation

### 3. Activator Class
- Version check methods used translation functions during activation

## Solution Implemented

### 1. Translation Helper Class
Created `includes/class-translation-helper.php` with:
- **Safe translation checking**: `can_translate()` method checks if 'init' has run
- **Fallback system**: Returns original text if translations aren't available yet
- **Lazy loading**: Translations are loaded only when safe to do so
- **Predefined messages**: Cached error, warning, and recommendation messages
- **Parameter support**: Safe sprintf-style message formatting

### 2. Plugin Structure Changes

#### Main Plugin File (`wikis-email-cleaner.php`)
- Removed translation calls from activation hook
- Separated translation loading into dedicated 'init' hook
- Added translation helper initialization

#### Email Validator (`includes/class-email-validator.php`)
- Replaced all direct translation calls with translation helper methods
- Changed fake patterns to use message keys instead of translated strings
- Updated error, warning, and recommendation generation

#### Activator Class (`includes/class-activator.php`)
- Removed translation calls from version checking methods
- Used plain English strings for activation errors

### 3. Translation Helper Features

#### Safe Translation Methods
```php
// Basic translation with fallback
Wikis_Email_Cleaner_Translation_Helper::translate($text)

// HTML-escaped translation
Wikis_Email_Cleaner_Translation_Helper::translate_esc_html($text)

// Attribute-escaped translation
Wikis_Email_Cleaner_Translation_Helper::translate_esc_attr($text)

// Sprintf-style translation
Wikis_Email_Cleaner_Translation_Helper::translate_sprintf($text, ...$args)
```

#### Predefined Message System
```php
// Get error message by key
Wikis_Email_Cleaner_Translation_Helper::get_error_message('invalid_format')

// Get warning message with parameters
Wikis_Email_Cleaner_Translation_Helper::get_warning_message('domain_typo', 'gmail.com')

// Get recommendation message
Wikis_Email_Cleaner_Translation_Helper::get_recommendation_message('email_valid')
```

## Files Modified

### Core Files
1. `wikis-email-cleaner.php` - Main plugin file
2. `includes/class-translation-helper.php` - New translation helper (created)
3. `includes/class-email-validator.php` - Updated all translation calls
4. `includes/class-activator.php` - Removed early translation calls

### Translation Strings
All existing translation strings in `languages/wikis-email-cleaner.pot` remain unchanged and functional.

## Benefits of the Fix

### 1. WordPress 6.7.0+ Compatibility
- Eliminates all "triggered too early" PHP notices
- Follows WordPress best practices for translation loading
- Future-proof against stricter WordPress requirements

### 2. Improved Performance
- Translations are loaded only when needed
- Cached message system reduces repeated translation calls
- Lazy loading prevents unnecessary processing

### 3. Better Error Handling
- Graceful fallbacks when translations aren't available
- Consistent message formatting across the plugin
- Centralized message management

### 4. Developer Experience
- Clear separation of concerns
- Easy to add new translatable messages
- Consistent API for message retrieval

## Testing Verification

### Before Fix
```
PHP Notice: Translation loading for the wikis-email-cleaner domain was triggered too early
```

### After Fix
- No PHP notices in debug log
- All plugin functionality preserved
- Translations work correctly when available
- Fallbacks work when translations aren't loaded yet

## Backward Compatibility

The fix maintains 100% backward compatibility:
- All existing translation strings work unchanged
- Plugin functionality remains identical
- No breaking changes to public APIs
- Existing translations continue to work

## Future Maintenance

### Adding New Translatable Strings
1. Add the string to the appropriate method in `class-translation-helper.php`
2. Use the helper methods instead of direct translation calls
3. Update the `.pot` file with the new strings

### Best Practices
- Always use translation helper methods in classes that might be instantiated early
- Direct translation calls are safe in admin hooks (admin_menu, admin_notices, etc.)
- Test with WordPress debug mode enabled to catch early translation calls

This fix ensures the plugin works correctly with WordPress 6.7.0+ while maintaining all existing functionality and translation support.
