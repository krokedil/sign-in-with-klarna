# Changelog

All notable changes of krokedil/sign-in-with-klarna are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

------------------
## [1.0.6] - 2025-06-09
### Fixed

* Resolved notice "_load_textdomain_just_in_time was called incorrectly".

### Added

* Added check to determine if SIWK is marked as unavailable for the current Klarna credentials. If so, SIWK will be disabled.

## [1.0.5] - 2025-01-13
### Fixed
* Fixed faulty formatting for scope and removed invalid scope.

## [1.0.4] - 2024-11-12
### Changed
* Updated the product description as shown on the settings page.

## [1.0.3] - 2024-10-30
### Fixed

* Fixed the setting for enabling the Klarna login button not being used properly, causing the login button to show even when it was disabled.

## [1.0.2] - 2024-10-28
### Fixed

* Fixed not defaulting settings to proper values when they are not set yet.

## [1.0.1] - 2024-10-25
### Changed

* Renamed `get_fresh_token` to `get_tokens`.
* Now store all tokens to `_siwk_tokens` (previously, stored the refresh token separately in metadata).
* Skipped access token validation and only check if it is expired.

### Fixed

* Fixed inconsistent serialization and deserialization of user metadata when storing and retrieving tokens, which caused the access token to be stored as an array instead of a string.
* Apply button styling set in the settings to the "Sign in with Klarna" button.
* Resolve console warnings caused by renamed style attribute values in the Klarna SDK.

## [1.0.0] - 2024-10-24
### Added

* Initial release of the package.
