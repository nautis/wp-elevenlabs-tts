/**
 * Film Watch Database - Frontend JavaScript
 */

(function($) {
    'use strict';

    /**
     * Initialize search functionality
     */
    function initSearch() {
        const searchBtn = document.getElementById('fwd-search-btn');
        const searchInput = document.getElementById('fwd-search-input');

        if (!searchBtn || !searchInput) return;

        // Search on button click
        searchBtn.addEventListener('click', performSearch);

        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });

        // Check for URL parameters and perform search automatically
        checkUrlParameters();

        // Handle browser back/forward navigation
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.search) {
                performSearchFromState(e.state.search);
            }
        });
    }

    /**
     * Check URL parameters and perform search if present
     * Enhanced to detect server-side rendered results
     */
    function checkUrlParameters() {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type');
        const query = urlParams.get('q');

        if (type && query) {
            const searchType = document.getElementById('fwd-search-type');
            const searchInput = document.getElementById('fwd-search-input');
            const resultsContainer = document.getElementById('fwd-search-results');

            if (searchType && searchInput) {
                // Set the form values (ignore type from URL, always use all)
                searchInput.value = decodeURIComponent(query);

                // Check if results are already rendered server-side (for SEO)
                const container = document.querySelector('.fwd-search-container');
                const hasServerResults = resultsContainer && resultsContainer.querySelector('.fwd-results-list');

                if (hasServerResults) {
                    // Results already rendered by PHP - no need for AJAX
                    console.log('FWD: Using server-rendered results (SEO mode)');
                } else {
                    // No server results - perform AJAX search
                    performSearch(false); // Don't update URL since we're loading from URL
                }
            }
        }
    }

    /**
     * Perform search from browser state (back/forward navigation)
     */
    function performSearchFromState(searchState) {
        const searchType = document.getElementById('fwd-search-type');
        const searchInput = document.getElementById('fwd-search-input');

        if (searchType && searchInput) {
            // Always use unified search
            searchInput.value = searchState.query;
            performSearch(false); // Don't update URL
        }
    }

    /**
     * Perform search via AJAX
     */
    function performSearch(updateUrl = true) {
        const searchType = document.getElementById('fwd-search-type');
        const searchInput = document.getElementById('fwd-search-input');
        const resultsContainer = document.getElementById('fwd-search-results');
        const searchBtn = document.getElementById('fwd-search-btn');

        if (!searchType || !searchInput || !resultsContainer) return;

        const queryType = "all"; // Always use unified search
        const searchTerm = searchInput.value.trim();

        if (!searchTerm) {
            resultsContainer.innerHTML = '<div class="fwd-error">Please enter a search term.</div>';
            return;
        }

        // Update URL with search parameters
        if (updateUrl) {
            const newUrl = window.location.pathname + '?type=' +
                encodeURIComponent(queryType) + '&q=' + encodeURIComponent(searchTerm);

            // Add to browser history
            window.history.pushState({
                search: {
                    type: queryType,
                    query: searchTerm
                }
            }, '', newUrl);
        }

        // Show loading state
        searchBtn.disabled = true;
        searchBtn.textContent = 'Searching...';
        resultsContainer.innerHTML = '<div class="fwd-loading">Searching database...</div>';

        // Make AJAX request
        $.ajax({
            url: fwdAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fwd_search',
                nonce: fwdAjax.nonce,
                query_type: queryType,
                search_term: searchTerm
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data, queryType, resultsContainer);
                } else {
                    resultsContainer.innerHTML = '<div class="fwd-error">Error: ' +
                        (response.data.error || 'Unknown error occurred') + '</div>';
                }
            },
            error: function(xhr, status, error) {
                resultsContainer.innerHTML = '<div class="fwd-error">Network error: ' + error + '</div>';
            },
            complete: function() {
                searchBtn.disabled = false;
                searchBtn.textContent = 'Search';
            }
        });
    }

    /**
     * Display search results
     */
    function displayResults(data, queryType, container) {
        // Hide recently added section when showing search results
        const recentlyAdded = document.querySelector(".fwd-recently-added-container");
        if (recentlyAdded) recentlyAdded.style.display = "none";
        
        // Handle unified search results
        if (queryType === 'all') {
            displayUnifiedResults(data, container);
            return;
        }

        if (data.count === 0) {
            container.innerHTML = '<div class="fwd-no-results">No results found.</div>';
            return;
        }

        let html = '';
        if (queryType === 'film' && data.film_count) {
            var filmText = data.film_count === 1 ? 'film' : 'films';
            var watchText = data.count === 1 ? 'watch' : 'watches';
            html += '<div class="fwd-success">' + data.film_count + ' ' + filmText + ', ' + data.count + ' ' + watchText + '</div>';
        } else if (queryType === 'brand' && data.film_count) {
            var filmText = data.film_count === 1 ? 'film' : 'films';
            var watchText = data.count === 1 ? 'watch' : 'watches';
            html += '<div class="fwd-success">' + data.film_count + ' ' + filmText + ', ' + data.count + ' ' + watchText + '</div>';
        }
        html += '<div class="fwd-results-list">';

        if (queryType === 'actor' && data.films) {
            data.films.forEach(function(film) {
                html += buildActorResultHTML(film, film.actor);
            });
        } else if (queryType === 'brand' && data.films) {
            // Brand results are now grouped by film
            data.films.forEach(function(filmGroup) {
                html += buildFilmGroupHTML(filmGroup);
            });
        } else if (queryType === 'film' && data.films) {
            data.films.forEach(function(filmGroup) {
                html += buildFilmGroupHTML(filmGroup);
            });
        }

        html += '</div>';
        container.innerHTML = html;

        // Execute any scripts in the inserted HTML (needed for ReGallery)
        executeScriptsInElement(container);

        // Initialize ReGallery elements after scripts have run
        setTimeout(initReGalleries, 100);
    }

    /**

    /**
     * Display unified search results organized by film only
     * All searches return results grouped by film (film is the unique key)
     */
    function displayUnifiedResults(data, container) {
        if (data.total_count === 0 || !data.films || data.films.length === 0) {
            container.innerHTML = '<div class="fwd-no-results">No results found.</div>';
            return;
        }

        let html = '';

        // Only show films section - film is always the unique key
        var filmCount = data.films.length;
        var totalWatches = 0;
        data.films.forEach(function(filmGroup) {
            totalWatches += filmGroup.watches.length;
        });

        var filmText = filmCount === 1 ? 'film' : 'films';
        var watchText = totalWatches === 1 ? 'watch' : 'watches';

        html += '<div class="fwd-results-section">';
        html += '<h3 class="fwd-section-title">' + filmCount + ' ' + filmText + ', ' + totalWatches + ' ' + watchText + '</h3>';
        html += '<div class="fwd-results-list">';
        data.films.forEach(function(filmGroup) {
            html += buildFilmGroupHTML(filmGroup);
        });
        html += '</div></div>';

        container.innerHTML = html;

        // Execute any scripts in the inserted HTML (needed for ReGallery)
        executeScriptsInElement(container);

        // Initialize ReGallery elements after scripts have run
        setTimeout(initReGalleries, 100);
    }

    /**
     * Build HTML for actor search result (natural language)
     */
    function buildActorResultHTML(film, actorName) {
        let html = '<div class="fwd-entry">';

        // Use pre-rendered gallery HTML if available (includes ReGallery)
        if (film.gallery_html) {
            html += film.gallery_html;
        } else if (film.image_url) {
            html += `<figure>`;
            html += `<img src="${escapeHtml(film.image_url)}" alt="${escapeHtml(film.brand)} ${escapeHtml(film.model)}">`;
            if (film.image_caption) {
                html += `<figcaption class="wp-element-caption">${escapeHtml(film.image_caption)}</figcaption>`;
            }
            html += `</figure>`;
        }

        html += `<p>The <strong class="fwd-watch">${escapeHtml(film.brand)} ${escapeHtml(film.model)}</strong> appears in the ${escapeHtml(film.year)} film <strong>${escapeHtml(film.title)}</strong>, worn by <strong>${escapeHtml(actorName)}</strong> as <strong>${escapeHtml(film.character)}</strong>.`;

        // Escape narrative for XSS protection
        if (film.narrative) {
            html += ` ${escapeHtml(film.narrative)}`;
        }

        html += '</p>';

        if (film.confidence_level || film.source) {
            html += '<p>';
            if (film.confidence_level) {
                html += `<em class="fwd-confidence">Confidence: ${escapeHtml(film.confidence_level)}</em>`;
            }
            if (film.source) {
                html += ` <a href="${escapeHtml(film.source)}" target="_blank" rel="noopener">Source ↗</a>`;
            }
            html += '</p>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Build HTML for brand search result (natural language)
     */
    function buildBrandResultHTML(film, brandName) {
        let html = '<div class="fwd-entry">';

        if (film.image_url) {
            html += `<figure>`;
            html += `<img src="${escapeHtml(film.image_url)}" alt="${escapeHtml(brandName)} ${escapeHtml(film.model)}">`;
            if (film.image_caption) {
                html += `<figcaption class="wp-element-caption">${escapeHtml(film.image_caption)}</figcaption>`;
            }
            html += `</figure>`;
        }

        html += `<p>The <strong class="fwd-watch">${escapeHtml(brandName)} ${escapeHtml(film.model)}</strong> appears in the ${escapeHtml(film.year)} film <strong>${escapeHtml(film.title)}</strong>, worn by <strong>${escapeHtml(film.actor)}</strong> as <strong>${escapeHtml(film.character)}</strong>.`;

        // Escape narrative for XSS protection
        if (film.narrative) {
            html += ` ${escapeHtml(film.narrative)}`;
        }

        html += '</p>';

        if (film.confidence_level || film.source) {
            html += '<p>';
            if (film.confidence_level) {
                html += `<em class="fwd-confidence">Confidence: ${escapeHtml(film.confidence_level)}</em>`;
            }
            if (film.source) {
                html += ` <a href="${escapeHtml(film.source)}" target="_blank" rel="noopener">Source ↗</a>`;
            }
            html += '</p>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Build HTML for film search result (natural language)
     */
    /**
     * Build HTML for grouped film results (film with multiple watches)
     */
    function buildFilmGroupHTML(filmGroup) {
        let html = '<div class="fwd-film-group">';
        
        // Film header
        html += '<h3 class="fwd-film-title">';
        html += escapeHtml(filmGroup.title) + ' (' + escapeHtml(filmGroup.year) + ') - ';
        html += filmGroup.watches.length + ' watch' + (filmGroup.watches.length !== 1 ? 'es' : '');
        html += '</h3>';
        
        html += '<div class="fwd-film-watches">';
        
        // Each watch in this film
        filmGroup.watches.forEach(function(watch) {
            html += buildFilmResultHTML(watch);
        });
        
        html += '</div>'; // fwd-film-watches
        html += '</div>'; // fwd-film-group
        
        return html;
    }


    function buildFilmResultHTML(watch) {
        let html = '<div class="fwd-entry">';

        // Use pre-rendered gallery HTML if available (includes ReGallery)
        if (watch.gallery_html) {
            html += watch.gallery_html;
        } else if (watch.image_url) {
            html += `<figure>`;
            html += `<img src="${escapeHtml(watch.image_url)}" alt="${escapeHtml(watch.brand)} ${escapeHtml(watch.model)}">`;
            if (watch.image_caption) {
                html += `<figcaption class="wp-element-caption">${escapeHtml(watch.image_caption)}</figcaption>`;
            }
            html += `</figure>`;
        }

        html += `<p>The <strong class="fwd-watch">${escapeHtml(watch.brand)} ${escapeHtml(watch.model)}</strong> appears in the ${escapeHtml(watch.year)} film <strong>${escapeHtml(watch.title)}</strong>, worn by <strong>${escapeHtml(watch.actor)}</strong> as <strong>${escapeHtml(watch.character)}</strong>.`;

        // Escape narrative for XSS protection
        if (watch.narrative) {
            html += ` ${escapeHtml(watch.narrative)}`;
        }

        html += '</p>';

        if (watch.confidence_level || watch.source) {
            html += '<p>';
            if (watch.confidence_level) {
                html += `<em class="fwd-confidence">Confidence: ${escapeHtml(watch.confidence_level)}</em>`;
            }
            if (watch.source) {
                html += ` <a href="${escapeHtml(watch.source)}" target="_blank" rel="noopener">Source ↗</a>`;
            }
            html += '</p>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Initialize add entry form
     */
    function initAddForm() {
        const addBtn = document.getElementById('fwd-add-btn');

        if (!addBtn) return;

        addBtn.addEventListener('click', addEntry);

        // Initialize media uploader
        initMediaUploader();
    }

    /**
     * Initialize WordPress media uploader
     */
    function initMediaUploader() {
        const uploadBtn = document.getElementById('fwd-upload-image-btn');
        const imageUrlInput = document.getElementById('fwd-image-url');
        const galleryIdsInput = document.getElementById('fwd-gallery-ids');
        const galleryPreview = document.getElementById('fwd-gallery-preview');

        if (!uploadBtn || !imageUrlInput) return;

        // Check if wp.media is available
        if (typeof wp === 'undefined' || !wp.media) {
            uploadBtn.style.display = 'none';
            return;
        }

        let mediaUploader;

        uploadBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // If the uploader object has already been created, reopen the dialog
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            // Extend the wp.media object
            mediaUploader = wp.media({
                title: 'Select Watch Images (Gallery)',
                button: {
                    text: 'Use these images'
                },
                multiple: true
            });

            // When images are selected, run a callback
            mediaUploader.on('select', function() {
                const attachments = mediaUploader.state().get('selection').toJSON();

                // Extract attachment IDs and URLs
                const attachmentIds = attachments.map(att => att.id);
                const attachmentUrls = attachments.map(att => att.url);

                // Store IDs in hidden field as JSON array
                if (galleryIdsInput) {
                    galleryIdsInput.value = JSON.stringify(attachmentIds);
                }

                // For backwards compatibility, set first image URL in the image_url field
                if (attachments.length > 0) {
                    imageUrlInput.value = attachments[0].url;
                }

                // Update preview display
                if (galleryPreview) {
                    updateGalleryPreview(attachments);
                }
            });

            // Open the uploader dialog
            mediaUploader.open();
        });
    }

    /**
     * Update gallery preview with selected images
     */
    function updateGalleryPreview(attachments) {
        const galleryPreview = document.getElementById('fwd-gallery-preview');
        if (!galleryPreview) return;

        if (attachments.length === 0) {
            galleryPreview.innerHTML = '<p class="description">No images selected</p>';
            return;
        }

        let previewHTML = '<div class="fwd-gallery-thumbnails">';

        attachments.forEach((attachment, index) => {
            const thumbUrl = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            previewHTML += `
                <div class="fwd-gallery-thumb">
                    <img src="${thumbUrl}" alt="Gallery image ${index + 1}">
                    <button type="button" class="fwd-remove-thumb" data-attachment-id="${attachment.id}">×</button>
                </div>
            `;
        });

        previewHTML += '</div>';
        previewHTML += `<p class="description">${attachments.length} image(s) selected</p>`;

        galleryPreview.innerHTML = previewHTML;

        // Add remove handlers
        galleryPreview.querySelectorAll('.fwd-remove-thumb').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                removeGalleryImage(parseInt(this.dataset.attachmentId));
            });
        });
    }

    /**
     * Remove an image from the gallery selection
     */
    function removeGalleryImage(attachmentId) {
        const galleryIdsInput = document.getElementById('fwd-gallery-ids');
        if (!galleryIdsInput || !galleryIdsInput.value) return;

        try {
            let ids = JSON.parse(galleryIdsInput.value);
            ids = ids.filter(id => id !== attachmentId);
            galleryIdsInput.value = JSON.stringify(ids);

            // Refresh preview - we need to reconstruct attachments array
            // For simplicity, just show count
            const galleryPreview = document.getElementById('fwd-gallery-preview');
            if (galleryPreview) {
                if (ids.length === 0) {
                    galleryPreview.innerHTML = '<p class="description">No images selected</p>';
                } else {
                    galleryPreview.innerHTML = `<p class="description">${ids.length} image(s) selected. Click "Select Images" to modify.</p>`;
                }
            }
        } catch (e) {
            console.error('Error removing gallery image:', e);
        }
    }

    /**
     * Show duplicate comparison modal
     */
    function showDuplicateModal(existing, newData, onReplace, onCancel) {
        // Create modal HTML
        const modalHTML = `
            <div id="fwd-duplicate-modal" class="fwd-modal-overlay">
                <div class="fwd-modal">
                    <div class="fwd-modal-header">
                        <h3>Duplicate Entry Detected</h3>
                    </div>
                    <div class="fwd-modal-body">
                        <p><strong>This entry already exists in the database.</strong> Would you like to replace it with the new data?</p>

                        <div class="fwd-comparison">
                            <div class="fwd-comparison-column">
                                <h4>Current (in database):</h4>
                                <table class="fwd-comparison-table">
                                    <tr><td><strong>Actor:</strong></td><td>${escapeHtml(existing.actor || '')}</td></tr>
                                    <tr><td><strong>Character:</strong></td><td>${escapeHtml(existing.character || '')}</td></tr>
                                    <tr><td><strong>Watch:</strong></td><td>${escapeHtml(existing.brand || '')} ${escapeHtml(existing.model || '')}</td></tr>
                                    <tr><td><strong>Film:</strong></td><td>${escapeHtml(existing.title || '')} (${escapeHtml(existing.year || '')})</td></tr>
                                    <tr><td><strong>Narrative:</strong></td><td>${escapeHtml(existing.narrative || 'None')}</td></tr>
                                    <tr><td><strong>Image:</strong></td><td>${existing.image_url ? 'Yes' : 'No'}</td></tr>
                                    <tr><td><strong>Confidence:</strong></td><td>${existing.confidence_level ? escapeHtml(existing.confidence_level) : 'None'}</td></tr>
                                </table>
                            </div>
                            <div class="fwd-comparison-column">
                                <h4>New (your input):</h4>
                                <table class="fwd-comparison-table">
                                    <tr><td><strong>Actor:</strong></td><td>${escapeHtml(newData.actor || '')}</td></tr>
                                    <tr><td><strong>Character:</strong></td><td>${escapeHtml(newData.character || '')}</td></tr>
                                    <tr><td><strong>Watch:</strong></td><td>${escapeHtml(newData.brand || '')} ${escapeHtml(newData.model || '')}</td></tr>
                                    <tr><td><strong>Film:</strong></td><td>${escapeHtml(newData.title || '')} (${escapeHtml(newData.year || '')})</td></tr>
                                    <tr><td><strong>Narrative:</strong></td><td>${escapeHtml(newData.narrative || 'None')}</td></tr>
                                    <tr><td><strong>Image:</strong></td><td>${newData.image_url ? 'Yes' : 'No'}</td></tr>
                                    <tr><td><strong>Confidence:</strong></td><td>${newData.confidence_level ? escapeHtml(newData.confidence_level) : 'None'}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="fwd-modal-footer">
                        <button id="fwd-modal-cancel" class="fwd-button fwd-button-secondary">Keep Existing</button>
                        <button id="fwd-modal-replace" class="fwd-button fwd-button-primary">Replace with New</button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Add event listeners
        document.getElementById('fwd-modal-cancel').addEventListener('click', function() {
            document.getElementById('fwd-duplicate-modal').remove();
            if (onCancel) onCancel();
        });

        document.getElementById('fwd-modal-replace').addEventListener('click', function() {
            document.getElementById('fwd-duplicate-modal').remove();
            if (onReplace) onReplace();
        });

        // Close on overlay click
        document.getElementById('fwd-duplicate-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.remove();
                if (onCancel) onCancel();
            }
        });
    }

    /**
     * Add new entry via AJAX
     */
    function addEntry(forceOverwrite = false) {
        const entryText = document.getElementById('fwd-entry-text');
        const narrative = document.getElementById('fwd-narrative');
        const imageUrl = document.getElementById('fwd-image-url');
        const galleryIds = document.getElementById('fwd-gallery-ids');
        const confidence = document.getElementById('fwd-confidence');
        const resultDiv = document.getElementById('fwd-add-result');
        const addBtn = document.getElementById('fwd-add-btn');

        if (!entryText || !narrative || !resultDiv) return;

        const entryValue = entryText.value.trim();
        const narrativeValue = narrative.value.trim();
        const imageUrlValue = imageUrl ? imageUrl.value.trim() : '';
        const galleryIdsValue = galleryIds ? galleryIds.value.trim() : '';
        const confidenceValue = confidence ? confidence.value.trim() : '';

        if (!entryValue) {
            showResult(resultDiv, 'fwd-error', 'Please enter an entry text.');
            return;
        }

        // Show loading state
        addBtn.disabled = true;
        addBtn.textContent = 'Adding...';
        showResult(resultDiv, 'fwd-loading', 'Adding entry to database...');

        // Make AJAX request
        $.ajax({
            url: fwdAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fwd_add_entry',
                nonce: fwdAjax.nonce,
                entry_text: entryValue,
                narrative: narrativeValue,
                image_url: imageUrlValue,
                gallery_ids: galleryIdsValue,
                confidence_level: confidenceValue,
                force_overwrite: forceOverwrite ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    showResult(resultDiv, 'fwd-success', '✓ ' + response.data.message);
                    entryText.value = '';
                    narrative.value = '';
                    if (imageUrl) imageUrl.value = '';
                    if (galleryIds) galleryIds.value = '';
                    if (confidence) confidence.value = '';

                    // Clear gallery preview
                    const galleryPreview = document.getElementById('fwd-gallery-preview');
                    if (galleryPreview) {
                        galleryPreview.innerHTML = '<p class="description">No images selected</p>';
                    }
                } else if (response.data && response.data.is_duplicate) {
                    // Show duplicate comparison modal
                    showDuplicateModal(
                        response.data.existing,
                        response.data.new,
                        function() {
                            // User clicked "Replace with New"
                            addEntry(true); // Retry with force_overwrite=true
                        },
                        function() {
                            // User clicked "Keep Existing"
                            showResult(resultDiv, 'fwd-success', 'Kept existing entry - no changes made');
                            addBtn.disabled = false;
                            addBtn.textContent = 'Add to Database';
                        }
                    );
                    return; // Don't reset button state yet
                } else {
                    showResult(resultDiv, 'fwd-error', 'Error: ' +
                        (response.data.error || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                showResult(resultDiv, 'fwd-error', 'Network error: ' + error);
            },
            complete: function() {
                addBtn.disabled = false;
                addBtn.textContent = 'Add to Database';
            }
        });
    }

    /**
     * Show result message
     */
    function showResult(element, className, message) {
        element.className = 'fwd-result show ' + className;
        element.textContent = message;
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Initialize tab switching
     */
    function initTabs() {
        const tabBtns = document.querySelectorAll('.fwd-tab-btn');
        const tabContents = document.querySelectorAll('.fwd-tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');

                // Remove active class from all tabs
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');
                document.getElementById('fwd-tab-' + tabName).classList.add('active');
            });
        });

        // Initialize quick entry button
        const quickAddBtn = document.getElementById('fwd-quick-add-btn');
        if (quickAddBtn) {
            quickAddBtn.addEventListener('click', addQuickEntry);
        }

        // Initialize CSV upload button
        const csvUploadBtn = document.getElementById('fwd-csv-upload-btn');
        if (csvUploadBtn) {
            csvUploadBtn.addEventListener('click', uploadCSV);
        }
    }

    /**
     * Add quick entry (pipe-delimited)
     */
    function addQuickEntry(forceOverwrite = false) {
        const quickEntry = document.getElementById('fwd-quick-entry');
        const resultDiv = document.getElementById('fwd-quick-result');
        const quickAddBtn = document.getElementById('fwd-quick-add-btn');

        if (!quickEntry || !resultDiv) return;

        const entryValue = quickEntry.value.trim();

        if (!entryValue) {
            showResult(resultDiv, 'fwd-error', 'Please enter a pipe-delimited entry.');
            return;
        }

        // Show loading state
        quickAddBtn.disabled = true;
        quickAddBtn.textContent = 'Adding...';
        showResult(resultDiv, 'fwd-loading', 'Adding entry to database...');

        // Make AJAX request
        $.ajax({
            url: fwdAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fwd_add_quick_entry',
                nonce: fwdAjax.nonce,
                quick_entry: entryValue,
                force_overwrite: forceOverwrite ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    showResult(resultDiv, 'fwd-success', '✓ ' + response.data.message);
                    quickEntry.value = '';
                } else if (response.data && response.data.is_duplicate) {
                    // Show duplicate comparison modal
                    showDuplicateModal(
                        response.data.existing,
                        response.data.new,
                        function() {
                            // User clicked "Replace with New"
                            addQuickEntry(true); // Retry with force_overwrite=true
                        },
                        function() {
                            // User clicked "Keep Existing"
                            showResult(resultDiv, 'fwd-success', 'Kept existing entry - no changes made');
                            quickAddBtn.disabled = false;
                            quickAddBtn.textContent = 'Add to Database';
                        }
                    );
                    return; // Don't reset button state yet
                } else {
                    showResult(resultDiv, 'fwd-error', 'Error: ' +
                        (response.data.error || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                showResult(resultDiv, 'fwd-error', 'Network error: ' + error);
            },
            complete: function() {
                quickAddBtn.disabled = false;
                quickAddBtn.textContent = 'Add to Database';
            }
        });
    }

    /**
     * Upload CSV file
     */
    function uploadCSV() {
        const csvFile = document.getElementById('fwd-csv-file');
        const resultDiv = document.getElementById('fwd-csv-result');
        const csvUploadBtn = document.getElementById('fwd-csv-upload-btn');

        if (!csvFile || !resultDiv) return;

        const file = csvFile.files[0];

        if (!file) {
            showResult(resultDiv, 'fwd-error', 'Please select a CSV file to upload.');
            return;
        }

        // Check file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            showResult(resultDiv, 'fwd-error', 'File size exceeds 5MB limit.');
            return;
        }

        // Show loading state
        csvUploadBtn.disabled = true;
        csvUploadBtn.textContent = 'Importing...';
        showResult(resultDiv, 'fwd-loading', 'Importing CSV file...');

        // Read file content
        const reader = new FileReader();
        reader.onload = function(e) {
            const csvContent = e.target.result;

            // Make AJAX request
            $.ajax({
                url: fwdAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fwd_import_csv',
                    nonce: fwdAjax.nonce,
                    csv_content: csvContent
                },
                success: function(response) {
                    if (response.success) {
                        showResult(resultDiv, 'fwd-success', '✓ ' + response.data.message);
                        csvFile.value = '';
                    } else {
                        showResult(resultDiv, 'fwd-error', 'Error: ' +
                            (response.data.error || 'Unknown error occurred'));
                    }
                },
                error: function(xhr, status, error) {
                    showResult(resultDiv, 'fwd-error', 'Network error: ' + error);
                },
                complete: function() {
                    csvUploadBtn.disabled = false;
                    csvUploadBtn.textContent = 'Import CSV';
                }
            });
        };

        reader.onerror = function() {
            showResult(resultDiv, 'fwd-error', 'Failed to read file.');
            csvUploadBtn.disabled = false;
            csvUploadBtn.textContent = 'Import CSV';
        };

        reader.readAsText(file);
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initSearch();
        initAddForm();
        initTabs();
    });


    /**
     * Execute script tags in dynamically inserted HTML
     * Needed for ReGallery which uses inline scripts for React initialization
     */
    function executeScriptsInElement(element) {
        const scripts = element.querySelectorAll('script');
        scripts.forEach(function(oldScript) {
            const newScript = document.createElement('script');

            // Copy attributes
            Array.from(oldScript.attributes).forEach(function(attr) {
                newScript.setAttribute(attr.name, attr.value);
            });

            // Copy inline script content
            newScript.textContent = oldScript.textContent;

            // Replace old script with new one to execute it
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });

        // Trigger ReGallery re-initialization if available
        if (typeof window.reacg_init === 'function') {
            window.reacg_init();
        }
    }

})(jQuery);

/**
 * Initialize any uninitialized ReGallery elements
 * ReGallery uses React and only initializes on page load.
 * This function manually triggers initialization for dynamically added galleries.
 */
function initReGalleries() {
    // Find all reacg-gallery elements that haven't been initialized yet
    const galleries = document.querySelectorAll('.reacg-gallery:not([data-initialized])');
    
    if (galleries.length === 0) return;
    
    // ReGallery stores its init function - we need to click the hidden loadApp button
    // for each new gallery to trigger React initialization
    galleries.forEach(function(gallery) {
        const galleryId = gallery.id; // e.g., "reacg-root7567"
        
        // Mark as attempting initialization
        gallery.setAttribute('data-initialized', 'pending');
        
        // Execute any inline scripts that set up reacg_data
        const scripts = gallery.parentElement?.querySelectorAll('script');
        scripts?.forEach(function(script) {
            if (script.textContent.includes('reacg_data')) {
                try {
                    // Create and execute a new script with the same content
                    const newScript = document.createElement('script');
                    newScript.textContent = script.textContent;
                    document.head.appendChild(newScript);
                    document.head.removeChild(newScript);
                } catch (e) {
                    console.warn('FWD: Error executing reacg_data script', e);
                }
            }
        });
        
        // Try to use ReGallery's internal init function
        // The loadApp button triggers ie() which does createRoot().render()
        const loadAppBtn = document.getElementById('reacg-loadApp');
        if (loadAppBtn) {
            // Set the data-id to target this specific gallery
            loadAppBtn.setAttribute('data-id', galleryId);
            loadAppBtn.click();
            gallery.setAttribute('data-initialized', 'true');
        }
    });
}