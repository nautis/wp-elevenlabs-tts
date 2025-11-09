/**
 * Film Watch Wiki - Frontend JavaScript
 * Handles interactive features on actor pages
 */

(function() {
    'use strict';

    /**
     * Handle "Read More" / "Read Less" toggle for actor biographies
     */
    function initBioToggle() {
        // Find all read more/less links
        const readMoreLinks = document.querySelectorAll('.read-more-toggle');
        const readLessLinks = document.querySelectorAll('.read-less-toggle');

        readMoreLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const bioContent = this.closest('.bio-content');
                const shortBio = bioContent.querySelector('.bio-short');
                const fullBio = bioContent.querySelector('.bio-full');

                if (shortBio && fullBio) {
                    shortBio.style.display = 'none';
                    fullBio.style.display = 'block';
                }
            });
        });

        readLessLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const bioContent = this.closest('.bio-content');
                const shortBio = bioContent.querySelector('.bio-short');
                const fullBio = bioContent.querySelector('.bio-full');

                if (shortBio && fullBio) {
                    shortBio.style.display = 'block';
                    fullBio.style.display = 'none';

                    // Scroll to biography section
                    const biographySection = this.closest('.actor-biography');
                    if (biographySection) {
                        biographySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
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
