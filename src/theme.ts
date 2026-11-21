type Theme = 'dark' | 'light';

const STORAGE_KEY = 'pcla-theme';

function apply(theme: Theme): void {
  const root = document.documentElement;
  root.dataset.theme = theme;

  const meta = document.getElementById('theme-color');
  if (meta) meta.setAttribute('content', theme === 'dark' ? '#060a12' : '#eff4fc');

  const button = document.getElementById('theme-toggle');
  if (button) {
    const use = button.querySelector<SVGUseElement>('use');
    if (use) use.setAttribute('href', theme === 'dark' ? '#i-sun' : '#i-moon');
    button.setAttribute('aria-label', theme === 'dark' ? '切换到浅色主题' : '切换到深色主题');
    button.setAttribute('title', theme === 'dark' ? '切换到浅色主题' : '切换到深色主题');
  }
}

export function initTheme(): void {
  let saved: Theme | null = null;
  try {
    const value = localStorage.getItem(STORAGE_KEY);
    if (value === 'dark' || value === 'light') saved = value;
  } catch {
    /* 存储不可用时使用默认主题 */
  }
  apply(saved ?? 'dark');
}

export function toggleTheme(): void {
  const next: Theme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
  apply(next);
  try {
    localStorage.setItem(STORAGE_KEY, next);
  } catch {
    /* ignore */
  }
}
