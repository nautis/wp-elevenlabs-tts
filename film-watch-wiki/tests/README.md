# Film Watch Wiki - Unit Tests

Comprehensive unit test suite for the Film Watch Wiki WordPress plugin.

## Test Coverage

### 1. TMDB API Tests (`test-tmdb-api.php`)
- ✅ API key retrieval and configuration
- ✅ Language settings
- ✅ Cache duration configuration
- ✅ Movie search with various inputs (empty, valid, invalid)
- ✅ People search functionality
- ✅ Image URL generation
- ✅ Movie details retrieval
- ✅ Person details retrieval
- ✅ Cache clearing
- ✅ US certification retrieval
- ✅ Error handling for missing API keys
- ✅ Edge cases and invalid inputs

**Total Tests: 19**

### 2. Movie Functions Tests (`test-movie-functions.php`)
- ✅ Movie data retrieval
- ✅ Runtime formatting (various durations, edge cases)
- ✅ Money formatting (billions, millions, thousands)
- ✅ Movie poster generation
- ✅ Movie backdrop generation
- ✅ Cast retrieval
- ✅ Watch sightings queries
- ✅ Director extraction
- ✅ Poster download and thumbnail setting
- ✅ Edge cases (empty, null, negative values)
- ✅ XSS protection in output

**Total Tests: 35**

### 3. AJAX Handlers Tests (`test-ajax-handlers.php`)
- ✅ CSRF/Nonce verification
- ✅ Movie search endpoint
- ✅ Movie details endpoint
- ✅ Input validation and sanitization
- ✅ SQL injection protection
- ✅ XSS attack prevention
- ✅ Authorization checks
- ✅ Edge cases (long queries, special characters)
- ✅ Error handling

**Total Tests: 17**

### 4. Post Types Tests (`test-post-types.php`)
- ✅ Post type registration (movies, actors, watches)
- ✅ Post type properties and capabilities
- ✅ Post type supports features
- ✅ URL slugs and permalinks
- ✅ Labels and menu icons
- ✅ CRUD operations
- ✅ Query functionality
- ✅ Archive URLs

**Total Tests: 24**

## Setup Instructions

### 1. Install WordPress Test Suite

```bash
# Set up test database (one time only)
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Or specify custom values:
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version>
```

### 2. Install PHPUnit

```bash
# Using Composer (recommended)
composer require --dev phpunit/phpunit ^9.0
composer require --dev yoast/phpunit-polyfills

# Or install globally
composer global require phpunit/phpunit
```

### 3. Set Environment Variable

```bash
# Add to ~/.bashrc or ~/.zshrc
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
```

## Running Tests

### Run All Tests

```bash
# Using Composer
composer test

# Using PHPUnit directly
vendor/bin/phpunit

# With coverage report
vendor/bin/phpunit --coverage-html coverage/
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/test-tmdb-api.php
vendor/bin/phpunit tests/test-movie-functions.php
vendor/bin/phpunit tests/test-ajax-handlers.php
vendor/bin/phpunit tests/test-post-types.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter test_get_api_key_empty
vendor/bin/phpunit --filter test_format_runtime_valid
```

### Run with Verbosity

```bash
vendor/bin/phpunit --verbose
vendor/bin/phpunit --debug
```

## Test Categories

### Normal Expected Inputs ✅
- Valid movie IDs
- Proper TMDB data structures
- Standard runtime values
- Typical money amounts
- Valid search queries

### Edge Cases ✅
- Empty strings
- Null values
- Zero values
- Very large numbers (24+ hour runtimes, $100B+ budgets)
- Special characters in queries
- Very long queries (500+ characters)
- Missing or incomplete TMDB data

### Invalid Inputs ✅
- Negative movie IDs
- Non-numeric values where numbers expected
- Missing required parameters
- Invalid nonces
- Missing API keys
- SQL injection attempts
- XSS attempts

## Security Testing

The test suite includes specific tests for:
- **CSRF Protection**: Nonce verification in AJAX handlers
- **SQL Injection**: Prepared statements and sanitization
- **XSS Prevention**: Output escaping in templates
- **Authorization**: Capability checks for admin functions
- **Input Sanitization**: All user inputs properly sanitized

## Continuous Integration

These tests can be integrated with CI/CD pipelines:

```yaml
# Example GitHub Actions workflow
- name: Run PHPUnit tests
  run: vendor/bin/phpunit --coverage-clover coverage.xml
```

## Known Limitations

1. **API Mocking**: Tests that require actual TMDB API calls will fail without a valid API key. Consider adding API mocking for CI/CD.

2. **Database Tables**: Tests assume the Film Watch Database tables exist. Some tests may fail if these tables are not present.

3. **WordPress Version**: Tests are designed for WordPress 5.0+ and PHP 7.4+.

## Adding New Tests

When adding new functionality:

1. Create test file in `tests/` directory
2. Extend `WP_UnitTestCase` or `WP_Ajax_UnitTestCase`
3. Follow naming convention: `test-{feature-name}.php`
4. Include tests for:
   - Normal operation
   - Edge cases
   - Invalid inputs
   - Security concerns

## Code Coverage

To generate code coverage report:

```bash
vendor/bin/phpunit --coverage-html coverage/
open coverage/index.html
```

Target coverage: **80%+** for all critical functions.

## Support

For issues or questions:
- Check WordPress PHPUnit documentation
- Review test examples in this suite
- Open issue on GitHub repository
