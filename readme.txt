=== Rank Math API Manager ===
Contributors: devoraas
Tags: seo, rank-math, api, rest-api, automation
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WordPress extension that exposes REST API endpoints to update Rank Math SEO metadata programmatically.

== Description ==

This extension enhances the WordPress REST API with custom endpoints that allow external systems (such as n8n workflows) to update Rank Math SEO fields directly via API calls.

= Features =

* REST API endpoints for Rank Math metadata
* Support for SEO Title, Description, Canonical URL, and Focus Keyword
* Dependency checking for Rank Math SEO
* Secure API access with proper authentication
* Compatible with WordPress posts and WooCommerce products

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Rank Math SEO extension

== Installation ==

1. Download the extension from GitHub
2. Upload to your WordPress site
3. Activate the extension
4. Ensure Rank Math SEO is installed and active

== Frequently Asked Questions ==

= Does this work with WordPress.org? =

This extension is currently distributed via GitHub only.

= What Rank Math fields are supported? =

* SEO Title (rank_math_title)
* SEO Description (rank_math_description)  
* Canonical URL (rank_math_canonical_url)
* Focus Keyword (rank_math_focus_keyword)

== Changelog ==

= 1.0.7 =
* Added dependency checking system
* Improved security and validation
* Enhanced admin notices
* Fixed Plugin Check compatibility issues

= 1.0.6 =
* Initial stable release
* Basic REST API functionality
* Core SEO field support

== Upgrade Notice ==

= 1.0.7 =
This version includes important security improvements and dependency checking.