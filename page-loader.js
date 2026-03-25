// Page Loader — shows items_loading.gif overlay for 1.25s on page entry
// Skips when URL contains ?export= (file download/export pages) or on chat pages
(function () {
    var path = window.location.pathname;
    if (window.location.search.indexOf('export=') !== -1) return;
    if (path.indexOf('messages.php') !== -1 || path.indexOf('admin_messages.php') !== -1) return;

    // Immediately hide body to prevent flash of page content
    var hideStyle = document.createElement('style');
    hideStyle.textContent = 'body { opacity: 0 !important; transition: none !important; }';
    document.head.appendChild(hideStyle);

    var overlay = document.createElement('div');
    overlay.id = 'pageLoaderOverlay';
    overlay.innerHTML =
        '<img src="animations/items_loading.gif" alt="Loading...">' +
        '<p class="pl-text">Loading...</p>';

    function inject() {
        // Remove body-hide style, show overlay on top
        if (hideStyle.parentNode) hideStyle.parentNode.removeChild(hideStyle);
        document.body.style.opacity = '';
        document.body.appendChild(overlay);
        setTimeout(function () {
            overlay.style.opacity = '0';
            setTimeout(function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }, 400);
        }, 1250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inject);
    } else {
        inject();
    }
})();
