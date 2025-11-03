=== Vira Code ===
Contributors: viraloka
Tags: code snippets, php, javascript, css, custom code
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, modular WordPress plugin for managing and executing code snippets safely. Inspired by Fluent Snippet with enterprise-grade structure.

== Description ==

**Vira Code** is a powerful code snippet manager for WordPress that allows you to create, manage, and execute PHP, JavaScript, CSS, and HTML snippets safely from your WordPress admin dashboard.

= Features =

* **Multiple Snippet Types** - Support for PHP, JavaScript, CSS, and HTML snippets
* **File-Based Storage** - High-performance file storage system with automatic fallback to database
* **WooCommerce Library** - Pre-built WooCommerce snippets organized by category (Checkout, Cart, Pricing, etc.)
* **Safe Execution** - Sandbox PHP code execution with error handling and automatic rollback
* **Execution Scope Control** - Run snippets on frontend, admin, or both
* **Modern Admin Interface** - Clean, intuitive UI with syntax highlighting via CodeMirror
* **Storage Migration** - Seamlessly migrate between database and file storage
* **REST API** - Full REST API support for programmatic snippet management
* **Safe Mode** - Emergency switch to disable all snippets in case of errors
* **Execution Logs** - Track snippet execution with detailed logging
* **Priority Control** - Set execution order for your snippets
* **Categories & Tags** - Organize snippets with categories and tags
* **Error Handling** - Automatic error detection and snippet disabling on fatal errors
* **Conditional Logic** - Advanced conditional execution based on various criteria

= Why Choose Vira Code? =

* **Enterprise-Grade Architecture** - Built with modern PHP 8.2+ standards
* **Dual Storage System** - Choose between database or high-performance file storage
* **WooCommerce Ready** - Extensive library of WooCommerce customization snippets
* **PSR-4 Autoloading** - Clean, organized codebase using Composer
* **WordPress Coding Standards** - Follows all WordPress best practices
* **Secure** - Proper sanitization, escaping, and nonce validation throughout
* **Extensible** - Custom hooks and filters for developers
* **Well Documented** - Comprehensive inline documentation

= Developer Friendly =

Vira Code provides numerous hooks and filters for extending functionality:

* `vira_code/snippet_saved` - Fired when a snippet is saved
* `vira_code/snippet_executed` - Fired when a snippet is executed
* `vira_code/safe_mode_enabled` - Fired when safe mode is enabled
* `vira_code/snippet_types` - Filter available snippet types
* `vira_code/snippet_scopes` - Filter available execution scopes

= REST API =

Access the REST API at `/wp-json/vira/v1/` for:

* GET `/snippets` - Get all snippets
* GET `/snippets/{id}` - Get single snippet
* POST `/snippets` - Create new snippet
* PUT `/snippets/{id}` - Update snippet
* DELETE `/snippets/{id}` - Delete snippet
* POST `/snippets/{id}/toggle` - Toggle snippet status

== Installation ==

1. Upload the `vira-code` folder to the `/wp-content/plugins/` directory
2. Run `composer install` in the plugin directory (or upload with vendor folder included)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'Vira Code' in your admin menu to start creating snippets

= Requirements =

* WordPress 6.0 or higher
* PHP 8.2 or higher
* Composer autoloader

== Frequently Asked Questions ==

= Is it safe to run PHP code snippets? =

Vira Code includes several safety features:
- PHP code validation before execution
- Error handling with automatic snippet disabling
- Safe Mode to disable all snippets instantly
- Execution logs for debugging

However, always test snippets in a development environment first.

= What happens if a snippet causes an error? =

If a snippet causes an error:
1. The error is logged with details
2. The snippet is automatically marked as "error" status
3. After multiple errors, Safe Mode may be auto-enabled
4. You can use the emergency Safe Mode URL to disable all snippets

= Can I use this on a production site? =

Yes, but always:
- Test snippets thoroughly before activating
- Keep backups of your site
- Use Safe Mode if issues occur
- Monitor execution logs regularly

= How do I enable Safe Mode in an emergency? =

You can enable Safe Mode by:
1. Visiting: `yoursite.com/wp-admin/admin.php?page=vira-code-settings&vira_safe_mode=1`
2. Using the toggle button in Settings
3. Checking the Safe Mode checkbox and saving settings

= Does this work with caching plugins? =

Yes, but you may need to clear your cache after activating/deactivating snippets to see changes take effect.

= Can I export/import snippets? =

Currently, snippets are stored in the database. Import/export functionality may be added in future versions. You can use the REST API to programmatically backup and restore snippets.

== Screenshots ==

1. Snippets list with statistics
2. Snippet editor with syntax highlighting
3. Settings page with Safe Mode controls
4. Execution logs view

== Changelog ==

= 1.0.5 =
* Major security enhancements:
  - Added comprehensive dangerous function restrictions (50+ blocked functions)
  - Implemented capability checks for all snippet executions
  - Added code validation for JavaScript, CSS, and HTML snippets
  - Improved output sanitization with wp_kses_post()
  - Added protection against XSS, code injection, and malicious patterns
* Performance optimizations:
  - Implemented transient caching for snippet lists (1 hour cache)
  - Automatic cache clearing on snippet updates
  - Optimized database queries
  - Lazy loading for better performance
* WordPress 6.8.3 full compatibility verified
* Added internationalization support with .pot template
* Created security documentation (SECURITY.md)
* Added .htaccess protection for language files
* Improved error handling and logging

= 1.0.4 =
* Add file-based storage system for improved performance
* Implement WooCommerce snippet library with 9+ ready-to-use snippets
* Add storage migration tools (Database ↔ File Storage)
* Organize library snippets by category (Checkout, Cart, Pricing, Product, Shipping)
* Add library migration interface with one-click migration
* Improve snippet organization and management
* Add storage type indicators in admin interface
* Enhanced security with .htaccess protection for snippet files

= 1.0.3 =
* Add conditional logic

= 1.0.2 =
* Add custom value log entries

= 1.0.1 =
* Fix bug clear logs
* Optimize code

= 1.0.0 =
* Initial release
* PHP, JavaScript, CSS, and HTML snippet support
* Safe execution with error handling
* REST API integration
* Safe Mode functionality
* Execution logging
* Modern admin interface with CodeMirror
* Categories and tags support
* Priority-based execution order

== Upgrade Notice ==

= 1.0.0 =
Initial release of Vira Code. Requires PHP 8.2+ and WordPress 6.0+.

== Development ==

Vira Code is actively developed. Visit our GitHub repository for the latest updates, to report issues, or to contribute.

== Credits ==

* Inspired by Fluent Snippet
* Built with WordPress coding standards
* Uses CodeMirror for syntax highlighting

== Support ==

For support, please visit our website or use the WordPress.org support forums.

== License ==

This plugin is licensed under the GPL v2 or later.

Copyright (C) 2025 Viraloka

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
