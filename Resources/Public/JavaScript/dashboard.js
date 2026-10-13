/**
 * Content Intelligence Dashboard
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize score badge tooltips if available
        const badges = document.querySelectorAll('.badge');
        badges.forEach(function(badge) {
            badge.setAttribute('title', badge.textContent.trim());
        });
    });
})();
