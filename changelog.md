# Changelog

All notable changes of krokedil/sign-in-with-klarna are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Changed

* Renamed `get_fresh_token` to `get_tokens`.
* Now store all tokens to `_siwk_tokens` (previously, stored the refresh token separately in metadata).
* Skipped access token validation and only check if it is expired. 

### Fixed

* Fixed inconsistent serialization and deserialization of user metadata when storing and retrieving tokens, which caused the access token to be stored as an array instead of a string.

------------------
## [1.0.0] - 2024-10-24
### Added

* Initial release of the package.
