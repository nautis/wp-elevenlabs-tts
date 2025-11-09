# Film Watch Wiki

A WordPress plugin that creates wiki-style pages for movies, actors, and watches with TMDB API integration.

## Overview

Film Watch Wiki transforms the Film Watch Database into a wiki-style site where each movie, actor, and watch has its own dedicated page with rich content and relationships. The plugin integrates with The Movie Database (TMDB) API to provide comprehensive movie information, cast details, posters, and more.

## Features

### Movies
- **Dedicated movie pages** with TMDB data (poster, backdrop, overview, cast, ratings, runtime, etc.)
- **Watch sightings** showing which watches were worn in each film
- **Actor/character information** with links to actor pages
- **Beautiful responsive design** similar to TMDB's movie pages

### Actors (Coming Soon)
- Actor biography and filmography from TMDB
- List of films and watches they've worn
- Photo galleries

### Watches (Coming Soon)
- Watch specifications and details
- Film appearances with context
- Actor associations
- Brand information

## Architecture

The plugin uses WordPress Custom Post Types:
- `fww_movie` - Movie pages
- `fww_actor` - Actor pages (future)
- `fww_watch` - Watch pages (future)

It connects to the existing `wp_fwd_*` database tables from the Film Watch Database plugin to display watch sightings and relationships without disrupting the production system.

## Installation

1. Copy the `film-watch-wiki` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Go to Settings → Film Watch Wiki
4. Enter your TMDB API Read Access Token
5. Configure language and cache settings

## Getting a TMDB API Key

1. Create a free account at [themoviedb.org](https://www.themoviedb.org/)
2. Go to Settings → API
3. Request an API key
4. Copy the "API Read Access Token" (Bearer token)
5. Paste it into the plugin settings

## Usage

### Creating Movie Pages

1. Go to Movies → Add New in WordPress admin
2. Enter the movie title
3. In the "Movie Details" metabox, enter:
   - **TMDB ID**: The movie's ID from TMDB (e.g., 37724 for Skyfall)
   - **Year**: Release year
   - **Legacy Film ID**: The film_id from wp_fwd_films table (for watch sightings)
4. Save the post

The plugin will automatically:
- Fetch movie data from TMDB (poster, overview, cast, etc.)
- Display watch sightings from the existing database
- Create a beautiful wiki-style page

### Finding TMDB IDs

Visit the movie page on TMDB (e.g., https://www.themoviedb.org/movie/37724-skyfall) - the number is the TMDB ID.

## Migration

To migrate existing films from the Film Watch Database:

1. Go to Settings → Film Watch Wiki
2. Click "Migrate Films to Custom Posts"
3. The script will create a fww_movie post for each film in wp_fwd_films
4. You can then manually add TMDB IDs to fetch additional data

## Database Structure

The plugin maintains compatibility with existing tables:
- `wp_fwd_films` - Original film records
- `wp_fwd_actors` - Actor records
- `wp_fwd_characters` - Character records
- `wp_fwd_watches` - Watch records
- `wp_fwd_brands` - Brand records
- `wp_fwd_film_actor_watch` - Junction table with watch sightings

## URL Structure

- Movies: `/movie/[slug]/`
- Actors: `/actor/[slug]/` (future)
- Watches: `/watch/[slug]/` (future)

## Template Customization

You can override templates by copying them to your theme:

```
your-theme/
  film-watch-wiki/
    single-fww_movie.php
    single-fww_actor.php
    single-fww_watch.php
    archive-fww_movie.php
```

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Film Watch Database plugin (for existing data)
- TMDB API account

## Version History

### 1.0.0 (2024)
- Initial release
- Movie Custom Post Type with TMDB integration
- Watch sighting display from existing database
- Wiki-style movie pages with cast, posters, and metadata

## Credits

- TMDB API for movie data
- Film Watch Database for the original plugin architecture
- WordPress community

## License

GPL v2 or later
