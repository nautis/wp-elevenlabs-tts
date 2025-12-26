# studious-palm-tree

WordPress plugins for tellingtime.com.

## Plugins

### ElevenLabs TTS (Active)
Text-to-speech plugin using ElevenLabs API. See details below.

### watch-film-spotting / film-watch-database (Deprecated)
These plugins have been merged into a unified `watch-spotting` plugin deployed directly on the server. The code here is kept for reference but is no longer actively maintained in this repo.

---

# ElevenLabs Text-to-Speech WordPress Plugin

Convert your WordPress blog posts into high-quality audio using ElevenLabs AI text-to-speech technology.

## Features

- **On-Demand Audio Generation**: Generate audio for posts only when needed
- **Smart Content Filtering**: Automatically excludes code blocks, image captions, and other non-readable content
- **Server Storage**: Audio files are stored on your WordPress server for fast delivery
- **Customizable Voice Settings**: Choose from 3,000+ voices and fine-tune voice parameters
- **Beautiful Audio Player**: Clean, responsive player that appears below post titles
- **Easy Management**: Generate, regenerate, or delete audio directly from the post editor
- **Multiple Models**: Choose between highest quality (Multilingual v2) or fastest response (Turbo v2.5)

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Active ElevenLabs account with API key
- Write permissions for WordPress uploads directory

## Installation

### Method 1: Direct Upload

1. Download the plugin files
2. Upload the entire `elevenlabs-tts` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Settings → ElevenLabs TTS to configure

### Method 2: From Git Repository

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/nautis/studious-palm-tree.git elevenlabs-tts
```

Then activate through WordPress admin panel.

## Configuration

### 1. Get Your ElevenLabs API Key

1. Sign up or log in at [ElevenLabs](https://elevenlabs.io)
2. Navigate to your [API Keys page](https://elevenlabs.io/app/settings/api-keys)
3. Create a new API key or copy an existing one

### 2. Configure the Plugin

1. In WordPress admin, go to **Settings → ElevenLabs TTS**
2. Enter your API key
3. Click **Test Connection** to verify it works
4. Click **Fetch Voices** to load available voices from your account
5. Select your preferred voice from the list
6. Adjust voice parameters if desired:
   - **Stability**: Higher = more consistent, Lower = more variation (Default: 0.5)
   - **Clarity + Similarity**: Enhances voice clarity (Default: 0.75)
   - **Style Exaggeration**: Higher = more emotion (Default: 0.0)
   - **Speaker Boost**: Improves similarity to original voice (Recommended: On)
7. Click **Save Settings**

## Usage

### Generating Audio for Posts

#### From the Post Editor

1. Edit any post
2. Look for the "ElevenLabs Audio" meta box in the sidebar
3. Click **Generate Audio**
4. Wait for the generation to complete
5. Preview the audio in the meta box
6. Publish or update your post

#### From the Frontend

1. View any published post (must be logged in as editor/admin)
2. If no audio exists, you'll see a "Generate Audio" button
3. Click to generate audio on-demand
4. Page will reload with the audio player

### Managing Audio

- **Regenerate**: Click the "Regenerate" button to create new audio (useful after editing post content)
- **Delete**: Remove audio files from the post editor meta box
- **Auto-display**: Audio player automatically appears below the post title

## Content Filtering

The plugin automatically filters out:

- Code blocks (`<pre>`, `<code>` tags)
- Image captions
- Tables
- Forms and buttons
- Iframes (embedded videos)
- HTML comments and scripts
- Shortcodes

Images with alt text will have their alt text read aloud for accessibility.

## File Storage

Audio files are stored in:
```
/wp-content/uploads/elevenlabs-audio/
```

Files are named: `post-{POST_ID}-{TIMESTAMP}.mp3`

## Customization

### Hooks and Filters

#### Modify content before filtering

```php
add_filter('elevenlabs_tts_pre_filter_content', function($content, $post) {
    // Modify content before it's filtered
    return $content;
}, 10, 2);
```

#### Modify content after filtering

```php
add_filter('elevenlabs_tts_filtered_content', function($content, $post) {
    // Modify the final content sent to ElevenLabs
    return $content;
}, 10, 2);
```

#### Change character limit

```php
add_filter('elevenlabs_tts_max_characters', function($max_chars) {
    return 50000; // Change from default 100,000
});
```

### Shortcode Usage

If you set player position to "Manual" in settings, use this shortcode:

```
[elevenlabs_player]
```

## Troubleshooting

### Audio not generating

1. Check API key is correct in settings
2. Test connection from settings page
3. Check WordPress error logs
4. Verify voice is selected
5. Ensure post has content to convert

### Audio player not showing

1. Verify audio was generated successfully
2. Check file exists in `/wp-content/uploads/elevenlabs-audio/`
3. Verify player position setting
4. Clear WordPress cache if using caching plugin

### API Errors

- **401 Unauthorized**: Check API key is valid
- **429 Too Many Requests**: You've hit rate limits, wait and try again
- **Character limit exceeded**: Post content is too long, edit to reduce

## Development

### File Structure

```
elevenlabs-tts/
├── elevenlabs-tts.php          # Main plugin file
├── includes/
│   ├── class-elevenlabs-api.php        # API integration
│   ├── class-content-filter.php        # Content filtering
│   └── class-audio-generator.php       # Audio generation
├── admin/
│   └── class-admin-settings.php        # Admin settings page
├── assets/
│   ├── css/
│   │   ├── admin.css                   # Admin styles
│   │   └── player.css                  # Frontend player styles
│   └── js/
│       ├── admin.js                    # Admin JavaScript
│       └── player.js                   # Frontend player JavaScript
└── README.md
```

## API Reference

This plugin uses the ElevenLabs Text-to-Speech API. See the [official documentation](https://elevenlabs.io/docs/api-reference/introduction) for more details.

### Supported Models

- `eleven_multilingual_v2` - Highest quality, 32 languages
- `eleven_turbo_v2_5` - Ultra-low 75ms latency
- `eleven_turbo_v2` - Fast generation
- `eleven_monolingual_v1` - English only

### Output Format

Audio is generated in MP3 format at 44.1kHz, 128kbps for optimal quality and file size.

## Security

- API keys are stored securely in WordPress options
- Audio generation is restricted to users with post editing permissions
- API requests use WordPress HTTP API with proper error handling
- All user inputs are sanitized and validated

## Performance

- Audio is generated on-demand to save API credits
- Files are stored locally for fast delivery
- No impact on page load times (player loads asynchronously)
- Minimal database queries

## License

GPL v2 or later

## Support

For issues and feature requests, please use the [GitHub repository](https://github.com/nautis/studious-palm-tree).

## Changelog

### 1.0.0
- Initial release
- Text-to-speech conversion with ElevenLabs API
- On-demand audio generation
- Smart content filtering
- Customizable voice settings
- Audio player with management interface
