// ── Dark / light toggle ──────────────────────────────────────────────────
const btn  = document.getElementById('themeToggle');
const root = document.documentElement;

function applyTheme(isLight) {
  root.classList.toggle('light', isLight);
}

// Initialize: respect prefers-color-scheme if no stored preference
const stored = localStorage.getItem('theme');
if (stored) {
  applyTheme(stored === 'light');
} else {
  applyTheme(window.matchMedia('(prefers-color-scheme: light)').matches);
}

if (btn) {
  btn.addEventListener('click', () => {
    const isLight = !root.classList.contains('light');
    applyTheme(isLight);
    localStorage.setItem('theme', isLight ? 'light' : 'dark');
  });
}
