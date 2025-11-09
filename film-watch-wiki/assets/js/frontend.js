/**
 * Film Watch Wiki - Frontend JavaScript
 * Handles interactive features on actor pages
 */

(function() {
    'use strict';

    /**
     * Handle "Read More" / "Read Less" toggle for actor biographies
     * Client-side word truncation approach
     */
    function initBioToggle() {
        const containers = document.querySelectorAll('.bio-container');

        containers.forEach(function(container) {
            const toggleBtn = document.querySelector('.bio-toggle-btn[data-target="' + container.id + '"]');
            if (!toggleBtn) return;

            const limit = parseInt(container.getAttribute('data-word-limit'), 10) || 200;

            // Get the full text content (strip HTML but preserve structure)
            const fullText = container.textContent.trim().replace(/\s+/g, ' ');
            const words = fullText.split(' ');

            // If text is short enough, no need for read more
            if (words.length <= limit) {
                return;
            }

            // Create preview text (first N words)
            const previewText = words.slice(0, limit).join(' ') + '...';

            // Store original HTML
            const originalHTML = container.innerHTML;

            // Create preview element
            const previewEl = document.createElement('div');
            previewEl.className = 'bio-preview';
            previewEl.textContent = previewText;

            // Create full element
            const fullEl = document.createElement('div');
            fullEl.className = 'bio-full hidden';
            fullEl.innerHTML = originalHTML;

            // Replace container content
            container.innerHTML = '';
            container.appendChild(previewEl);
            container.appendChild(fullEl);

            // Show the toggle button
            toggleBtn.classList.remove('hidden');

            // Handle toggle click
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';

                if (isExpanded) {
                    // Collapse: show preview, hide full
                    fullEl.classList.add('hidden');
                    previewEl.classList.remove('hidden');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    toggleBtn.textContent = 'Read More';

                    // Scroll to biography section
                    const biographySection = container.closest('.actor-biography');
                    if (biographySection) {
                        biographySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } else {
                    // Expand: hide preview, show full
                    previewEl.classList.add('hidden');
                    fullEl.classList.remove('hidden');
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    toggleBtn.textContent = 'Read Less';
                }
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBioToggle);
    } else {
        initBioToggle();
    }
})();
