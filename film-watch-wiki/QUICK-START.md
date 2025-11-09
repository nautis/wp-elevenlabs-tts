# Film Watch Wiki - Quick Start Guide

## Run Tests

```bash
# Comprehensive test suite (recommended)
php run-tests.php

# Demo tests
php tests/test-demo.php
```

## View Reports

```bash
# Full code review (11KB)
cat CODE-REVIEW-SUMMARY.md

# Test documentation
cat tests/README.md
```

## Test Results

```
✅ 45/45 tests passed (100%)
✅ 6 test suites
✅ Covers: TMDB API, Runtime/Money formatting, Security, Edge cases
```

## Key Files

| File | Description |
|------|-------------|
| `run-tests.php` | Main test runner (45 tests) |
| `CODE-REVIEW-SUMMARY.md` | Complete code review |
| `tests/test-*.php` | 95 PHPUnit tests (requires WordPress) |
| `composer.json` | Dependencies |

## Issues Found

### Critical (Fix First)
1. Debug logging active in production
2. No file size validation for downloads
3. Race condition in TMDB fetching

### High Priority
4. Missing type hints (PHP 7.4+)
5. No pagination in database queries
6. Invalid ID handling

## Next Steps

1. Review `CODE-REVIEW-SUMMARY.md` for detailed findings
2. Fix critical security issues
3. Add type hints to functions
4. Implement pagination
5. Run full WordPress tests (requires DB setup)

## Installation

Already installed:
- ✅ PHP 8.4.14
- ✅ Composer 2.8.12
- ✅ PHPUnit 9.6.29

## Support

- Tests: `tests/README.md`
- Review: `CODE-REVIEW-SUMMARY.md`
- Issues: https://github.com/nautis/studious-palm-tree/issues
