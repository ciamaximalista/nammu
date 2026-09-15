// Nammu admin — conmutador de modo oscuro del panel.
        document.addEventListener('DOMContentLoaded', function() {
            var toggle = document.getElementById('adminThemeToggle');
            if (!toggle) {
                return;
            }

            var storageKey = 'nammuAdminTheme';
            var root = document.documentElement;

            function applyAdminTheme(theme) {
                var isDark = theme === 'dark';
                if (isDark) {
                    root.setAttribute('data-admin-theme', 'dark');
                } else {
                    root.removeAttribute('data-admin-theme');
                }
                toggle.textContent = isDark ? 'Modo claro' : 'Modo oscuro';
                toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            }

            var currentTheme = root.getAttribute('data-admin-theme') === 'dark' ? 'dark' : 'light';
            applyAdminTheme(currentTheme);

            toggle.addEventListener('click', function() {
                var nextTheme = root.getAttribute('data-admin-theme') === 'dark' ? 'light' : 'dark';
                applyAdminTheme(nextTheme);
                try {
                    localStorage.setItem(storageKey, nextTheme);
                } catch (error) {
                    // La preferencia no es crítica si localStorage no está disponible.
                }
            });
        });
