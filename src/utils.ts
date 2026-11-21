export function qs<T extends Element = HTMLElement>(selector: string, root: ParentNode = document): T | null {
  return root.querySelector<T>(selector);
}

export function qsa<T extends Element = HTMLElement>(selector: string, root: ParentNode = document): T[] {
  return Array.from(root.querySelectorAll<T>(selector));
}

export type ToastKind = 'info' | 'success' | 'warn' | 'error';

const TOAST_ICONS: Record<ToastKind, string> = {
  info: 'info',
  success: 'check',
  warn: 'bolt',
  error: 'close',
};

export function toast(message: string, kind: ToastKind = 'info', duration = 3000): void {
  const root = document.getElementById('toasts');
  if (!root) return;

  const el = document.createElement('div');
  el.className = `toast toast-${kind}`;

  const ico = document.createElement('span');
  ico.className = 'toast-ico';
  ico.innerHTML = `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-${TOAST_ICONS[kind]}"></use></svg>`;

  const text = document.createElement('span');
  text.className = 'toast-text';
  text.textContent = message;

  el.append(ico, text);
  root.append(el);
  requestAnimationFrame(() => el.classList.add('show'));

  window.setTimeout(() => {
    el.classList.remove('show');
    window.setTimeout(() => el.remove(), 260);
  }, duration);
}

export function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
