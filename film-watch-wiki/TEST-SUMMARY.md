# Film Watch Wiki - Comprehensive Test Suite

This document outlines all the comprehensive tests created for the Film Watch Wiki plugin.

## Test Coverage

### 1. PHPUnit Tests for Sightings (`tests/test-sightings.php`)

**23 comprehensive tests** covering:

#### Normal Expected Inputs
1. ✅ Basic sighting creation
2. ✅ Sighting with screenshot URL
3. ✅ Sighting with legacy ID (migration tracking)
4. ✅ Get sightings by movie
5. ✅ Get sightings by actor
6. ✅ Get sightings by watch
7. ✅ Get sightings by brand
8. ✅ Update sighting
9. ✅ Get statistics

#### Invalid Inputs
10. ✅ Missing movie ID
11. ✅ Missing actor ID
12. ✅ Missing watch ID
13. ✅ Missing brand ID
14. ✅ Invalid movie ID (non-existent)
15. ✅ Invalid screenshot URL
16. ✅ Invalid verification level

#### Edge Cases
17. ✅ Duplicate sighting prevention
18. ✅ Soft delete functionality
19. ✅ Restore soft-deleted sighting
20. ✅ Very long text fields (1000 characters)
21. ✅ Special characters and XSS prevention
22. ✅ Empty string vs null handling
23. ✅ Orphaned sightings cleanup (when post deleted)

---

### 2. Migration Tests (`tests/test-migration.php`)

**10 comprehensive tests** covering:

#### Normal Migration Scenarios
1. ✅ Migration class instantiation
2. ✅ Dry run mode verification
3. ✅ Verification level mapping (confirmed, verified, unverified)
4. ✅ Legacy ID storage in sightings
5. ✅ Post meta legacy ID storage
6. ✅ Screenshot URL migration
7. ✅ Source URL migration

#### Invalid Migration Inputs
8. ✅ Null data handling
9. ✅ Empty data array handling

#### Migration Edge Cases
10. ✅ Duplicate prevention during migration

---

### 3. Puppeteer Visual/Layout Tests (`tests/visual/puppeteer-layout.test.js`)

**20 comprehensive visual tests** covering:

#### Page Layout Structure
1. ✅ Movie page layout structure
2. ✅ Actor page layout structure
3. ✅ Watch page layout structure
4. ✅ Brand page layout structure
5. ✅ Custom CSS classes applied
6. ✅ Heading hierarchy (h1, h2, h3)

#### Image Loading
7. ✅ Movie page screenshots load correctly
8. ✅ Actor page thumbnails load correctly
9. ✅ Image alt text exists
10. ✅ Screenshot image dimensions are reasonable

#### Navigation & Links
11. ✅ Watch links are clickable
12. ✅ Internal navigation works
13. ✅ Many-to-many entity links present (brand, watch, actor)

#### Responsive Design
14. ✅ Mobile viewport (375x667)
15. ✅ Tablet viewport (768x1024)

#### Styling & UX
16. ✅ Brand page statistics display
17. ✅ Verification level badge styling
18. ✅ Brand page handles large datasets (46+ films)

#### Performance & Quality
19. ✅ No console errors
20. ✅ Page load performance (<10 seconds)

---

## Test Execution

### Running All Tests

```bash
# Run comprehensive test suite (PHPUnit + Puppeteer)
./run-all-tests.sh
```

### Running Individual Test Suites

```bash
# PHPUnit tests only
php run-tests.php

# Puppeteer visual tests only
cd tests/visual
npm test
```

---

## Test Statistics

### Total Test Count: **53 Tests**

- **PHPUnit Sightings**: 23 tests
- **PHPUnit Migration**: 10 tests
- **Puppeteer Visual**: 20 tests

### Coverage Areas

| Area | Tests | Status |
|------|-------|--------|
| Normal Inputs | 17 | ✅ Created |
| Invalid Inputs | 16 | ✅ Created |
| Edge Cases | 10 | ✅ Created |
| Visual/Layout | 20 | ✅ Created |

---

## Test Environment Requirements

### PHP Tests
- PHP 7.4+
- WordPress test environment
- PHPUnit or custom test runner

### Visual Tests
- Node.js 16+
- Puppeteer 21+
- Jest 29+
- Production or staging WordPress site

---

## Key Test Features

### 1. Data Integrity Tests
- Validates all required fields
- Tests foreign key relationships
- Verifies data sanitization
- Checks duplicate prevention

### 2. Security Tests
- XSS prevention
- SQL injection handling (via WordPress methods)
- Input validation
- URL validation

### 3. Migration Tests
- Legacy ID tracking
- Data mapping verification
- Screenshot URL migration
- Duplicate detection during migration

### 4. Visual Tests
- Layout structure
- Image loading
- Responsive design
- Navigation functionality
- Performance metrics

---

## Example Test Output

### PHPUnit Tests
```
Test Sightings
──────────────────────────────────────────────────────────
  ✓ test_create_basic_sighting
  ✓ test_create_sighting_with_screenshot
  ✓ test_create_sighting_missing_movie_id
  ✓ test_duplicate_sighting_prevention
  ...

23/23 tests passed
```

### Puppeteer Tests
```
PASS tests/visual/puppeteer-layout.test.js
  Film Watch Wiki - Layout and Visual Tests
    ✓ Movie page has correct layout structure (1234ms)
    ✓ Movie page screenshots load correctly (987ms)
    ✓ Internal navigation links work correctly (2145ms)
    ...

Tests: 20 passed, 20 total
```

---

## Test Maintenance

### Adding New Tests

#### PHPUnit Sightings Test
```php
public function test_your_new_test() {
    // Arrange
    $data = [...];

    // Act
    $result = FWW_Sightings::add_sighting($data);

    // Assert
    $this->assertIsInt($result);
}
```

#### Puppeteer Visual Test
```javascript
test('Your new visual test', async () => {
    await page.goto(`${BASE_URL}/your-page/`);
    const element = await page.$('.your-class');
    expect(element).toBeTruthy();
});
```

---

## Continuous Integration

These tests can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions
- name: Run PHPUnit Tests
  run: php run-tests.php

- name: Run Puppeteer Tests
  run: |
    cd tests/visual
    npm install
    npm test
```

---

## Known Limitations

1. **PHPUnit tests** require WordPress test environment setup
2. **Puppeteer tests** require a live WordPress site (production/staging)
3. Some visual tests may be sensitive to theme changes
4. Performance tests depend on server response time

---

## Future Test Additions

Potential areas for additional testing:
- [ ] Admin metabox functionality
- [ ] AJAX handlers
- [ ] TMDB API integration
- [ ] Post type registration
- [ ] Template rendering
- [ ] Accessibility (WCAG compliance)
- [ ] Load testing with large datasets

---

## Contact

For questions or issues with tests, please refer to the plugin documentation or create an issue in the repository.
