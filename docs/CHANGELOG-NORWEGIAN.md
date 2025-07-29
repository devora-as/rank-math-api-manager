# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.7] - 2025-07-XX

### Added

- ✅ **Automatiske Oppdateringer fra GitHub**: Komplett implementering av automatisk oppdateringssystem
  - GitHub API-integrasjon for å sjekke nyeste releases
  - Sikker nedlasting og installasjon av oppdateringer
  - Admin-notifikasjoner for tilgjengelige oppdateringer
  - Manuell oppdateringssjekk-grensesnitt
  - Oppdateringslogging og feilhåndtering
  - Rate limiting og caching for API-kall
- Enhanced plugin dependency checking
- Comprehensive documentation in /docs folder
- WordPress Plugin Check (PCP) compatibility improvements

### Changed

- Improved code structure with singleton pattern
- Enhanced security measures with proper nonce verification
- Better error handling and logging throughout the plugin
- Updated plugin version to 1.0.7

### Security

- Implemented proper input validation and sanitization
- Added capability checks for all admin functions
- Secure API communication with GitHub
- Rate limiting for update checks

## [Unreleased]

### Changed

- Improved error handling and validation
- Enhanced security measures
- Better integration with n8n workflows

### Fixed

- Various bug fixes and performance improvements

## [1.0.6] - 2025-07-XX

### Removed

- Schema markup-related code (moved to roadmap for future development)

### Changed

- Improved code quality and WordPress standards compliance
- Updated plugin description

## [1.0.5] - 2025-XX-XX

### Added

- Support for WooCommerce products
- Enhanced error handling

### Changed

- Improved validation mechanisms

## [1.0.0] - 2025-XX-XX

### Added

- Initial release
- Basic SEO field support (SEO Title, SEO Description, Canonical URL, Focus Keyword)
- REST API endpoints for metadata updates
- Integration with n8n workflows
- Support for WordPress posts and WooCommerce products
- Authentication and permission validation
- Input sanitization and validation
