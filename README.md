# Rank Math API Manager

![Full SEO Automation in WordPress with Rank Math API Manager](assets/images/rank-math-api-wordpress-seo-automation-workflow.png)

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://php.net/)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0+-green.svg)](https://wordpress.org/)

## 📋 Overview

**Plugin Name**: Rank Math API Manager  
**Version**: 1.0.8  
**Author**: [Devora AS](https://devora.no/)  
**Description**: WordPress extension that exposes REST API endpoints to update [Rank Math](https://rankmath.com/) SEO metadata programmatically.

## 🎯 Purpose

This extension enhances the WordPress REST API with custom endpoints that allow external systems (such as n8n workflows) to update Rank Math SEO fields directly via API calls. This eliminates the need for manual SEO configuration and integrates seamlessly with automation.

## ✨ Features

### 🔧 Supported SEO Fields

- **SEO Title** (`rank_math_title`) - Meta title for search engines
- **SEO Description** (`rank_math_description`) - Meta description for search engines
- **Canonical URL** (`rank_math_canonical_url`) - Canonical URL for duplicate content
- **Focus Keyword** (`rank_math_focus_keyword`) - Primary keyword for the article

### 🌐 REST API Endpoints

#### POST `/wp-json/rank-math-api/v1/update-meta`

Updates Rank Math SEO metadata for a specific post or product.

**Parameters:**

- `post_id` (required) - ID of the post/product
- `rank_math_title` (optional) - SEO title
- `rank_math_description` (optional) - SEO description
- `rank_math_canonical_url` (optional) - Canonical URL
- `rank_math_focus_keyword` (optional) - Focus keyword

**Request Example:**

```bash
curl -X POST "https://example.com/wp-json/rank-math-api/v1/update-meta" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Authorization: Basic [base64-encoded-credentials]" \
  -d "post_id=123&rank_math_title=Optimized title&rank_math_description=SEO description&rank_math_focus_keyword=keyword"
```

**Response:**

```json
{
  "rank_math_title": "updated",
  "rank_math_description": "updated",
  "rank_math_focus_keyword": "updated"
}
```

## 🚀 Installation

### 1. Plugin Installation

1. Upload `rank-math-api-manager.php` to `/wp-content/plugins/rank-math-api-manager/`
2. Activate the plugin in WordPress admin panel
3. Verify that the plugin is active

### 2. Permissions

The plugin requires users to have `edit_posts` permissions to update metadata.

### 3. REST API Access

Ensure that the WordPress REST API is available and not blocked by security layers.

## 🔗 Integration with n8n Workflow

This plugin is specifically designed to work with Devora's n8n workflow "Write wordpress post with AI".

### Workflow Integration

1. **Automatic SEO Generation**: AI generates SEO metadata based on content
2. **Programmatic Update**: n8n sends API calls to the plugin
3. **Seamless Integration**: No manual intervention required

### n8n Node Configuration

```json
{
  "method": "POST",
  "url": "https://example.com/wp-json/rank-math-api/v1/update-meta",
  "contentType": "form-urlencoded",
  "bodyParameters": {
    "post_id": "={{ $('Post on Wordpress').first().json.id }}",
    "rank_math_title": "={{ $('Generate metatitle e metadescription').first().json.output.metatitle }}",
    "rank_math_description": "={{ $('Generate metatitle e metadescription').first().json.output.metadescription }}",
    "rank_math_focus_keyword": "={{ $('Generate metatitle e metadescription').first().json.output.metakeywords }}"
  }
}
```

## 🛡️ Security

### Authentication

- Requires WordPress Application Password or Basic Auth
- Validates user permissions (`edit_posts`)
- Sanitizes all input parameters

### Validation

- Validates that `post_id` exists
- Sanitizes text fields with `sanitize_text_field()`
- Validates URLs with `esc_url_raw()`

## 🔧 Technical Details

### Post Types

The plugin automatically supports:

- **Posts** (standard WordPress posts)
- **Products** (WooCommerce products, if WooCommerce is active)

### Meta Fields

All SEO fields are registered as post meta with:

- `show_in_rest: true` - Available via REST API
- `single: true` - Single values
- `type: string` - String data type
- `auth_callback` - Permission control

## 🗺️ Development Roadmap

### 🎯 Phase 1: Extended Field Support (High Priority)

#### 1.1 Social Media Meta Tags

- **Facebook Title** (`rank_math_facebook_title`)
- **Facebook Description** (`rank_math_facebook_description`)
- **Facebook Image** (`rank_math_facebook_image`)
- **Twitter Title** (`rank_math_twitter_title`)
- **Twitter Description** (`rank_math_twitter_description`)
- **Twitter Image** (`rank_math_twitter_image`)

#### 1.2 Advanced SEO Fields

- **Robots Meta** (`rank_math_robots`)
- **Advanced Robots** (`rank_math_advanced_robots`)
- **Primary Category** (`rank_math_primary_category`)
- **Secondary Focus Keyword** (`rank_math_secondary_focus_keyword`)
- **Tertiary Focus Keyword** (`rank_math_tertiary_focus_keyword`)

#### 1.3 Schema Markup

- **Schema Type** (`rank_math_schema_type`)
- **Article Schema Type** (`rank_math_schema_article_type`)

### 🚀 Phase 2: Bulk Operations and Read Functions

#### 2.1 Bulk Updates

```php
POST /wp-json/rank-math-api/v1/bulk-update
```

- Update multiple posts/products in one API request
- Support for batch processing
- Error handling for individual updates

#### 2.2 Read Functions

```php
GET /wp-json/rank-math-api/v1/get-meta/{post_id}
GET /wp-json/rank-math-api/v1/posts
```

- Retrieve existing SEO metadata
- List of posts with SEO information
- Filtering and sorting

#### 2.3 SEO Status Endpoint

```php
GET /wp-json/rank-math-api/v1/seo-status/{post_id}
```

- SEO score for posts
- Missing fields
- Improvement recommendations
- Schema status

### 🔄 Phase 3: Automation and Integration

#### 3.1 Conditional Updates

```php
POST /wp-json/rank-math-api/v1/smart-update
```

- Update only if fields are empty
- Update only if values are different
- Minimum/maximum length validation
- Duplicate checking

#### 3.2 Webhook Support

```php
POST /wp-json/rank-math-api/v1/webhooks
```

- Register webhooks for SEO updates
- Real-time notifications for changes
- Configurable webhook endpoints

#### 3.3 SEO Template System

```php
POST /wp-json/rank-math-api/v1/apply-template
```

- Predefined SEO templates
- Variable substitution
- Content-based templates (blog, product, page)

### 📊 Phase 4: Advanced Features

#### 4.1 SEO Validation

```php
POST /wp-json/rank-math-api/v1/validate
```

- Validation of SEO metadata before saving
- Length controls
- Keyword density
- Duplicate checking

#### 4.2 Analytics and Reporting

```php
GET /wp-json/rank-math-api/v1/analytics
```

- SEO statistics for the website
- Average SEO score
- Schema implementation rate
- Missing metadata overview

#### 4.3 Rate Limiting and Security

- Rate limiting per user/IP
- API key support
- Audit logging
- Advanced error handling

### 🌐 Phase 5: Enterprise Features

#### 5.1 Multi-site Support

```php
POST /wp-json/rank-math-api/v1/multisite-update
```

- Support for WordPress multisite
- Cross-site SEO synchronization
- Centralized SEO administration

#### 5.2 Advanced Integrations

- Google Search Console API integration
- Google Analytics 4 integration
- External SEO tool integration

## 📈 Expected Timeline

| Phase | Features               | Estimated Delivery | Status     |
| ----- | ---------------------- | ------------------ | ---------- |
| 1     | Extended Field Support | Q3 2025            | 🔄 Planned |
| 2     | Bulk Operations        | Q3 2025            | 🔄 Planned |
| 3     | Automation             | Q3 2025            | 🔄 Planned |
| 4     | Advanced Features      | Q4 2025            | 🔄 Planned |
| 5     | Enterprise             | Q1 2026            | 🔄 Planned |

## 🎯 Use Cases

### 1. **Content Syndication**

- Update SEO metadata when content is syndicated
- Cross-site SEO synchronization
- Automatic SEO optimization

### 2. **AI-driven SEO Optimization**

- Integration with AI tools
- Automatic keyword generation
- Content-based SEO suggestions

### 3. **E-commerce SEO Automation**

- Product catalog optimization
- Seasonal campaigns
- Inventory-based SEO updates

### 4. **Bulk SEO Administration**

- Mass reporting of posts
- SEO audit automation
- Competitor analysis integration

## ❓ FAQ (Frequently Asked Questions)

### 🤔 General Questions

**Q: What is Rank Math API Manager?**
A: Rank Math API Manager is a WordPress plugin that allows you to update Rank Math SEO metadata programmatically via REST API endpoints. It's specifically designed to integrate with automation like n8n workflows.

**Q: Which WordPress versions are supported?**
A: The plugin requires WordPress 5.0 or newer and PHP 7.4 or newer.

**Q: Is Rank Math SEO plugin required?**
A: Yes, the Rank Math SEO plugin must be installed and activated for this plugin to work.

### 🔧 Installation and Setup

**Q: How do I install the plugin?**
A: Upload the plugin file to `/wp-content/plugins/rank-math-api-manager/` and activate it in the WordPress admin panel.

**Q: What permissions do I need?**
A: You must have `edit_posts` permissions to use the API endpoints.

**Q: How do I set up authentication?**
A: Use WordPress Application Passwords or Basic Auth. See the installation section for details.

### 🌐 API and Integration

**Q: Which SEO fields can I update?**
A: The plugin supports SEO Title, SEO Description, Canonical URL, and Focus Keyword.

**Q: Can I use this with WooCommerce?**
A: Yes, the plugin automatically supports WooCommerce products if WooCommerce is active.

**Q: How do I integrate with n8n?**
A: See the n8n integration section in the documentation for example configuration.

**Q: Is there rate limiting on the API endpoints?**
A: The plugin uses WordPress's built-in rate limiting. For high-traffic sites, additional rate limiting is recommended.

### 🛡️ Security

**Q: Are the API endpoints secure?**
A: Yes, all endpoints require authentication and validate user permissions. All input parameters are sanitized.

**Q: How do I report security issues?**
A: Send security reports to security@devora.no. Do not create public GitHub issues for security problems.

**Q: Is sensitive data logged?**
A: No, the plugin does not log sensitive data.

### 🔄 Updates and Maintenance

**Q: How do I update the plugin?**
A: The plugin can be updated via the WordPress admin panel or by manually uploading a new version.

**Q: Are there automatic updates?**
A: Yes! The plugin includes a complete WordPress-native auto-update system that checks for new releases on GitHub and provides update notifications just like WordPress.org plugins. Users can enable/disable automatic updates and view release details.

**Q: How do I check if the plugin is working?**
A: Test the API endpoint with a simple POST request to `/wp-json/rank-math-api/v1/update-meta`.

### 🐛 Troubleshooting

**Q: I get 401 Unauthorized errors?**
A: Check that the Application Password is correctly configured and that the user has `edit_posts` permissions.

**Q: I get 404 Not Found errors?**
A: Verify that the plugin is active and that the WordPress REST API is available.

**Q: I get 400 Bad Request errors?**
A: Check that the `post_id` exists and that all parameters are correctly formatted.

**Q: WooCommerce integration doesn't work?**
A: Check that WooCommerce is installed and activated.

### 📈 Future Features

**Q: Will there be support for more SEO fields?**
A: Yes, see the roadmap section for planned features like social media meta tags and schema markup.

**Q: Will there be bulk operations?**
A: Yes, bulk updates are planned for phase 2 of development.

**Q: Will there be webhook support?**
A: Yes, webhook support is planned for phase 3.

## 🐛 Troubleshooting

### Common Problems

1. **401 Unauthorized**

   - Check that Application Password is correctly configured
   - Verify that the user has `edit_posts` permissions

2. **404 Not Found**

   - Check that the plugin is active
   - Verify that the REST API is available

3. **400 Bad Request**
   - Check that `post_id` exists
   - Validate that all parameters are correctly formatted

### Debugging

Enable WordPress debug logging to see detailed error messages:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🤝 Contributing

To contribute to this plugin:

1. Follow WordPress coding standards
2. Test changes thoroughly
3. Update documentation
4. Use descriptive commit messages
5. Follow our [Code of Conduct](CODE_OF_CONDUCT.md)

## 📞 Support

**Developed by**: Devora AS  
**Website**: https://devora.no

### 🐛 Reporting Bugs and Issues

If you discover a bug or have other problems with the plugin, you can:

1. **Create a GitHub Issue**: Visit [GitHub Issues](https://github.com/devora-as/rank-math-api-manager/issues) and create a new issue
2. **Include the following information**:
   - WordPress version
   - Plugin version
   - PHP version
   - Description of the problem
   - Steps to reproduce the problem
   - Error messages (if any)
   - Screenshots (if relevant)

### 🔒 Security Issues

**Important**: Do not report security issues via GitHub Issues. Send them to **security@devora.no** instead.

### 📧 Contact

- **General support**: Contact Devora team via [devora.no](https://devora.no)
- **Security issues**: security@devora.no
- **Code of Conduct**: conduct@devora.no

### 📋 Documentation

- **[Changelog](CHANGELOG.md)**: See changelog for all versions
- **[Security Policy](docs/SECURITY.md)**: Security policy and vulnerability reporting
- **[Code of Conduct](CODE_OF_CONDUCT.md)**: Community guidelines for contributors
- **[Norwegian Documentation](README-NORWEGIAN.md)**: Norwegian version of this documentation
- **[Norwegian Changelog](docs/CHANGELOG-NORWEGIAN.md)**: Norwegian changelog
- **[Norwegian Security Policy](docs/SECURITY-NORWEGIAN.md)**: Norwegian security policy
- **[Norwegian Code of Conduct](docs/CODE_OF_CONDUCT-NORWEGIAN.md)**: Norwegian code of conduct

---

**License**: [GPL v3](LICENSE.md) - Devora AS  
**Last Updated**: July 2025
