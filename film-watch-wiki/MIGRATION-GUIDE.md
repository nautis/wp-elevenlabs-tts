# Legacy Data Migration Guide

Complete guide for migrating data from `wp_fwd_*` legacy tables to the new WordPress post types and sightings system.

---

## 📊 Migration Overview

### What Will Be Migrated

**Source (Legacy Database):**
- `wp_fwd_brands` → 54 brands
- `wp_fwd_watches` → 194 watches
- `wp_fwd_actors` → 237 actors
- `wp_fwd_films` → 341 movies
- `wp_fwd_film_actor_watch` → 435 watch sightings

**Destination (New System):**
- → `fww_brand` posts (54 brands)
- → `fww_watch` posts (194 watches)
- → `fww_actor` posts (237 actors)
- → `fww_movie` posts (341 movies)
- → `wp_fww_sightings` table (435 relationships)

**Total:** 826 WordPress posts + 435 relationships

---

## ✅ Pre-Migration Checklist

### 1. **Verify Schema Upgrade**

Ensure the database schema is v2.0:

```bash
ssh tellingtime
mysql wordpress -e "SELECT option_value FROM wp_options WHERE option_name = 'fww_sightings_db_version';"
```

Should output: `2.0`

If not, deactivate/reactivate the plugin first.

### 2. **Backup Database**

**CRITICAL:** Always backup before migration!

```bash
ssh tellingtime

# Backup entire WordPress database
mysqldump -u root -p wordpress > ~/wordpress_backup_$(date +%Y%m%d).sql

# Or backup just the relevant tables
mysqldump -u root -p wordpress \
  wp_fwd_brands \
  wp_fwd_watches \
  wp_fwd_actors \
  wp_fwd_films \
  wp_fwd_characters \
  wp_fwd_film_actor_watch \
  wp_posts \
  wp_postmeta \
  wp_fww_sightings \
  > ~/fww_migration_backup_$(date +%Y%m%d).sql
```

### 3. **Verify Legacy Data**

Check that legacy tables exist and have data:

```bash
mysql wordpress -e "SELECT
  (SELECT COUNT(*) FROM wp_fwd_brands) as brands,
  (SELECT COUNT(*) FROM wp_fwd_watches) as watches,
  (SELECT COUNT(*) FROM wp_fwd_actors) as actors,
  (SELECT COUNT(*) FROM wp_fwd_films) as films,
  (SELECT COUNT(*) FROM wp_fwd_film_actor_watch) as sightings;"
```

Expected output:
```
brands | watches | actors | films | sightings
54     | 194     | 237    | 341   | 435
```

### 4. **Check Disk Space**

Migration will create ~800 posts, ensure adequate space:

```bash
df -h /var/www/wordpress
```

Should have at least 500MB free.

---

## 🚀 Migration Process

### Step 1: DRY RUN (Preview Only)

**ALWAYS run dry-run first!** This shows what will happen without making changes.

```bash
ssh tellingtime
cd /var/www/wordpress/wp-content/plugins/film-watch-wiki
php migrate-legacy-data.php --dry-run --verbose
```

**Expected Output:**
```
=== FILM WATCH WIKI MIGRATION ===
Mode: DRY RUN (no changes will be made)

Step 1/5: Migrating Brands...
  Found 54 brands to migrate.
  WOULD CREATE: Brand 'Vacheron Constantin'
  WOULD CREATE: Brand 'TAG Heuer'
  ...
  Brands: 54 created, 0 skipped

Step 2/5: Migrating Watches...
  Found 194 watches to migrate.
  WOULD CREATE: Watch 'Vacheron Constantin Malte'
  ...
  Watches: 194 created, 0 skipped

Step 3/5: Migrating Actors...
  Found 237 actors to migrate.
  WOULD CREATE: Actor 'Colin Farrell'
  ...
  Actors: 237 created, 0 skipped

Step 4/5: Migrating Movies...
  Found 341 movies to migrate.
  WOULD CREATE: Movie 'Miami Vice (2006)'
  ...
  Movies: 341 created, 0 skipped

Step 5/5: Migrating Watch Sightings...
  Found 435 sightings to migrate.
  WOULD CREATE: Sighting 'Colin Farrell' in 'Miami Vice'
  ...
  Sightings: 435 created, 0 skipped

=== MIGRATION COMPLETE ===

Summary:
  Brands:    54 created, 0 skipped
  Watches:   194 created, 0 skipped
  Actors:    237 created, 0 skipped
  Movies:    341 created, 0 skipped
  Sightings: 435 created, 0 skipped

*** THIS WAS A DRY RUN - NO CHANGES WERE MADE ***
```

**Review the output carefully!** Look for:
- ✅ Correct number of items found
- ✅ No errors
- ✅ Reasonable titles (not blank or garbled)

### Step 2: LIVE MIGRATION

Once dry-run looks good, run the actual migration:

```bash
php migrate-legacy-data.php --live --verbose
```

You'll be prompted for confirmation:
```
WARNING: You are about to perform a LIVE migration!
This will create WordPress posts and database records.

Type 'yes' to continue or anything else to cancel:
```

Type `yes` and press Enter.

**Duration:** ~2-5 minutes for 826 posts + 435 sightings

### Step 3: Verify Migration

Check that posts were created:

```bash
mysql wordpress -e "SELECT
  (SELECT COUNT(*) FROM wp_posts WHERE post_type = 'fww_brand' AND post_status = 'publish') as brands,
  (SELECT COUNT(*) FROM wp_posts WHERE post_type = 'fww_watch' AND post_status = 'publish') as watches,
  (SELECT COUNT(*) FROM wp_posts WHERE post_type = 'fww_actor' AND post_status = 'publish') as actors,
  (SELECT COUNT(*) FROM wp_posts WHERE post_type = 'fww_movie' AND post_status = 'publish') as movies,
  (SELECT COUNT(*) FROM wp_fww_sightings WHERE deleted_at IS NULL) as sightings;"
```

Should match expected numbers:
```
brands | watches | actors | movies | sightings
54     | 194     | 237    | 341    | 435
```

---

## 🔍 Verification Steps

### 1. Check Sample Posts

```bash
# Check brands
mysql wordpress -e "SELECT ID, post_title FROM wp_posts WHERE post_type = 'fww_brand' LIMIT 5;"

# Check actors
mysql wordpress -e "SELECT ID, post_title FROM wp_posts WHERE post_type = 'fww_actor' LIMIT 5;"

# Check movies
mysql wordpress -e "SELECT ID, post_title FROM wp_posts WHERE post_type = 'fww_movie' LIMIT 5;"
```

### 2. Verify Legacy ID Mapping

Check that legacy IDs are stored:

```bash
mysql wordpress -e "SELECT post_id, meta_value as legacy_brand_id FROM wp_postmeta WHERE meta_key = '_fww_legacy_brand_id' LIMIT 5;"
```

### 3. Check Sightings Relationships

Verify sightings have all required data:

```bash
mysql wordpress -e "
  SELECT s.id, s.legacy_id,
         m.post_title as movie,
         a.post_title as actor,
         b.post_title as brand,
         w.post_title as watch
  FROM wp_fww_sightings s
  LEFT JOIN wp_posts m ON s.movie_id = m.ID
  LEFT JOIN wp_posts a ON s.actor_id = a.ID
  LEFT JOIN wp_posts b ON s.brand_id = b.ID
  LEFT JOIN wp_posts w ON s.watch_id = w.ID
  LIMIT 5;"
```

Should show complete data (no NULLs).

### 4. Test Frontend

Visit these URLs on your site:

- `/brand/` - Browse all brands
- `/actor/` - Browse all actors
- `/watch/` - Browse all watches
- `/movie/` - Browse all movies

Click through a few to verify:
- Posts display correctly
- Relationships show up (actor → movies, watch → movies, etc.)
- Links work between entities

---

## 📝 Migration Details

### Data Mapping

#### Brands
- `wp_fwd_brands.brand_id` → `_fww_legacy_brand_id` (post meta)
- `brand_name` → post_title
- `description` → post_content

#### Watches
- `wp_fwd_watches.watch_id` → `_fww_legacy_watch_id` (post meta)
- `brand_name + model_reference` → post_title
- `model_description` → post_content
- `specifications` → post_content (appended)

#### Actors
- `wp_fwd_actors.actor_id` → `_fww_legacy_actor_id` (post meta)
- `actor_name` → post_title
- `biography` → post_content
- `tmdb_id` → `_fww_tmdb_id` (if present)

#### Movies
- `wp_fwd_films.film_id` → `_fww_legacy_film_id` (post meta)
- `title` → post_title
- `year` → `_fww_year` (post meta)
- `description` → post_content
- `tmdb_id` → `_fww_tmdb_id` (if present)

#### Sightings
- `faw_id` → `legacy_id` (sightings table column)
- `film_id` → `movie_id` (via mapping)
- `actor_id` → `actor_id` (via mapping)
- `character_name` → `character_name`
- `watch_id` → `watch_id` (via mapping)
- `brand_id` (via watch) → `brand_id` (via mapping)
- `narrative_role` → `scene_description`
- `confidence_level` → `verification_level` (mapped)
- `image_url` → `screenshot_url`
- `source_url` → `source_url`

### Verification Level Mapping

**Legacy `confidence_level` → New `verification_level`:**
- Contains "confirmed" or "high" → `confirmed`
- Contains "verified" or "medium" → `verified`
- Empty or other → `unverified`

---

## ⚠️ Troubleshooting

### Issue: "Could not find WordPress installation"

**Cause:** Script can't locate wp-load.php
**Solution:** Run from plugin directory or adjust path

```bash
cd /var/www/wordpress/wp-content/plugins/film-watch-wiki
php migrate-legacy-data.php --dry-run
```

### Issue: "Missing mapped IDs for sighting"

**Cause:** Sighting references a movie/actor/watch that doesn't exist
**Solution:** These sightings will be skipped. Review error log to see which ones.

### Issue: Duplicate posts created

**Cause:** Migration run multiple times
**Solution:**
1. Migration skips existing posts by title
2. If you want to re-migrate, delete posts first:

```bash
# WARNING: Deletes all migrated posts!
mysql wordpress -e "
  DELETE FROM wp_posts WHERE post_type IN ('fww_brand', 'fww_watch', 'fww_actor', 'fww_movie');
  DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts);
  DELETE FROM wp_fww_sightings WHERE legacy_id IS NOT NULL;
"
```

### Issue: Migration times out

**Cause:** Too many records for PHP execution time
**Solution:** Increase timeout:

```bash
php -d max_execution_time=600 migrate-legacy-data.php --live
```

### Issue: Out of memory

**Cause:** PHP memory limit too low
**Solution:**

```bash
php -d memory_limit=512M migrate-legacy-data.php --live
```

---

## 🔄 Re-Running Migration

If you need to run migration again:

### Option A: Incremental (Skip Existing)
The migration automatically skips existing posts, so you can safely re-run:

```bash
php migrate-legacy-data.php --live
```

It will only create what's missing.

### Option B: Clean Slate (Delete & Re-Migrate)

**WARNING:** This deletes all migrated data!

```bash
# 1. Delete all migrated posts
mysql wordpress -e "
  DELETE pm FROM wp_postmeta pm
  LEFT JOIN wp_posts p ON pm.post_id = p.ID
  WHERE p.post_type IN ('fww_brand', 'fww_watch', 'fww_actor', 'fww_movie');

  DELETE FROM wp_posts
  WHERE post_type IN ('fww_brand', 'fww_watch', 'fww_actor', 'fww_movie');

  DELETE FROM wp_fww_sightings
  WHERE legacy_id IS NOT NULL;
"

# 2. Run migration again
php migrate-legacy-data.php --live
```

---

## 📊 Post-Migration Tasks

### 1. Update Legacy Template Fallback

The movie template currently shows legacy data if no new sightings exist. After successful migration, you can remove this fallback in `templates/single-fww_movie.php`.

### 2. Fetch TMDB Data for Movies

Optionally fetch rich metadata from TMDB for all movies:

```php
// In WordPress admin or via WP-CLI
$movies = get_posts(array('post_type' => 'fww_movie', 'posts_per_page' => -1));

foreach ($movies as $movie) {
    $tmdb_id = get_post_meta($movie->ID, '_fww_tmdb_id', true);

    if ($tmdb_id) {
        $tmdb_data = FWW_TMDB_API::get_movie($tmdb_id);

        if (!is_wp_error($tmdb_data)) {
            update_post_meta($movie->ID, '_fww_tmdb_data', $tmdb_data);

            // Download poster
            if (!empty($tmdb_data['poster_path'])) {
                fww_download_and_set_poster($movie->ID, $tmdb_data['poster_path'], $tmdb_data['title']);
            }
        }
    }
}
```

### 3. Regenerate Permalinks

Flush WordPress permalinks to ensure URLs work:

Go to: WordPress Admin → Settings → Permalinks → Save Changes

Or via WP-CLI:
```bash
wp rewrite flush --path=/var/www/wordpress
```

### 4. Test Search & Archives

- Search for actors, movies, watches, brands
- Browse archive pages
- Verify pagination works

---

## 🎯 Success Criteria

Migration is successful when:

✅ Dry run shows expected counts
✅ Live migration completes without errors
✅ Post counts match legacy table counts
✅ Sample posts display correctly on frontend
✅ Relationships work (clicking actor shows their movies)
✅ Legacy data still intact (wp_fwd_* tables unchanged)
✅ All sightings have `legacy_id` for reference

---

## 🔒 Legacy Data Preservation

**IMPORTANT:** Migration does NOT modify or delete legacy tables!

- `wp_fwd_*` tables remain untouched
- They can be kept as backup/reference
- Can be dropped later if desired (after verification)

**To drop legacy tables (only after confirming migration success):**

```bash
# WARNING: This is permanent!
mysql wordpress -e "
  DROP TABLE IF EXISTS wp_fwd_brands;
  DROP TABLE IF EXISTS wp_fwd_watches;
  DROP TABLE IF EXISTS wp_fwd_actors;
  DROP TABLE IF EXISTS wp_fwd_films;
  DROP TABLE IF EXISTS wp_fwd_characters;
  DROP TABLE IF EXISTS wp_fwd_film_actor_watch;
"
```

**Recommendation:** Keep legacy tables for at least 30 days post-migration.

---

## 📞 Support

If migration fails:

1. **Check error output** - Migration logs all errors
2. **Review dry-run first** - Always preview before live
3. **Restore from backup** - If needed, restore database backup
4. **Check file:** `SCHEMA-UPGRADE-V2.md` for schema issues

**Migration Log Location:**
- Outputs to console (save with `> migration.log`)
- Or check WordPress debug log if WP_DEBUG enabled

---

## ✨ Summary

**Migration Steps:**
1. ✅ Backup database
2. ✅ Verify schema v2.0
3. ✅ Run dry-run
4. ✅ Review output
5. ✅ Run live migration
6. ✅ Verify counts
7. ✅ Test frontend
8. ✅ Done!

**Estimated Time:** 15-30 minutes total (including verification)

**Downtime:** None (migration runs in background)
