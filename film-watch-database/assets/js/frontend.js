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
     */
    function checkUrlParameters() {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type');
        const query = urlParams.get('q');

        if (type && query) {
            const searchType = document.getElementById('fwd-search-type');
            const searchInput = document.getElementById('fwd-search-input');

            if (searchType && searchInput) {
                // Set the form values
                searchType.value = type;
                searchInput.value = decodeURIComponent(query);

                // Perform the search
                performSearch(false); // Don't update URL since we're loading from URL
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
            searchType.value = searchState.type;
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

        // Check if AJAX variables are loaded
        if (typeof fwdAjax === 'undefined') {
            resultsContainer.innerHTML = '<div class="fwd-error">Plugin not properly loaded. Please refresh the page.</div>';
            console.error('fwdAjax is not defined. Check that the script is enqueued correctly.');
            return;
        }

        const queryType = searchType.value;
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
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                let errorMsg = 'Network error';
                if (error) {
                    errorMsg += ': ' + error;
                } else if (status) {
                    errorMsg += ': ' + status;
                }
                if (xhr.status) {
                    errorMsg += ' (HTTP ' + xhr.status + ')';
                }
                if (xhr.responseText) {
                    console.error('Response text:', xhr.responseText);
                    errorMsg += '. Check browser console for details.';
                }
                resultsContainer.innerHTML = '<div class="fwd-error">' + errorMsg + '</div>';
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
        if (data.count === 0) {
            container.innerHTML = '<div class="fwd-no-results">No results found.</div>';
            return;
        }

        let html = '<div class="fwd-success">Found ' + data.count + ' result(s)</div>';
        html += '<div class="fwd-results-list">';

        if (queryType === 'actor' && data.films) {
            data.films.forEach(function(film) {
                html += buildActorResultHTML(film, film.actor);
            });
        } else if (queryType === 'brand' && data.films) {
            data.films.forEach(function(film) {
                html += buildBrandResultHTML(film, data.brand);
            });
        } else if (queryType === 'film' && data.watches) {
            data.watches.forEach(function(watch) {
                html += buildFilmResultHTML(watch);
            });
        }

        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * Build HTML for actor search result (natural language)
     */
    function buildActorResultHTML(film, actorName) {
        let html = '<div class="fwd-entry">';

        if (film.image_url) {
            html += `<figure>`;
            html += `<img src="${escapeHtml(film.image_url)}" alt="${escapeHtml(film.brand)} ${escapeHtml(film.model)}">`;
            if (film.image_caption) {
                html += `<figcaption class="wp-element-caption">${escapeHtml(film.image_caption)}</figcaption>`;
            }
            html += `</figure>`;
        }

        html += `<p><strong>${escapeHtml(actorName)}</strong> as <strong>${escapeHtml(film.character)}</strong> wears <strong class="fwd-watch">${escapeHtml(film.brand)} ${escapeHtml(film.model)}</strong> in <strong>${escapeHtml(film.title)}</strong> (${escapeHtml(film.year)}).`;

        // Don't escape narrative - it's already sanitized from database
        if (film.narrative) {
            html += ` ${film.narrative}`;
        }

        html += '</p>';

        // Add metadata footer
        if (film.confidence_level || film.source) {
            html += '<p class="fwd-metadata" style="font-size: 0.9em; color: #666; margin-top: 0.5em;">';
            if (film.confidence_level) {
                html += `<span class="fwd-confidence">Confidence score: ${escapeHtml(film.confidence_level)}</span>`;
            }
            if (film.source) {
                if (film.confidence_level) html += ' | ';
                html += `<a href="${escapeHtml(film.source)}" target="_blank" rel="noopener">Source ↗</a>`;
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

        html += `<p><strong>${escapeHtml(film.actor)}</strong> as <strong>${escapeHtml(film.character)}</strong> wears <strong class="fwd-watch">${escapeHtml(brandName)} ${escapeHtml(film.model)}</strong> in <strong>${escapeHtml(film.title)}</strong> (${escapeHtml(film.year)}).`;

        // Don't escape narrative - it's already sanitized from database
        if (film.narrative) {
            html += ` ${film.narrative}`;
        }

        html += '</p>';

        // Add metadata footer
        if (film.confidence_level || film.source) {
            html += '<p class="fwd-metadata" style="font-size: 0.9em; color: #666; margin-top: 0.5em;">';
            if (film.confidence_level) {
                html += `<span class="fwd-confidence">Confidence score: ${escapeHtml(film.confidence_level)}</span>`;
            }
            if (film.source) {
                if (film.confidence_level) html += ' | ';
                html += `<a href="${escapeHtml(film.source)}" target="_blank" rel="noopener">Source ↗</a>`;
            }
            html += '</p>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Build HTML for film search result (natural language)
     */
    function buildFilmResultHTML(watch) {
        let html = '<div class="fwd-entry">';

        if (watch.image_url) {
            html += `<figure>`;
            html += `<img src="${escapeHtml(watch.image_url)}" alt="${escapeHtml(watch.brand)} ${escapeHtml(watch.model)}">`;
            if (watch.image_caption) {
                html += `<figcaption class="wp-element-caption">${escapeHtml(watch.image_caption)}</figcaption>`;
            }
            html += `</figure>`;
        }

        html += `<p><strong>${escapeHtml(watch.actor)}</strong> as <strong>${escapeHtml(watch.character)}</strong> wears <strong class="fwd-watch">${escapeHtml(watch.brand)} ${escapeHtml(watch.model)}</strong> in <strong>${escapeHtml(watch.title)}</strong> (${escapeHtml(watch.year)}).`;

        // Don't escape narrative - it's already sanitized from database
        if (watch.narrative) {
            html += ` ${watch.narrative}`;
        }

        html += '</p>';

        // Add metadata footer
        if (watch.confidence_level || watch.source) {
            html += '<p class="fwd-metadata" style="font-size: 0.9em; color: #666; margin-top: 0.5em;">';
            if (watch.confidence_level) {
                html += `<span class="fwd-confidence">Confidence score: ${escapeHtml(watch.confidence_level)}</span>`;
            }
            if (watch.source) {
                if (watch.confidence_level) html += ' | ';
                html += `<a href="${escapeHtml(watch.source)}" target="_blank" rel="noopener">Source ↗</a>`;
            }
            html += '</p>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
                title: 'Select Watch Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            // When an image is selected, run a callback
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                imageUrlInput.value = attachment.url;
            });

            // Open the uploader dialog
            mediaUploader.open();
        });
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
                                    <tr><td><strong>Confidence score: </strong></td><td>${existing.confidence_level ? escapeHtml(existing.confidence_level) : 'None'}</td></tr>
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
                                    <tr><td><strong>Confidence score: </strong></td><td>${newData.confidence_level ? escapeHtml(newData.confidence_level) : 'None'}</td></tr>
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
        const confidence = document.getElementById('fwd-confidence');
        const sourceUrl = document.getElementById('fwd-source-url');
        const resultDiv = document.getElementById('fwd-add-result');
        const addBtn = document.getElementById('fwd-add-btn');

        if (!entryText || !narrative || !resultDiv) return;

        const entryValue = entryText.value.trim();
        const narrativeValue = narrative.value.trim();
        const imageUrlValue = imageUrl ? imageUrl.value.trim() : '';
        const confidenceValue = confidence ? confidence.value.trim() : '';
        const sourceUrlValue = sourceUrl ? sourceUrl.value.trim() : '';

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
                confidence_level: confidenceValue,
                source_url: sourceUrlValue,
                force_overwrite: forceOverwrite ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    showResult(resultDiv, 'fwd-success', '✓ ' + response.data.message);
                    entryText.value = '';
                    narrative.value = '';
                    if (imageUrl) imageUrl.value = '';
                    if (confidence) confidence.value = '';
                    if (sourceUrl) sourceUrl.value = '';
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
                    const errorMsg = response.data.error || 'Unknown error occurred';
                    showResult(resultDiv, 'fwd-error', formatErrorMessage(errorMsg));
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
            quickAddBtn.addEventListener('click', function() {
                addQuickEntry(false);
            });
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
                    const errorMsg = response.data.error || 'Unknown error occurred';
                    showResult(resultDiv, 'fwd-error', formatErrorMessage(errorMsg));
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
     * Format error message with helpful context
     * @param {string} error - The error message
     * @return {string} - Formatted HTML error message
     */
    function formatErrorMessage(error) {
        let message = '';
        let helpText = '';

        // Parse validation errors
        if (error.includes('Validation failed:')) {
            const errors = error.replace('Validation failed: ', '').split('; ');
            message = '<strong>Please fix the following issues:</strong><ul>';
            errors.forEach(function(err) {
                message += '<li>' + FWD_Utils.escapeHtml(err) + '</li>';
            });
            message += '</ul>';
        }
        // Handle rate limit errors
        else if (error.includes('rate limit')) {
            message = '<strong>Too many requests</strong><br>' + FWD_Utils.escapeHtml(error);
            helpText = 'Please wait a moment before trying again.';
        }
        // Handle API key errors
        else if (error.includes('API key')) {
            message = '<strong>Configuration Issue</strong><br>' + FWD_Utils.escapeHtml(error);
            helpText = 'Please contact your site administrator.';
        }
        // Handle parsing errors
        else if (error.includes('Could not parse')) {
            message = '<strong>Unable to understand entry</strong><br>' + FWD_Utils.escapeHtml(error);
            helpText = 'Try using the Quick Entry format: Actor|Character|Brand|Model|Title|Year';
        }
        // Generic error
        else {
            message = FWD_Utils.escapeHtml(error);
        }

        if (helpText) {
            message += '<div class="fwd-help-text">' + helpText + '</div>';
        }

        return message;
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initSearch();
        initAddForm();
        initTabs();
    });

})(jQuery);
