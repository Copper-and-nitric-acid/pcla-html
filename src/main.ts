import { initTheme, toggleTheme } from './theme';
import { prefersReducedMotion, qs, qsa, toast } from './utils';

const DOWNLOAD_URL = 'https://github.com/Copper-and-nitric/plain-craft-launcher-for-android';

type ScreenId = 'launch' | 'probe' | 'download';

const SCREEN_INFO: Record<ScreenId, { title: string; text: string; points: string[] }> = {
  launch: {
    title: '启动，不需要想太多',
    text: '当前版本、内存、Java 和最近玩过的版本都在一屏里。选好版本，按下去就行。',
    points: ['版本隔离，切换不会污染存档', '内存与 Java 运行时一眼可见', '大按钮，单手也能按到'],
  },
  probe: {
    title: '先体检，再开游戏',
    text: '设备型号、GPU、内存和推荐档位一次性列清楚，不用去网上搜“这个芯片能不能玩”。',
    points: ['EGL14 离屏读取，不需要额外权限', '按真机基线推荐堆与渲染距离', '首选版本直接给出，不用猜'],
  },
  download: {
    title: '下载队列，看得见的稳',
    text: '多个文件一起排，主源失败就换源；遇到限流自动降并发，下完再做 SHA-1 校验。',
    points: ['多文件队列，默认 2 并发', '失败自动换源，不必手动重试', '429 自适应并发 + SHA-1 校验'],
  },
};

function setupTheme(): void {
  qs<HTMLButtonElement>('#theme-toggle')?.addEventListener('click', toggleTheme);
}

function setupHeader(): void {
  const header = qs<HTMLElement>('#site-header');
  const cta = qs<HTMLElement>('#mobile-cta');

  const onScroll = (): void => {
    const y = window.scrollY;
    header?.classList.toggle('scrolled', y > 12);

    if (cta) {
      const nearBottom = window.innerHeight + y > document.documentElement.scrollHeight - 180;
      const visible = y > 520 && !nearBottom && window.innerWidth <= 960;
      cta.classList.toggle('show', visible);
    }
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  onScroll();
}

function setupMobileMenu(): void {
  const toggle = qs<HTMLButtonElement>('#menu-toggle');
  const menu = qs<HTMLElement>('#mobile-menu');
  if (!toggle || !menu) return;

  const setOpen = (open: boolean): void => {
    menu.classList.toggle('open', open);
    document.body.classList.toggle('has-menu', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? '关闭菜单' : '打开菜单');
    toggle.querySelector('use')?.setAttribute('href', open ? '#i-close' : '#i-menu');
  };

  toggle.addEventListener('click', () => setOpen(!menu.classList.contains('open')));
  qsa<HTMLAnchorElement>('a', menu).forEach((a) => a.addEventListener('click', () => setOpen(false)));

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && menu.classList.contains('open')) setOpen(false);
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 960) setOpen(false);
  });
}

function setupReveal(): void {
  const items = qsa<HTMLElement>('.reveal');
  if (!items.length) return;

  if (!('IntersectionObserver' in window)) {
    items.forEach((item) => item.classList.add('in'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('in');
        obs.unobserve(entry.target);
      });
    },
    { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
  );

  items.forEach((item) => observer.observe(item));
}

function setupCounters(): void {
  const counters = qsa<HTMLElement>('[data-count]');
  if (!counters.length) return;

  const run = (el: HTMLElement): void => {
    const target = Number(el.dataset.count);
    if (!Number.isFinite(target)) return;
    if (prefersReducedMotion()) {
      el.textContent = String(target);
      return;
    }
    const started = performance.now();
    const duration = 1500;
    const frame = (now: number): void => {
      const p = Math.min(1, (now - started) / duration);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = String(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  };

  if (!('IntersectionObserver' in window)) {
    counters.forEach(run);
    return;
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        run(entry.target as HTMLElement);
        obs.unobserve(entry.target);
      });
    },
    { threshold: 0.4 },
  );
  counters.forEach((el) => observer.observe(el));
}

function setupScreens(): void {
  const tabs = qsa<HTMLButtonElement>('[data-screen-tab]');
  const views = qsa<HTMLElement>('.phone-view');
  const info = qs<HTMLElement>('.screen-info');
  const title = qs<HTMLElement>('#screen-title');
  const text = qs<HTMLElement>('#screen-text');
  const points = qs<HTMLElement>('#screen-points');
  if (!tabs.length) return;

  const select = (id: ScreenId): void => {
    const data = SCREEN_INFO[id];
    if (!data) return;

    tabs.forEach((tab) => {
      const active = tab.dataset.screenTab === id;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
    });

    views.forEach((view) => view.classList.toggle('is-active', view.dataset.view === id));

    if (title) title.textContent = data.title;
    if (text) text.textContent = data.text;
    if (points) points.innerHTML = data.points.map((point) => `<li>${point}</li>`).join('');

    if (info) {
      info.classList.remove('swap');
      void info.offsetWidth;
      info.classList.add('swap');
    }
  };

  tabs.forEach((tab, index) => {
    tab.tabIndex = tab.classList.contains('is-active') ? 0 : -1;
    tab.addEventListener('click', () => select(tab.dataset.screenTab as ScreenId));
    tab.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowRight' && event.key !== 'ArrowUp' && event.key !== 'ArrowLeft') return;
      event.preventDefault();
      const step = event.key === 'ArrowDown' || event.key === 'ArrowRight' ? 1 : -1;
      const next = (index + step + tabs.length) % tabs.length;
      tabs[next]?.focus();
      tabs[next]?.click();
    });
  });
}

function setupSpotlight(): void {
  qsa<HTMLElement>('[data-spotlight]').forEach((card) => {
    card.addEventListener('pointermove', (event) => {
      const rect = card.getBoundingClientRect();
      card.style.setProperty('--mx', `${event.clientX - rect.left}px`);
      card.style.setProperty('--my', `${event.clientY - rect.top}px`);
    });
  });
}

function setupFaq(): void {
  const items = qsa<HTMLElement>('.faq-item');
  items.forEach((item) => {
    const button = item.querySelector<HTMLButtonElement>('.faq-q');
    if (!button) return;

    button.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');
      items.forEach((other) => {
        other.classList.remove('open');
        other.querySelector('.faq-q')?.setAttribute('aria-expanded', 'false');
      });
      if (!isOpen) {
        item.classList.add('open');
        button.setAttribute('aria-expanded', 'true');
      }
    });
  });
}

function fallbackCopy(text: string): void {
  const area = document.createElement('textarea');
  area.value = text;
  area.setAttribute('readonly', '');
  area.style.position = 'fixed';
  area.style.top = '0';
  area.style.opacity = '0';
  document.body.append(area);
  area.select();
  try {
    const ok = document.execCommand('copy');
    toast(ok ? '下载链接已复制到剪贴板' : '复制失败，请手动打开 GitHub', ok ? 'success' : 'warn');
  } catch {
    toast('复制失败，请手动打开 GitHub', 'warn');
  }
  area.remove();
}

function setupCopy(): void {
  qs<HTMLButtonElement>('#copy-link')?.addEventListener('click', async () => {
    try {
      if (!navigator.clipboard) throw new Error('unsupported');
      await navigator.clipboard.writeText(DOWNLOAD_URL);
      toast('下载链接已复制到剪贴板', 'success');
    } catch {
      fallbackCopy(DOWNLOAD_URL);
    }
  });
}

function setupSupportPrompt(): void {
  const dialog = qs<HTMLDialogElement>('#support-dialog');
  if (!dialog) return;

  const ACK_KEY = 'pcla.support.ack';
  const SNOOZE_KEY = 'pcla.support.snooze';

  const shouldPrompt = (): boolean => {
    try {
      if (localStorage.getItem(ACK_KEY) === '1') return false;
      const until = Number(localStorage.getItem(SNOOZE_KEY) ?? '0');
      return !(until > Date.now());
    } catch {
      return true;
    }
  };

  let timer = 0;
  const open = (): void => {
    if (dialog.open) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };

  qsa<HTMLAnchorElement>('a[data-download]').forEach((link) => {
    link.addEventListener('click', () => {
      if (!shouldPrompt()) return;
      window.clearTimeout(timer);
      timer = window.setTimeout(open, 1200);
    });
  });

  qsa<HTMLElement>('[data-support-action]', dialog).forEach((el) => {
    el.addEventListener('click', (event) => {
      const action = el.dataset.supportAction;
      if (action === 'buy') {
        dialog.close();
        return;
      }

      event.preventDefault();
      try {
        if (action === 'ack') localStorage.setItem(ACK_KEY, '1');
        else localStorage.setItem(SNOOZE_KEY, String(Date.now() + 24 * 60 * 60 * 1000));
      } catch {
        /* 隐私模式下忽略 */
      }

      dialog.close();
      toast(action === 'ack' ? '感谢支持正版' : '好的，下次再说', action === 'ack' ? 'success' : 'info');
    });
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
}

function setupParallax(): void {
  const hero = qs<HTMLElement>('.hero');
  const phone = qs<HTMLElement>('[data-tilt]');
  if (!hero || !phone || prefersReducedMotion()) return;
  if (!window.matchMedia('(pointer: fine)').matches || window.innerWidth < 960) return;

  let frame = 0;
  let targetX = 0;
  let targetY = 0;

  const paint = (): void => {
    frame = 0;
    phone.style.setProperty('--ry', `${(targetX * 9).toFixed(2)}deg`);
    phone.style.setProperty('--rx', `${(-targetY * 7).toFixed(2)}deg`);
  };

  hero.addEventListener('pointermove', (event) => {
    const rect = hero.getBoundingClientRect();
    targetX = (event.clientX - rect.left) / rect.width - 0.5;
    targetY = (event.clientY - rect.top) / rect.height - 0.5;
    if (!frame) frame = requestAnimationFrame(paint);
  });

  hero.addEventListener('pointerleave', () => {
    targetX = 0;
    targetY = 0;
    if (!frame) frame = requestAnimationFrame(paint);
  });
}

function setupActiveNav(): void {
  const links = qsa<HTMLAnchorElement>('.site-nav a');
  const sections = qsa<HTMLElement>('main section[id]');
  if (!links.length || !sections.length || !('IntersectionObserver' in window)) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const hash = `#${entry.target.id}`;
        links.forEach((link) => link.classList.toggle('active', link.getAttribute('href') === hash));
      });
    },
    { rootMargin: '-38% 0px -56% 0px', threshold: 0 },
  );

  sections.forEach((section) => observer.observe(section));
}

function setupScrollProgress(): void {
  const bar = qs<HTMLElement>('#scroll-progress');
  if (!bar) return;

  const update = (): void => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    const progress = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
    bar.style.transform = `scaleX(${progress.toFixed(4)})`;
  };

  window.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  update();
}


function setupSponsor(): void {
  const button = qs<HTMLAnchorElement>('#sponsor-btn');
  const pop = qs<HTMLElement>('#sponsor-pop');
  if (!button || !pop) return;

  const setOpen = (open: boolean): void => {
    pop.classList.toggle('open', open);
    pop.setAttribute('aria-hidden', String(!open));
    button.setAttribute('aria-expanded', String(open));
  };

  button.addEventListener('click', (event) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    setOpen(!pop.classList.contains('open'));
  });

  document.addEventListener('click', (event) => {
    if (!pop.classList.contains('open')) return;
    const target = event.target;
    if (target instanceof Node && (pop.contains(target) || button.contains(target))) return;
    setOpen(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });

  window.addEventListener('scroll', () => setOpen(false), { passive: true });

  pop.querySelector('a')?.addEventListener('click', () => setOpen(false));
}

function boot(): void {
  initTheme();
  setupTheme();
  setupHeader();
  setupMobileMenu();
  setupSponsor();
  setupReveal();
  setupCounters();
  setupScreens();
  setupSpotlight();
  setupFaq();
  setupCopy();
  setupSupportPrompt();
  setupParallax();
  setupActiveNav();
  setupScrollProgress();

  const year = qs<HTMLElement>('#year');
  if (year) year.textContent = String(new Date().getFullYear());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
