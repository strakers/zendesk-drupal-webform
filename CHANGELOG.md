# Changelog

## [Unreleased]

## [3.0.0] - 2026-07-13
### Added
- Added a PHPUnit test suite (Unit/Kernel/Functional) covering the Utility/Name helpers and the Zendesk webform handler
- Added `config/schema/zendesk_webform.schema.yml`, providing config schema for the admin settings and the handler's settings (previously missing, which strict config validation flagged)
- Added a ddev local development environment (via `ddev/ddev-drupal-contrib`) for ongoing module development

### Changed
- Modernized for Drupal 10/11: `core_version_requirement: ^10 || ^11`, PHP `>=8.1`
- Updated `zendesk/zendesk_api_client_php` dependency to `^4.1` (from `^2.2.11`)
- Updated `webform` dependency constraint to `>=6.2`
- Removed the committed `composer.lock` (not meaningful for a library-type package)
- Extracted the Zendesk ticket request-building logic out of `ZendeskHandler::postSave()` into a new `buildTicketRequest()` method, so it can be tested independently of the live API call

### Fixed
- Removed an invalid `#theme: markup` key from the handler summary render array, which logged a spurious "Theme hook markup not found" warning on every Handlers listing page view

## [9.x-2.1] - 2022-01-10
### Added
- Implemented config for automatically deleting submissions after successful Zendesk ticket creation

### Changed
- Organized Handler configuration form fields into sections
- Fixed issue preventing fields contained in sections from updating when saved
- Updated README to mention new auto-delete functionality


## [2.0.0], [9.x-2.0] - 2021-08-31
### Changed
- Updated dependencies for Drupal 9
- Switched to Drupal's flavor of semantic versioning

### Fixed
- Fixed #42 Issue preventing the Zendesk Handler Add/Edit form from loading in Drupal 9


## [1.1.0] - 2021-01-05
### Added
- Implemented new Name Utility class to polyfill expected name values

### Fixed
- Fixed error when setting requester_name to full name on the webform
- Fixed issue attaching all uploaded files, regardless of the file field limit


## [1.0.0] - 2019-07-02
### Added
- Field reference for custom ticket fields
- New helper class Utility to separate helper functions for sanity and maintenance
- Launched full release

### Changed
- Deprecated helper functions on the ZendeskHandler class, to be removed in the next minor version
- Replaced old helper methods with Utility helper methods


## [0.3.0] - 2019-07-02
### Changed
- Updated install instructions after registering with Packagist

## [0.2.1] - 2019-06-27
### Added
- Means to update Drupal webform submission with Zendesk Ticket ID, if field is present on form
- Allow for specifying any hidden field on the form as the Zendesk Ticket ID Field to be updated

### Changed
- Updated the README file with instructions for configuring storage of the submission's Zendesk Ticket ID.

### Fixed
- Configuration menu link now appears in admin menu


## [0.2.0] - 2019-06-25
### Added
- New helper function for formatting names from name field

### Changed
- Split Requester field into separate fields for name and email fields
- Allow for setting name value from possible name fields
- Updated comments with more descriptions
- Update custom field description and text
- Retrieve subdomain setting for use in link
- Add placeholder value to custom fields field

### Fixed
- Changed YAML placeholder values to use single quotations
- Only display custom fields when present
- Removed second occurence of "clean up tags" block


## [0.1.0] - 2019-05-28
### Added
- Optionally set an assignee for the Webform Handler

### Changed
- Made project description more accurate
- Updated README file with documentation

### Fixed
- Webform submissions no longer create multiple tickets


## [0.0.3] - 2019-05-22
### Added
- Parsing for CC email addresses
- Now uploads webform file attachments to Zendesk ticket

### Changed
- Updated the settings form fields with field descriptions


## [0.0.2] - 2019-05-21
### Added
- Formatting and conditional helper functions
- Token manager for placeholder parsing
- Handler settings summary for display/listing page
- Link webform submission to Zendesk ticket by ID

### Changed
- Redefine default configuration fields
- Moved Zendesk new ticket call to be triggered on SaveForm call for new form submissions only


## [0.0.1] - 2019-05-19
### Added
- This CHANGELOG file to document changes in the codebase.
- This initial code base


[Unreleased]: https://github.com/strakers/zendesk-drupal-webform/compare/v3.0.0...develop
[3.0.0]: https://github.com/strakers/zendesk-drupal-webform/compare/9.x-2.1...v3.0.0
[9.x-2.1]: https://github.com/strakers/zendesk-drupal-webform/compare/9.x-2.0...9.x-2.1
[9.x-2.0]: https://github.com/strakers/zendesk-drupal-webform/compare/v1.1.0...9.x-2.0
[2.0.0]: https://github.com/strakers/zendesk-drupal-webform/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/strakers/zendesk-drupal-webform/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/strakers/zendesk-drupal-webform/compare/v0.3.0...v1.0.0
[0.3.0]: https://github.com/strakers/zendesk-drupal-webform/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/strakers/zendesk-drupal-webform/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/strakers/zendesk-drupal-webform/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/strakers/zendesk-drupal-webform/compare/v0.0.3...v0.1.0
[0.0.3]: https://github.com/strakers/zendesk-drupal-webform/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/strakers/zendesk-drupal-webform/compare/v0.0.1...v0.0.2
[0.0.1]: https://github.com/strakers/zendesk-drupal-webform/releases/tag/v0.0.1