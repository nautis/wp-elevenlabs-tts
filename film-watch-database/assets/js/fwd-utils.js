/**
 * Film Watch Database - Shared Utilities
 * Common functions used across admin and frontend JavaScript
 */

var FWD_Utils = (function() {
    'use strict';

    return {
        /**
         * Escape HTML to prevent XSS attacks
         *
         * @param {string} text Text to escape
         * @returns {string} Escaped HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
})();
