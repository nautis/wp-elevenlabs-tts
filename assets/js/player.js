/**
 * ElevenLabs TTS Player JavaScript
 *
 * @package ElevenLabs_TTS
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Helper function to show messages (XSS-safe)
     *
     * @param {jQuery} $container Container element
     * @param {string} message Message text
     * @param {string} type Message type ('success' or 'error')
     */
    function showMessage($container, message, type) {
        var messageClass = type === 'success' ? 'elevenlabs-success' : 'elevenlabs-error';

        // Use .text() instead of HTML to prevent XSS
        var $message = $('<div></div>')
            .addClass(messageClass)
            .text(message);

        // Remove any existing messages
        $container.find('.elevenlabs-success, .elevenlabs-error').remove();

        // Add new message
        $container.append($message);

        // Auto-remove error messages after 5 seconds
        if (type === 'error') {
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    /**
     * Initialize on DOM ready
     */
    $(function() {

        // Handle generate button click
        $('.elevenlabs-generate-btn').on('click', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var $container = $button.closest('.elevenlabs-generate-container, .elevenlabs-player');
            var $progress = $container.find('.elevenlabs-progress');

            // Disable button and show progress
            $button.prop('disabled', true).text('Generating...');
            $progress.show();

            // Make AJAX request
            $.ajax({
                url: elevenlabsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elevenlabs_generate_audio',
                    post_id: postId,
                    nonce: elevenlabsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showMessage($container, 'Audio generated successfully! Reloading page...', 'success');

                        // Reload page after 1 second with cache busting
                        setTimeout(function() {
                            location.href = location.href.split('?')[0] + '?t=' + Date.now();
                        }, 1000);
                    } else {
                        // Show error message (safely escaped by showMessage)
                        var errorMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Failed to generate audio';
                        showMessage($container, errorMsg, 'error');

                        // Re-enable button
                        $button.prop('disabled', false).text('Generate Audio');
                        $progress.hide();
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message (safely escaped by showMessage)
                    showMessage($container, 'An error occurred: ' + error, 'error');

                    // Re-enable button
                    $button.prop('disabled', false).text('Generate Audio');
                    $progress.hide();
                }
            });
        });

        // Handle regenerate button click
        $('.elevenlabs-regenerate-btn').on('click', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var $container = $button.closest('.elevenlabs-player');

            if (!confirm('Are you sure you want to regenerate the audio? This will replace the existing audio file.')) {
                return;
            }

            // Find or create progress container
            var $progress = $container.find('.elevenlabs-progress');
            if ($progress.length === 0) {
                $progress = $('<div class="elevenlabs-progress"></div>')
                    .append($('<span class="elevenlabs-spinner"></span>'))
                    .append($('<span class="elevenlabs-status-text"></span>').text('Generating audio...'));
                $container.append($progress);
            }

            // Disable button and show progress
            $button.prop('disabled', true).text('Regenerating...');
            $progress.show();

            // Make AJAX request
            $.ajax({
                url: elevenlabsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elevenlabs_generate_audio',
                    post_id: postId,
                    force: true,
                    nonce: elevenlabsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showMessage($container, 'Audio regenerated successfully! Reloading page...', 'success');

                        // Reload page after 1 second with cache busting
                        setTimeout(function() {
                            location.href = location.href.split('?')[0] + '?t=' + Date.now();
                        }, 1000);
                    } else {
                        // Show error message (safely escaped by showMessage)
                        var errorMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Failed to regenerate audio';
                        showMessage($container, errorMsg, 'error');

                        // Re-enable button
                        $button.prop('disabled', false).text('Regenerate');
                        $progress.hide();
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message (safely escaped by showMessage)
                    showMessage($container, 'An error occurred: ' + error, 'error');

                    // Re-enable button
                    $button.prop('disabled', false).text('Regenerate');
                    $progress.hide();
                }
            });
        });

        // Add keyboard accessibility
        $('.elevenlabs-generate-btn, .elevenlabs-regenerate-btn').on('keypress', function(e) {
            if (e.which === 13 || e.which === 32) { // Enter or Space
                e.preventDefault();
                $(this).click();
            }
        });

        // Initialize synchronized highlighting if available
        initSyncHighlighting();
    });

    /**
     * Synchronized Text Highlighting
     *
     * Highlights words in the actual article content as audio plays,
     * using word-level timestamps from ElevenLabs API.
     */

    var syncState = {
        words: [],           // Timestamp data from API
        wordSpans: [],       // DOM spans we've wrapped around words
        wordMapping: [],     // Maps timestamp index -> page word index (for fuzzy matching)
        currentWordIndex: -1,
        currentPageIndex: -1, // Actual page word being highlighted
        isEnabled: false,
        audio: null,
        animationFrameId: null,  // For smooth 60fps updates
        autoScrollEnabled: true, // Allow user to scroll away
        lastUserScroll: 0        // Track when user last scrolled
    };

    /**
     * Initialize synchronized highlighting
     */
    function initSyncHighlighting() {
        // Check if we have timestamps data
        if (typeof elevenlabsData === 'undefined' || !elevenlabsData.wordTimestamps) {
            return;
        }

        try {
            syncState.words = JSON.parse(elevenlabsData.wordTimestamps);
        } catch (e) {
            console.warn('ElevenLabs TTS: Failed to parse word timestamps', e);
            return;
        }

        if (!syncState.words || syncState.words.length === 0) {
            return;
        }

        // Get the audio element
        syncState.audio = document.querySelector('.elevenlabs-audio-element');
        if (!syncState.audio) {
            return;
        }

        // Find and process the article containers (title + content)
        var containers = findArticleContainers();
        if (containers.length === 0) {
            console.warn('ElevenLabs TTS: Could not find article content');
            return;
        }

        // Wrap words in each container
        containers.forEach(function(container) {
            wrapArticleWords(container);
        });

        if (syncState.wordSpans.length === 0) {
            console.warn('ElevenLabs TTS: No words wrapped');
            return;
        }

        // Build word mapping to handle ElevenLabs text normalization
        // (e.g., "41mm" on page becomes "41 millimeters" in audio)
        buildWordMapping();

        // Set up audio event listeners for smooth 60fps highlighting
        syncState.audio.addEventListener('play', startHighlightLoop);
        syncState.audio.addEventListener('pause', stopHighlightLoop);
        syncState.audio.addEventListener('seeking', handleSeeking);
        syncState.audio.addEventListener('ended', handleEnded);

        syncState.isEnabled = true;
        console.log('ElevenLabs TTS: Highlighting initialized (60fps) - ' + syncState.wordSpans.length + ' words in article, ' + syncState.words.length + ' timestamps');
    }

    /**
     * Start the 60fps highlight update loop
     */
    function startHighlightLoop() {
        if (syncState.animationFrameId) return; // Already running

        function loop() {
            if (syncState.isEnabled && syncState.audio && !syncState.audio.paused) {
                updateHighlight();
                syncState.animationFrameId = requestAnimationFrame(loop);
            } else {
                syncState.animationFrameId = null;
            }
        }
        syncState.animationFrameId = requestAnimationFrame(loop);
    }

    /**
     * Stop the highlight update loop
     */
    function stopHighlightLoop() {
        if (syncState.animationFrameId) {
            cancelAnimationFrame(syncState.animationFrameId);
            syncState.animationFrameId = null;
        }
    }

    /**
     * Find the article content containers (title + content)
     * Returns an array of elements to process
     */
    function findArticleContainers() {
        var containers = [];

        // First, try to find the title
        var titleSelectors = [
            '.entry-title',
            '.post-title',
            'article h1',
            'h1.title'
        ];

        for (var i = 0; i < titleSelectors.length; i++) {
            var titleEl = document.querySelector(titleSelectors[i]);
            if (titleEl) {
                containers.push(titleEl);
                break;
            }
        }

        // Then find the main content (exclude excerpt/summary variants)
        var contentSelectors = [
            '.entry-content:not(.excerpt):not(.summary)',
            '.post-content:not(.excerpt):not(.summary)',
            'article .content',
            '.article-content',
            'main article',
            '.single-post .content'
        ];

        for (var i = 0; i < contentSelectors.length; i++) {
            var contentEl = document.querySelector(contentSelectors[i]);
            if (contentEl) {
                containers.push(contentEl);
                break;
            }
        }

        return containers;
    }

    /**
     * Check if an element is inside the player container
     */
    function isInsidePlayer(element) {
        var current = element;
        while (current && current !== document.body) {
            if (current.classList && (
                current.classList.contains('elevenlabs-player') ||
                current.classList.contains('elevenlabs-audio-player-container')
            )) {
                return true;
            }
            current = current.parentNode;
        }
        return false;
    }

    /**
     * Wrap words in the article content with spans for highlighting
     */
    /**
     * Check if an element is inside a skipped container (captions, code blocks, etc.)
     * These elements are stripped by PHP content filter and not read in audio
     */
    function isInsideSkippedElement(element) {
        var current = element;
        while (current && current !== document.body) {
            if (current.tagName) {
                var tag = current.tagName.toUpperCase();
                // Skip elements that PHP filter strips (class-content-filter.php)
                if (tag === 'FIGCAPTION' ||  // Figure captions
                    tag === 'PRE' ||          // Code blocks
                    tag === 'CODE' ||         // Inline code
                    tag === 'TABLE' ||        // Tables
                    tag === 'FORM' ||         // Forms
                    tag === 'BUTTON' ||       // Buttons
                    tag === 'IFRAME') {       // Embeds
                    return true;
                }
                // Also check for WordPress caption class
                if (current.classList && current.classList.contains('wp-caption-text')) {
                    return true;
                }
            }
            current = current.parentNode;
        }
        return false;
    }

    function wrapArticleWords(container) {
        // Walk through all text nodes
        var walker = document.createTreeWalker(
            container,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip empty text nodes
                    if (!node.textContent.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip nodes inside scripts/styles
                    var parent = node.parentNode;
                    if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE' ||
                        parent.tagName === 'NOSCRIPT') {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip nodes inside the player container
                    if (isInsidePlayer(node)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip nodes inside elements that are stripped from audio
                    if (isInsideSkippedElement(node)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );

        var textNodes = [];
        var node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        // Process each text node
        textNodes.forEach(function(textNode) {
            var text = textNode.textContent;
            var parent = textNode.parentNode;

            // Don't process if parent is already a highlight span
            if (parent.classList && parent.classList.contains('elevenlabs-word')) {
                return;
            }

            // Split text into words while preserving whitespace
            var fragment = document.createDocumentFragment();
            var regex = /(\S+)(\s*)/g;
            var match;
            var hasContent = false;

            while ((match = regex.exec(text)) !== null) {
                var word = match[1];
                var whitespace = match[2];

                // Create span for the word
                var span = document.createElement('span');
                span.className = 'elevenlabs-word';
                span.setAttribute('data-word-index', syncState.wordSpans.length);
                span.textContent = word;

                syncState.wordSpans.push(span);
                fragment.appendChild(span);
                hasContent = true;

                // Preserve whitespace
                if (whitespace) {
                    fragment.appendChild(document.createTextNode(whitespace));
                }
            }

            // Handle text that's only whitespace
            if (!hasContent && text) {
                fragment.appendChild(document.createTextNode(text));
            }

            // Replace the text node with our fragment
            if (fragment.childNodes.length > 0) {
                parent.replaceChild(fragment, textNode);
            }
        });
    }

    /**
     * Build mapping between timestamp words and page words
     * Handles ElevenLabs text normalization like "41mm" → "41 millimeters"
     */
    function buildWordMapping() {
        var pageWords = [];
        syncState.wordSpans.forEach(function(span) {
            pageWords.push(normalizeWord(span.textContent));
        });

        var timestampWords = [];
        syncState.words.forEach(function(w) {
            timestampWords.push(normalizeWord(w.word));
        });

        // Build mapping using sequence alignment
        var mapping = [];
        var pageIndex = 0;

        for (var tsIndex = 0; tsIndex < timestampWords.length; tsIndex++) {
            var tsWord = timestampWords[tsIndex];

            if (pageIndex >= pageWords.length) {
                // No more page words, map to last
                mapping.push(pageWords.length - 1);
                continue;
            }

            var pageWord = pageWords[pageIndex];

            // Exact match - advance both
            if (tsWord === pageWord) {
                mapping.push(pageIndex);
                pageIndex++;
                continue;
            }

            // Timestamp word is part of page word (e.g., "41" matches start of "41mm")
            if (pageWord.indexOf(tsWord) === 0 ||
                (tsWord.length >= 2 && pageWord.indexOf(tsWord) !== -1)) {
                mapping.push(pageIndex);
                // Don't advance pageIndex - next timestamp word might also match this page word
                continue;
            }

            // Check if this is a known expansion (e.g., "millimeters" for "mm" in "41mm")
            if (isExpansionOf(tsWord, pageWord)) {
                mapping.push(pageIndex);
                pageIndex++; // Move past the abbreviated page word
                continue;
            }

            // Look ahead: maybe current page word was skipped in audio
            var foundAhead = false;
            for (var lookAhead = 1; lookAhead <= 3 && pageIndex + lookAhead < pageWords.length; lookAhead++) {
                var futurePageWord = pageWords[pageIndex + lookAhead];
                if (tsWord === futurePageWord || futurePageWord.indexOf(tsWord) === 0) {
                    // Skip page words that weren't in audio
                    pageIndex += lookAhead;
                    mapping.push(pageIndex);
                    pageIndex++;
                    foundAhead = true;
                    break;
                }
            }
            if (foundAhead) continue;

            // Look ahead in timestamps: maybe audio has extra words not on page
            var nextTsWord = tsIndex + 1 < timestampWords.length ? timestampWords[tsIndex + 1] : null;
            if (nextTsWord && (nextTsWord === pageWord || pageWord.indexOf(nextTsWord) === 0)) {
                // This timestamp word is extra (expansion), map to current page word
                mapping.push(pageIndex);
                continue;
            }

            // Default: map to current and advance
            mapping.push(pageIndex);
            pageIndex++;
        }

        syncState.wordMapping = mapping;
        console.log('ElevenLabs TTS: Word mapping built - ' + mapping.length + ' timestamp words mapped to ' + pageWords.length + ' page words');
    }

    /**
     * Normalize a word for comparison
     */
    function normalizeWord(word) {
        return word.toLowerCase()
            .replace(/[.,!?;:'"()\[\]{}""''–—]/g, '')
            .trim();
    }

    /**
     * Check if expanded word is an expansion of abbreviated page word
     * e.g., "millimeters" is expansion of "mm" (found in "41mm")
     */
    function isExpansionOf(expanded, pageWord) {
        var expansions = {
            'millimeters': 'mm',
            'millimeter': 'mm',
            'centimeters': 'cm',
            'centimeter': 'cm',
            'reference': 'ref',
            'versus': 'vs',
            'mister': 'mr',
            'doctor': 'dr',
            'approximately': 'approx',
            'number': 'no',
            'and': '&'
        };

        var abbrev = expansions[expanded];
        if (abbrev && pageWord.indexOf(abbrev) !== -1) {
            return true;
        }
        return false;
    }

    /**
     * Update highlight to current word (called 60fps during playback)
     */
    function updateHighlight() {
        if (!syncState.isEnabled) {
            return;
        }

        var currentTime = syncState.audio.currentTime;

        // Find the timestamp word at current time
        var timestampIndex = findWordAtTime(currentTime);

        // Map to page word index
        var pageIndex = -1;
        if (timestampIndex >= 0 && timestampIndex < syncState.wordMapping.length) {
            pageIndex = syncState.wordMapping[timestampIndex];
        }

        if (pageIndex !== syncState.currentPageIndex) {
            // Remove highlight from previous word
            if (syncState.currentPageIndex >= 0 && syncState.currentPageIndex < syncState.wordSpans.length) {
                syncState.wordSpans[syncState.currentPageIndex].classList.remove('elevenlabs-word-active');
            }

            // Add highlight to current word
            if (pageIndex >= 0 && pageIndex < syncState.wordSpans.length) {
                var currentSpan = syncState.wordSpans[pageIndex];
                currentSpan.classList.add('elevenlabs-word-active');

                // Scroll into view if needed
                scrollWordIntoView(currentSpan);
            }

            syncState.currentPageIndex = pageIndex;
        }
    }

    /**
     * Find the word at the given time using binary search on timestamps
     */
    function findWordAtTime(time) {
        var words = syncState.words;
        var left = 0;
        var right = words.length - 1;

        while (left <= right) {
            var mid = Math.floor((left + right) / 2);
            var word = words[mid];

            if (time >= word.start && time < word.end) {
                return mid;
            } else if (time < word.start) {
                right = mid - 1;
            } else {
                left = mid + 1;
            }
        }

        // Return the last word that started before current time
        if (left > 0) {
            return left - 1;
        }
        return -1;
    }

    /**
     * Scroll a word element into view smoothly
     * Respects user scrolling - won't force scroll if user recently scrolled
     */
    function scrollWordIntoView(element) {
        if (!element) return;

        // Don't auto-scroll if user has scrolled within the last 3 seconds
        if (!syncState.autoScrollEnabled) {
            var timeSinceUserScroll = Date.now() - syncState.lastUserScroll;
            if (timeSinceUserScroll < 3000) {
                return;
            }
            // Re-enable auto-scroll after timeout
            syncState.autoScrollEnabled = true;
        }

        var rect = element.getBoundingClientRect();
        var viewportHeight = window.innerHeight;

        // Check if element is outside viewport (with generous margins)
        if (rect.top < 50 || rect.bottom > viewportHeight - 50) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }

    /**
     * Handle user scroll - temporarily disable auto-scroll
     */
    function handleUserScroll() {
        if (syncState.isEnabled && syncState.audio && !syncState.audio.paused) {
            syncState.autoScrollEnabled = false;
            syncState.lastUserScroll = Date.now();
        }
    }

    // Listen for user scroll events
    var scrollTimeout;
    window.addEventListener('scroll', function() {
        // Debounce to avoid excessive calls
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(handleUserScroll, 50);
    }, { passive: true });

    /**
     * Handle seeking - update highlight immediately
     */
    function handleSeeking() {
        syncState.currentWordIndex = -1;
        syncState.currentPageIndex = -1;
        updateHighlight();
    }

    /**
     * Handle ended event - reset highlighting and stop loop
     */
    function handleEnded() {
        stopHighlightLoop();
        if (syncState.currentPageIndex >= 0 && syncState.currentPageIndex < syncState.wordSpans.length) {
            syncState.wordSpans[syncState.currentPageIndex].classList.remove('elevenlabs-word-active');
        }
        syncState.currentPageIndex = -1;
    }

})(jQuery);
