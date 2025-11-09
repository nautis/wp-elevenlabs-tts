# Database Schema Review - Film Watch Wiki

## Current Schema

### Table: `wp_fww_sightings`
**Purpose:** Junction table for many-to-many-to-many relationships between movies, actors, watches, and brands

```sql
CREATE TABLE wp_fww_sightings (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    movie_id bigint(20) unsigned NOT NULL,
    actor_id bigint(20) unsigned NOT NULL,
    character_name varchar(255) DEFAULT NULL,
    watch_id bigint(20) unsigned NOT NULL,
    brand_id bigint(20) unsigned NOT NULL,
    scene_description text DEFAULT NULL,
    verification_level varchar(50) DEFAULT 'unverified',
    timestamp_start varchar(50) DEFAULT NULL,
    timestamp_end varchar(50) DEFAULT NULL,
    screenshot_url varchar(500) DEFAULT NULL,
    notes text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY movie_id (movie_id),
    KEY actor_id (actor_id),
    KEY watch_id (watch_id),
    KEY brand_id (brand_id),
    KEY verification_level (verification_level)
);
```

---

## ✅ STRENGTHS

### 1. **Correct Junction Table Design**
- ✅ Properly implements many-to-many-to-many relationship
- ✅ Links 4 entities: movies ↔ actors ↔ watches ↔ brands
- ✅ All foreign IDs use appropriate data type (bigint unsigned)

### 2. **Good Column Data Types**
- ✅ `bigint(20) unsigned` for IDs (matches WordPress wp_posts.ID)
- ✅ `varchar(255)` for character_name (reasonable length)
- ✅ `text` for scene_description/notes (variable length content)
- ✅ `datetime` for timestamps with auto-update

### 3. **Basic Indexes Present**
- ✅ Primary key on `id`
- ✅ Individual indexes on all foreign key columns
- ✅ Index on `verification_level` (for filtering)

### 4. **Audit Trail**
- ✅ `created_at` and `updated_at` timestamps
- ✅ Auto-updates on UPDATE via ON UPDATE CURRENT_TIMESTAMP

---

## ⚠️ ISSUES & RECOMMENDATIONS

### 1. **Missing Foreign Key Constraints**

**Current:** No explicit FOREIGN KEY constraints
**Issue:** Orphaned records possible if posts deleted
**WordPress Context:** WordPress intentionally avoids FK constraints for plugin compatibility

**Recommendation:**
```sql
-- Option A: Add FK constraints (breaks WordPress conventions)
FOREIGN KEY (movie_id) REFERENCES wp_posts(ID) ON DELETE CASCADE,
FOREIGN KEY (actor_id) REFERENCES wp_posts(ID) ON DELETE CASCADE,
FOREIGN KEY (watch_id) REFERENCES wp_posts(ID) ON DELETE CASCADE,
FOREIGN KEY (brand_id) REFERENCES wp_posts(ID) ON DELETE CASCADE

-- Option B: WordPress approach - Handle in application code
-- Add cleanup function to delete sightings when posts are deleted
```

**Decision:** Use Option B (WordPress standard) + implement cleanup hook

---

### 2. **Missing Composite Indexes**

**Current:** Only single-column indexes
**Issue:** Slow queries when filtering by multiple columns

**Common Query Patterns:**
```sql
-- Get all sightings for a movie by a specific actor
SELECT * FROM wp_fww_sightings WHERE movie_id = X AND actor_id = Y;

-- Get all sightings of a watch in a specific movie
SELECT * FROM wp_fww_sightings WHERE movie_id = X AND watch_id = Y;

-- Get all sightings by actor wearing specific brand
SELECT * FROM wp_fww_sightings WHERE actor_id = X AND brand_id = Y;
```

**Recommendation:**
```sql
KEY idx_movie_actor (movie_id, actor_id),
KEY idx_movie_watch (movie_id, watch_id),
KEY idx_actor_watch (actor_id, watch_id),
KEY idx_actor_brand (actor_id, brand_id),
KEY idx_brand_watch (brand_id, watch_id),
KEY idx_created_at (created_at)  -- For "recent additions" queries
```

---

### 3. **No Duplicate Prevention**

**Current:** Can insert identical sightings multiple times
**Issue:** Same actor can wear same watch in same movie (duplicate rows)

**Example Problem:**
```
id=1: Daniel Craig wears Omega Seamaster in Skyfall
id=2: Daniel Craig wears Omega Seamaster in Skyfall  ← DUPLICATE
```

**Recommendation:**
```sql
-- Add unique constraint to prevent duplicates
UNIQUE KEY unique_sighting (movie_id, actor_id, watch_id, character_name(100))
```

**Consideration:** Should one actor wearing the same watch in different scenes be separate records?
- **YES** if scene_description differs → Don't add unique constraint
- **NO** if we want one record per actor-watch combo → Add unique constraint

**Suggested Decision:** Allow duplicates, but add uniqueness validation in PHP before insert

---

### 4. **Timestamp Storage Format**

**Current:** `timestamp_start varchar(50)` and `timestamp_end varchar(50)`
**Issue:** Inconsistent format, hard to validate/sort

**Possible Values:**
- "00:45:30" (time format)
- "45:30" (minutes:seconds)
- "2730" (seconds only)
- "Chapter 5" (invalid for sorting)

**Recommendation:**
```sql
-- Option A: Store as seconds (integer)
timestamp_start int unsigned DEFAULT NULL,  -- Seconds from film start
timestamp_end int unsigned DEFAULT NULL,

-- Option B: Store as TIME data type
timestamp_start time DEFAULT NULL,  -- 00:45:30
timestamp_end time DEFAULT NULL,

-- Option C: Keep varchar but enforce format in validation
timestamp_start varchar(8) DEFAULT NULL,  -- HH:MM:SS format only
```

**Suggested Decision:** Option A (integer seconds) - most flexible for calculations

---

### 5. **Missing Soft Delete**

**Current:** DELETE removes records permanently
**Issue:** No undo, no audit trail of removed sightings

**Recommendation:**
```sql
deleted_at datetime DEFAULT NULL,
KEY idx_deleted (deleted_at)

-- Query pattern
WHERE deleted_at IS NULL  -- Get active records only
```

---

### 6. **Scalability Concerns**

#### A. **TEXT Column Size**
**Current:** Unlimited `text` for scene_description and notes
**Potential Issue:** 65KB per record if users paste entire film scripts

**Recommendation:**
```sql
scene_description varchar(1000) DEFAULT NULL,  -- ~2-3 paragraphs
notes varchar(2000) DEFAULT NULL,  -- Detailed notes allowed
```

#### B. **Large Dataset Performance**
**Projected Growth:**
- 10,000 films × 5 actors × 2 watches = **100,000 rows**
- With current indexes: Acceptable performance
- With composite indexes: Excellent performance

**Partitioning Strategy (for 1M+ rows):**
```sql
-- Partition by movie_id ranges
PARTITION BY RANGE (movie_id) (
    PARTITION p0 VALUES LESS THAN (10000),
    PARTITION p1 VALUES LESS THAN (20000),
    ...
);
```

**Current Decision:** Not needed yet. Revisit at 500K+ rows.

#### C. **Caching Strategy**
**WordPress Transients:** Already using for TMDB API
**Recommendation:** Cache sighting queries

```php
// Cache key pattern
$cache_key = 'fww_sightings_movie_' . $movie_id;
$sightings = get_transient($cache_key);

if ($sightings === false) {
    $sightings = FWW_Sightings::get_sightings_by_movie($movie_id);
    set_transient($cache_key, $sightings, 3600); // 1 hour
}
```

---

### 7. **Missing Validation Constraints**

**Current:** No CHECK constraints
**Issue:** Invalid data can be inserted

**Examples:**
```sql
-- Invalid verification levels
INSERT ... verification_level = 'maybe_verified';  -- Should fail

-- Negative IDs
INSERT ... movie_id = -1;  -- Should fail

-- Invalid timestamp order
INSERT ... timestamp_start = '01:30:00', timestamp_end = '00:30:00';  -- End before start!
```

**Recommendation (MySQL 8.0+):**
```sql
CHECK (verification_level IN ('unverified', 'verified', 'confirmed')),
CHECK (movie_id > 0),
CHECK (actor_id > 0),
CHECK (watch_id > 0),
CHECK (brand_id > 0),
CHECK (timestamp_start IS NULL OR timestamp_end IS NULL OR timestamp_start <= timestamp_end)
```

**WordPress Compatibility Issue:** CHECK constraints not supported in MySQL 5.7
**Solution:** Validate in PHP before insert

---

### 8. **Missing Relationship to Legacy Database**

**Current:** No link to old `wp_fwd_film_actor_watch` table
**Issue:** Can't track which records were migrated

**Recommendation:**
```sql
legacy_id bigint(20) unsigned DEFAULT NULL,
migrated_at datetime DEFAULT NULL,
KEY idx_legacy (legacy_id)
```

---

## 📊 RECOMMENDED IMPROVED SCHEMA

```sql
CREATE TABLE IF NOT EXISTS wp_fww_sightings (
    -- Primary Key
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

    -- Foreign Keys (IDs to wp_posts)
    movie_id bigint(20) unsigned NOT NULL,
    actor_id bigint(20) unsigned NOT NULL,
    watch_id bigint(20) unsigned NOT NULL,
    brand_id bigint(20) unsigned NOT NULL,

    -- Sighting Details
    character_name varchar(255) DEFAULT NULL,
    scene_description varchar(1000) DEFAULT NULL,
    notes varchar(2000) DEFAULT NULL,

    -- Timestamps (in seconds from film start)
    timestamp_start int unsigned DEFAULT NULL,
    timestamp_end int unsigned DEFAULT NULL,

    -- Verification & Source
    verification_level enum('unverified', 'verified', 'confirmed') DEFAULT 'unverified',
    screenshot_url varchar(500) DEFAULT NULL,
    source_url varchar(500) DEFAULT NULL,

    -- Audit Trail
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at datetime DEFAULT NULL,

    -- Migration Tracking
    legacy_id bigint(20) unsigned DEFAULT NULL,
    migrated_at datetime DEFAULT NULL,

    -- Primary Key
    PRIMARY KEY (id),

    -- Single Column Indexes (for foreign key lookups)
    KEY idx_movie (movie_id),
    KEY idx_actor (actor_id),
    KEY idx_watch (watch_id),
    KEY idx_brand (brand_id),

    -- Composite Indexes (for multi-column queries)
    KEY idx_movie_actor (movie_id, actor_id),
    KEY idx_movie_watch (movie_id, watch_id),
    KEY idx_actor_watch (actor_id, watch_id),
    KEY idx_actor_brand (actor_id, brand_id),
    KEY idx_brand_watch (brand_id, watch_id),

    -- Utility Indexes
    KEY idx_verification (verification_level),
    KEY idx_created (created_at),
    KEY idx_deleted (deleted_at),
    KEY idx_legacy (legacy_id),

    -- Prevent complete duplicates (same movie+actor+watch+character)
    UNIQUE KEY unique_sighting (movie_id, actor_id, watch_id, character_name(100))

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔄 MIGRATION PLAN

### Phase 1: Add Indexes (Non-Breaking)
```sql
ALTER TABLE wp_fww_sightings
    ADD KEY idx_movie_actor (movie_id, actor_id),
    ADD KEY idx_movie_watch (movie_id, watch_id),
    ADD KEY idx_actor_watch (actor_id, watch_id),
    ADD KEY idx_actor_brand (actor_id, brand_id),
    ADD KEY idx_brand_watch (brand_id, watch_id),
    ADD KEY idx_created (created_at);
```

### Phase 2: Add New Columns (Non-Breaking)
```sql
ALTER TABLE wp_fww_sightings
    ADD COLUMN deleted_at datetime DEFAULT NULL AFTER updated_at,
    ADD COLUMN legacy_id bigint(20) unsigned DEFAULT NULL AFTER deleted_at,
    ADD COLUMN migrated_at datetime DEFAULT NULL AFTER legacy_id,
    ADD COLUMN source_url varchar(500) DEFAULT NULL AFTER screenshot_url,
    ADD KEY idx_deleted (deleted_at),
    ADD KEY idx_legacy (legacy_id);
```

### Phase 3: Change Data Types (BREAKING - requires backup)
```sql
-- Backup table first!
CREATE TABLE wp_fww_sightings_backup AS SELECT * FROM wp_fww_sightings;

-- Change verification_level to ENUM
ALTER TABLE wp_fww_sightings
    MODIFY verification_level enum('unverified', 'verified', 'confirmed') DEFAULT 'unverified';

-- Change timestamp format (requires data conversion)
ALTER TABLE wp_fww_sightings
    ADD COLUMN timestamp_start_new int unsigned DEFAULT NULL AFTER timestamp_end,
    ADD COLUMN timestamp_end_new int unsigned DEFAULT NULL AFTER timestamp_start_new;

-- Convert existing data (if any)
-- UPDATE wp_fww_sightings SET timestamp_start_new = TIME_TO_SEC(timestamp_start);

-- Drop old columns
ALTER TABLE wp_fww_sightings
    DROP COLUMN timestamp_start,
    DROP COLUMN timestamp_end,
    CHANGE timestamp_start_new timestamp_start int unsigned DEFAULT NULL,
    CHANGE timestamp_end_new timestamp_end int unsigned DEFAULT NULL;
```

### Phase 4: Add Unique Constraint (Optional)
```sql
-- Only if we want to prevent duplicate sightings
ALTER TABLE wp_fww_sightings
    ADD UNIQUE KEY unique_sighting (movie_id, actor_id, watch_id, character_name(100));
```

---

## 🎯 RECOMMENDED ACTIONS

### Immediate (Before Data Migration)
1. ✅ Add composite indexes (Phase 1)
2. ✅ Add soft delete column (Phase 2)
3. ✅ Add legacy_id tracking (Phase 2)
4. ✅ Implement cleanup hook for deleted posts
5. ✅ Add PHP validation for verification_level

### Before Production Use
1. ⚠️ Decide on duplicate prevention strategy
2. ⚠️ Decide on timestamp format (keep varchar or convert to int)
3. ⚠️ Add caching layer for queries
4. ⚠️ Implement soft delete in code

### Future (At Scale)
1. 🔮 Consider table partitioning at 500K+ rows
2. 🔮 Add read replicas if query volume increases
3. 🔮 Implement ElasticSearch for full-text search on descriptions

---

## 📝 SUMMARY

**Current Status:** 6/10
- ✅ Basic structure correct
- ✅ Data types appropriate
- ⚠️ Missing critical indexes for performance
- ⚠️ No duplicate prevention
- ⚠️ No cleanup mechanism

**With Recommended Changes:** 9/10
- ✅ Comprehensive indexing strategy
- ✅ Soft delete support
- ✅ Migration tracking
- ✅ Scalability considerations
- ✅ Data integrity safeguards

**Ready for Production?**
- **Current schema:** Yes, but will be slow with 10K+ records
- **With Phase 1+2 changes:** Yes, production-ready for 100K+ records
