# Code Refactoring Plan - Film Watch Wiki

## Priority: CRITICAL ⚠️

### 1. Remove Debug Logging from Production
**File:** `includes/template-loader.php` (lines 90-108)

**Issue:** Debug logs always active, exposing internal paths

**Current Code:**
```php
error_log('FWW Template Filter Running - Template: ' . $template);
error_log('FWW is_singular check: ' . (is_singular(...) ? 'YES' : 'NO'));
error_log('FWW post type: ' . get_post_type());
```

**Refactored:**
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('FWW Template Filter Running - Template: ' . $template);
    error_log('FWW is_singular check: ' . (is_singular(...) ? 'YES' : 'NO'));
    error_log('FWW post type: ' . get_post_type());
}
```

---

### 2. Fix Invalid Movie ID Handling
**File:** `includes/tmdb-api.php` (line 167-168)

**Issue:** `intval('')` returns 0, causing invalid API calls

**Current Code:**
```php
public static function get_movie($movie_id) {
    $response = self::make_request('movie/' . intval($movie_id));
```

**Refactored:**
```php
public static function get_movie($movie_id) {
    // Validate movie ID
    if (empty($movie_id) || !is_numeric($movie_id) || $movie_id <= 0) {
        return new WP_Error('invalid_movie_id', 'Invalid movie ID provided');
    }

    $movie_id = intval($movie_id);
    $response = self::make_request('movie/' . $movie_id);
```

---

### 3. Fix Race Condition in TMDB Data Fetch
**File:** `includes/movie-functions.php` (lines 27-34)

**Issue:** Concurrent requests can both fetch data

**Current Code:**
```php
// If no cached data or it's old, fetch from TMDB
if (empty($tmdb_data) && !empty($tmdb_id)) {
    $tmdb_data = FWW_TMDB_API::get_movie($tmdb_id);

    if ($tmdb_data) {
        // Cache the data
        update_post_meta($post_id, '_fww_tmdb_data', $tmdb_data);
    }
}
```

**Refactored:**
```php
// If no cached data or it's old, fetch from TMDB
if (empty($tmdb_data) && !empty($tmdb_id)) {
    // Use transient locking to prevent race conditions
    $lock_key = 'fww_fetching_' . $tmdb_id;

    if (false === get_transient($lock_key)) {
        // Set lock for 30 seconds
        set_transient($lock_key, true, 30);

        $tmdb_data = FWW_TMDB_API::get_movie($tmdb_id);

        if ($tmdb_data && !is_wp_error($tmdb_data)) {
            // Cache the data
            update_post_meta($post_id, '_fww_tmdb_data', $tmdb_data);
        }

        // Release lock
        delete_transient($lock_key);
    }
}
```

---

### 4. Remove Error Suppression
**File:** `includes/movie-functions.php` (lines 252-253)

**Issue:** `@unlink()` suppresses errors silently

**Current Code:**
```php
// Clean up temp file
if (file_exists($tmp)) {
    @unlink($tmp);
}
```

**Refactored:**
```php
// Clean up temp file
if (file_exists($tmp)) {
    if (!unlink($tmp)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FWW: Failed to delete temporary file: ' . $tmp);
        }
    }
}
```

---

### 5. Add File Size Validation for Downloads
**File:** `includes/movie-functions.php` (lines 230-264)

**Issue:** No file size limit, potential DoS

**Current Code:**
```php
// Download to temp file
$tmp = download_url($poster_url);

if (is_wp_error($tmp)) {
    return false;
}
```

**Refactored:**
```php
// Set reasonable limits
$max_file_size = 5 * 1024 * 1024; // 5MB
$original_timeout = ini_get('max_execution_time');

// Increase timeout temporarily
set_time_limit(60);

// Download to temp file
$tmp = download_url($poster_url);

// Restore original timeout
set_time_limit($original_timeout);

if (is_wp_error($tmp)) {
    return false;
}

// Check file size
if (filesize($tmp) > $max_file_size) {
    unlink($tmp);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('FWW: Poster image too large: ' . filesize($tmp) . ' bytes');
    }
    return false;
}
```

---

## Priority: HIGH 🔴

### 6. Add Pagination to Watch Sightings
**File:** `includes/movie-functions.php` (lines 51-78)

**Issue:** Returns ALL results, could be thousands

**Current Code:**
```php
$query = $wpdb->prepare("
    SELECT ...
    FROM {$wpdb->prefix}fwd_film_actor_watch faw
    ...
    WHERE faw.film_id = %d
    ORDER BY a.actor_name
", $film_id);

return $wpdb->get_results($query);
```

**Refactored:**
```php
function fww_get_movie_watch_sightings($film_id, $limit = 50, $offset = 0) {
    if (empty($film_id)) {
        return array();
    }

    global $wpdb;

    $limit = intval($limit);
    $offset = intval($offset);

    $query = $wpdb->prepare("
        SELECT ...
        FROM {$wpdb->prefix}fwd_film_actor_watch faw
        ...
        WHERE faw.film_id = %d
        ORDER BY a.actor_name
        LIMIT %d OFFSET %d
    ", $film_id, $limit, $offset);

    return $wpdb->get_results($query);
}
```

---

### 7. Add Type Hints (PHP 7.4+)
**Files:** ALL PHP files in `includes/`

**Issue:** No type hints or return types

**Current Code:**
```php
public static function get_movie($movie_id) {
    // ...
}

function fww_format_runtime($minutes) {
    // ...
}
```

**Refactored:**
```php
public static function get_movie(int $movie_id): array|WP_Error {
    // ...
}

function fww_format_runtime(?int $minutes): string {
    // ...
}
```

---

### 8. Replace Magic Numbers with Constants
**File:** `includes/tmdb-api.php`

**Issue:** Hard-coded values throughout

**Current Code:**
```php
return intval(get_option('fww_cache_duration', 86400));
// ...
$response = wp_remote_get($url, array('timeout' => 20));
// ...
$cast = FWW_TMDB_API::get_movie_credits($tmdb_id, 10);
```

**Refactored:**
```php
class FWW_TMDB_API {
    const API_BASE_URL = 'https://api.themoviedb.org/3/';
    const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/';

    // Configuration constants
    const DEFAULT_CACHE_DURATION = 86400; // 24 hours
    const API_TIMEOUT = 20; // seconds
    const DEFAULT_CAST_LIMIT = 10;
    const LOCK_TIMEOUT = 30; // seconds

    // ...

    return intval(get_option('fww_cache_duration', self::DEFAULT_CACHE_DURATION));
```

---

### 9. Validate Person/Movie IDs in All API Calls

**Files:** `includes/tmdb-api.php`

**Add validation to:**
- `get_person()` (line 308)
- `get_person_movie_credits()` (line 333)
- `get_movie_credits()` (line 230)
- `get_us_certification()` (line 202)

**Pattern:**
```php
public static function get_person(int $person_id): array|WP_Error {
    // Add this validation at the start
    if (empty($person_id) || $person_id <= 0) {
        return new WP_Error('invalid_person_id', 'Invalid person ID provided');
    }

    $person_id = intval($person_id);
    // ... rest of code
}
```

---

### 10. Standardize Error Return Values

**Issue:** Inconsistent - some return `false`, others `array()`, others `WP_Error`

**Refactored Pattern:**
```php
// For functions that fetch data:
// ALWAYS return WP_Error on failure, array on success

// For helper functions that format data:
// Return empty string '' for formatting functions
// Return empty array array() for collection functions
// Return null for optional values

// Examples:
function fww_get_movie_data($post_id): array {
    // Always returns array, never false
}

function fww_format_runtime(?int $minutes): string {
    // Always returns string, never null or false
}

public static function get_movie(int $movie_id): array|WP_Error {
    // Returns WP_Error on failure, array on success
}
```

---

## Priority: MEDIUM 🟡

### 11. Remove Duplicate File
**Issue:** `movie-functions.php` exists in root AND `includes/`

**Action:**
```bash
# Remove root version
rm film-watch-wiki/movie-functions.php

# Keep only includes/movie-functions.php
```

---

### 12. Add Docblocks to All Public Methods

**Current:**
```php
public static function get_image_url($path, $size = 'original') {
    if (empty($path)) {
        return null;
    }
    return self::IMAGE_BASE_URL . $size . $path;
}
```

**Refactored:**
```php
/**
 * Get full TMDB image URL from path
 *
 * @param string|null $path  The image path from TMDB API
 * @param string      $size  Image size (w92, w185, w500, original, etc.)
 * @return string|null Full image URL or null if path is empty
 *
 * @since 1.1.2
 */
public static function get_image_url(?string $path, string $size = 'original'): ?string {
    if (empty($path)) {
        return null;
    }
    return self::IMAGE_BASE_URL . $size . $path;
}
```

---

## Refactoring Checklist

### Critical (Do First)
- [ ] Fix debug logging (template-loader.php)
- [ ] Add movie ID validation (tmdb-api.php)
- [ ] Fix race condition (movie-functions.php)
- [ ] Remove error suppression (movie-functions.php)
- [ ] Add file size validation (movie-functions.php)

### High Priority
- [ ] Add pagination to watch sightings
- [ ] Add type hints to all functions
- [ ] Replace magic numbers with constants
- [ ] Validate all API method parameters
- [ ] Standardize error return values

### Medium Priority
- [ ] Remove duplicate movie-functions.php
- [ ] Add complete docblocks
- [ ] Add year format validation
- [ ] Document required database indexes

---

## Estimated Time
- Critical fixes: 2-3 hours
- High priority: 4-6 hours
- Medium priority: 3-4 hours
- **Total: 9-13 hours**

## Impact
After refactoring:
- Security: A+ (all critical issues fixed)
- Code Quality: A (type hints, constants, documentation)
- Performance: B+ (pagination added, but needs indexes)
- Maintainability: A (clear, documented, typed code)

**Overall Grade: A-** (up from B+)
