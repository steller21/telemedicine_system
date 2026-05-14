(function () {
    function startPageTransition() {
        if (!document.body) {
            return;
        }

        document.body.classList.add('page-transitioning');
    }

    function isSamePageHashNavigation(link) {
        if (!link.hash) {
            return false;
        }

        var currentUrl = new URL(window.location.href);
        var targetUrl = new URL(link.href, window.location.href);
        return currentUrl.pathname === targetUrl.pathname && currentUrl.search === targetUrl.search;
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented) {
            return;
        }

        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var href = link.getAttribute('href') || '';
        if (!href || href === '#' || link.hasAttribute('download') || link.target === '_blank') {
            return;
        }

        if (isSamePageHashNavigation(link)) {
            return;
        }

        var targetUrl = new URL(link.href, window.location.href);
        if (targetUrl.origin !== window.location.origin) {
            return;
        }

        startPageTransition();
    });

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) {
            return;
        }

        var form = event.target;
        if (!form || (form.target && form.target.toLowerCase() === '_blank')) {
            return;
        }

        startPageTransition();
    });

    window.addEventListener('pageshow', function () {
        if (document.body) {
            document.body.classList.remove('page-transitioning');
        }
    });
})();
