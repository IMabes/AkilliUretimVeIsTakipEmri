(function () {
    const root = document.documentElement;
    const storageKey = 'uretim-takip-theme';
    const saved = localStorage.getItem(storageKey);
    if (saved === 'dark' || saved === 'light') {
        root.setAttribute('data-bs-theme', saved);
    }
    const btn = document.getElementById('themeToggle');
    if (btn) {
        const updateLabel = function () {
            const t = root.getAttribute('data-bs-theme') || 'light';
            btn.textContent = t === 'dark' ? 'Gündüz' : 'Gece';
        };
        updateLabel();
        btn.addEventListener('click', function () {
            const next = (root.getAttribute('data-bs-theme') || 'light') === 'light' ? 'dark' : 'light';
            root.setAttribute('data-bs-theme', next);
            localStorage.setItem(storageKey, next);
            updateLabel();
        });
    }
})();
