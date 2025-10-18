=== Admin Bar and Roles Control ===
Contributors: Robertiks
Tags: admin bar, user roles, access control, redirect, role-based redirects
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced admin bar visibility and wp-admin access control with role-specific redirect configurations and granular permission management.

== Description ==

Admin Bar and Roles Control provides comprehensive control over WordPress admin access with advanced role-based redirect functionality:

**Core Features:**
* Enable or disable admin bar control functionality
* Select specific user roles that should be restricted from wp-admin access
* Hide the admin bar for selected roles while maintaining WordPress functionality
* Advanced role-based redirect system with multiple configurations

**Advanced Redirect System:**
* Configure different redirect destinations for different user roles
* Multiple redirect options per configuration:
  * Redirect to home page
  * Redirect to a custom path on your site
  * Redirect to a completely custom URL
* Create unlimited role assignment configurations
* Tag-based role selection interface for easy management
* Prevent duplicate role assignments across configurations

**Enhanced User Experience:**
* Intuitive role assignment interface with visual feedback
* Custom confirmation dialogs (no browser alerts)
* Real-time validation and error handling
* Automatic exclusion of already-assigned roles from selection
* Clean, WordPress-native admin interface

Perfect for membership sites, client websites, multi-role organizations, or any WordPress installation requiring sophisticated backend access control with role-specific redirect behavior.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/admin-bar-and-roles-control` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Tools->Admin Bar and Roles Control screen to configure the plugin

== Frequently Asked Questions ==

= Does this plugin affect user functionality? =

No, this plugin only controls admin bar visibility and wp-admin access. All WordPress functionality remains intact for restricted users on the frontend.

= Can I set different redirect URLs for different roles? =

Yes! The enhanced version includes an advanced role assignment system that allows you to create multiple redirect configurations, each targeting specific user roles with different redirect destinations.

= How does the role assignment system work? =

Select roles in the Role Restrictions section, then use "Assign Selected Roles" to create specific redirect configurations. You can assign different roles to different redirect settings, and the system prevents duplicate assignments.

= Will this block AJAX requests? =

No, AJAX requests are specifically excluded from the wp-admin blocking to ensure frontend functionality continues to work properly.

= Can I remove role assignments? =

Yes, each role assignment configuration includes a remove button with confirmation dialog for easy management.

== Screenshots ==

1. Admin Bar and Roles Control settings page in Tools menu
2. Enhanced role selection interface with tag-based management
3. Advanced redirect options configuration
4. Role assignment management with multiple configurations

== Changelog ==

= 1.0.0 =
* Initial release with enhanced functionality
* Advanced role-based redirect system
* Multiple redirect configurations support
* Tag-based role selection interface
* Custom confirmation dialogs
* Real-time validation and error handling
* Automatic duplicate prevention
* WordPress Settings API integration
* Enhanced security with proper nonce verification
* Improved user experience with visual feedback

== Upgrade Notice ==

= 1.0.0 =
Initial release of Admin Bar and Roles Control plugin with advanced role-based redirect functionality.
