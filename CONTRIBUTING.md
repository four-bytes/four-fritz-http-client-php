# Contributing to Four Fritz HTTP Client

Thank you for your interest in contributing to the Four Fritz HTTP Client! This document provides guidelines for contributing to the project.

## 📋 Code of Conduct

This project follows a code of conduct that ensures a welcoming environment for everyone. Please be respectful and constructive in all interactions.

## 🚀 Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally
3. **Install dependencies**: `composer install`
4. **Create a branch** for your feature: `git checkout -b feature/amazing-feature`

## 🧪 Development Setup

```bash
# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer style

# Run static analysis
composer analyse
```

## 📝 Contribution Guidelines

### Code Style

- Follow **PSR-12** coding standards
- Use **type declarations** for all parameters and return types
- Add **PHPDoc comments** for all public methods
- Keep methods **focused and small**

### Testing

- Add **unit tests** for new functionality
- Ensure **100% test coverage** for new code
- Test with **real FritzBox devices** when possible
- Include **integration tests** for API endpoints

### Documentation

- Update **README.md** if adding new features
- Add **code examples** for new functionality
- Document **configuration options**
- Update **CHANGELOG.md**

## 🔧 Types of Contributions

### 🐛 Bug Reports

When reporting bugs, please include:

- **FritzBox model** and **FritzOS version**
- **PHP version** and **environment details**
- **Steps to reproduce** the issue
- **Expected vs actual behavior**
- **Code examples** if possible

### 💡 Feature Requests

For new features:

- **Describe the use case** clearly
- **Explain the benefit** to users
- **Consider backwards compatibility**
- **Provide implementation ideas** if possible

### 🔨 Code Contributions

#### New Services

When adding new services (WLAN, Call, SmartHome):

1. **Extend AbstractFritzClient**
2. **Follow NasService pattern**
3. **Add comprehensive tests**
4. **Document all methods**
5. **Include usage examples**

#### API Improvements

- **Maintain backwards compatibility**
- **Add deprecation warnings** for breaking changes
- **Update version accordingly**

## 🧪 Testing Guidelines

### Unit Tests

```php
<?php
namespace Four\FritzHttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Four\FritzHttpClient\Services\NasService;

class NasServiceTest extends TestCase
{
    public function testFileListParsing(): void
    {
        // Test implementation
    }
}
```

### Integration Tests

- Test with **real FritzBox devices**
- Use **environment variables** for credentials
- **Skip tests** if no device available
- **Clean up** test data after tests

## 📦 Release Process

1. **Update version** in composer.json
2. **Update CHANGELOG.md**
3. **Ensure all tests pass**
4. **Create release tag**
5. **Publish to Packagist**

## 🔍 Code Review

All contributions go through code review:

- **Keep PRs focused** and small
- **Respond to feedback** promptly
- **Update code** based on suggestions
- **Ensure CI passes**

## 📞 Getting Help

- 💬 **GitHub Discussions** for questions
- 🐛 **GitHub Issues** for bugs
- 📧 **Email maintainers** for security issues

## 🎯 Areas Needing Help

Current priorities:

- [ ] **File Upload Implementation**
- [ ] **WLAN Service Development**
- [ ] **Call History Service**
- [ ] **Smart Home Device Control**
- [ ] **Documentation Improvements**
- [ ] **Test Coverage Expansion**

## 📜 License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for helping make the Four Fritz HTTP Client better! 🎉