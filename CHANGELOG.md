# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-04-07

### Fixed
- `AbstractFritzClient`: All cURL requests now use a private `curlExec()` helper
  that captures the response body via `CURLOPT_WRITEFUNCTION`. FritzBox and
  MyFRITZ send `Connection: close` and reset the TCP connection immediately after
  the response body; PHP libcurl with OpenSSL raises `CURLE_RECV_ERROR` (errno 56)
  on that reset even though all bytes were already received. The helper treats
  errno 56 as a soft error and returns the buffered body, fixing authentication
  failures when connecting via MyFRITZ URLs.
- `authenticate()`: Removed the redundant `isReachable()` pre-check that caused a
  double HTTP round-trip; reachability is now implicitly verified by `getLoginState()`.

## [Unreleased]

### Added
- Initial release of Four Fritz HTTP Client
- Complete FritzBox authentication system (PBKDF2 + MD5)
- NAS service with file management operations
- Session management with automatic SID caching
- Comprehensive logging with PSR-3 compliance
- MyFRITZ remote access support

### Features
- **Authentication**: Support for both modern PBKDF2 and legacy MD5 authentication
- **NAS Operations**: Browse, download, delete, rename files and folders
- **Session Management**: Automatic session renewal and caching
- **Error Handling**: Comprehensive error handling with detailed logging
- **MyFRITZ Support**: Remote access via MyFRITZ URLs
- **Pagination**: Support for large directory listings
- **Bulk Operations**: Delete multiple files in single request

### Technical
- **PHP 8.1+**: Modern PHP with full type declarations
- **PSR-4**: Autoloading compliance
- **PSR-3**: Logger interface support
- **Zero Dependencies**: Minimal external dependencies
- **Clean Architecture**: Service-oriented design for extensibility

## [1.0.0] - 2025-01-XX

### Added
- Initial stable release
- Complete NAS service implementation
- Authentication and session management
- Comprehensive documentation and examples

---

**Note**: This changelog will be updated as the project evolves. For detailed changes, see the [Git commit history](https://github.com/four-bytes/four-fritz-http-client-php/commits/main).