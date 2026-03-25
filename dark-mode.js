// Campus Find — Dark Mode (separate keys for admin vs user)
(function () {
    // Admin pages have 'admin_' in the URL filename
    var isAdmin = /\/admin_/.test(window.location.pathname);
    var KEY = isAdmin ? 'darkMode_admin' : 'darkMode_user';

    function applyDark(on) {
        if (on) {
            document.documentElement.classList.add('dark-mode');
        } else {
            document.documentElement.classList.remove('dark-mode');
        }
        updateToggle(on);
    }

    function updateToggle(on) {
        var input = document.getElementById('darkModeBtn');
        if (!input) return;
        input.checked = on;
    }

    // Apply immediately before paint (no flash)
    applyDark(localStorage.getItem(KEY) === 'true');

    // Keep toggleDarkMode for any legacy callers
    window.toggleDarkMode = function () {
        var isDark = document.documentElement.classList.contains('dark-mode');
        localStorage.setItem(KEY, !isDark);
        applyDark(!isDark);
    };

    document.addEventListener('DOMContentLoaded', function () {
        var isDark = localStorage.getItem(KEY) === 'true';
        updateToggle(isDark);

        var input = document.getElementById('darkModeBtn');
        if (input) {
            input.addEventListener('change', function () {
                var on = this.checked;
                localStorage.setItem(KEY, on);
                applyDark(on);
            });
        }
    });
})();
