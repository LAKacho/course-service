/* Progressive HTML navigation. Server forms, CSRF and redirects remain authoritative. */
(() => {
  if (!window.fetch || !window.FormData || !window.DOMParser) return;
  let busy = false, pendingPop = null;
  const sameOrigin = url => url.origin === location.origin && /^https?:$/.test(url.protocol);
  function notice(message, error = false) {
    let node = document.getElementById('ajax-notice');
    if (!node) { node = document.createElement('div'); node.id = 'ajax-notice'; document.body.append(node); }
    node.className = 'ajax-notice' + (error ? ' ajax-error' : '');
    node.setAttribute('role', error ? 'alert' : 'status'); node.textContent = message;
  }
  function rememberScroll() { history.replaceState({...history.state, ajaxScroll: [scrollX, scrollY]}, '', location.href); }
  async function render(html, url, historyMode, restore, invalid) {
    const next = new DOMParser().parseFromString(html, 'text/html');
    if (!next.body.hasAttribute('data-ajax-app') || !next.querySelector('main')) throw new Error('unrecognized-page');
    // Only trusted same-origin stylesheets; parsed scripts are never executed.
    const styles = [...next.querySelectorAll('link[rel="stylesheet"]')];
    const wanted = new Set(styles.map(link => new URL(link.getAttribute('href'), url).href));
    await Promise.all(styles.map(link => {
      const href = new URL(link.getAttribute('href'), url);
      if (!sameOrigin(href)) return Promise.resolve();
      if ([...document.querySelectorAll('link[rel="stylesheet"]')].some(old => old.href === href.href)) return Promise.resolve();
      return new Promise((resolve, reject) => {
        const node = document.createElement('link'); node.rel = 'stylesheet'; node.href = href.href;
        const timer = setTimeout(() => { node.remove(); reject(new Error('stylesheet-unavailable')); }, 10000);
        node.onload = () => { clearTimeout(timer); resolve(); };
        node.onerror = () => { clearTimeout(timer); node.remove(); reject(new Error('stylesheet-unavailable')); };
        document.head.append(node);
      });
    }));
    for (const script of next.querySelectorAll('script')) script.remove();
    for (const attribute of [...document.documentElement.attributes]) document.documentElement.removeAttribute(attribute.name);
    for (const attribute of next.documentElement.attributes) document.documentElement.setAttribute(attribute.name, attribute.value);
    document.title = next.title;
    for (const old of document.querySelectorAll('link[rel="stylesheet"]')) if (!wanted.has(old.href)) old.remove();
    document.querySelector('meta[name="color-scheme"]')?.remove();
    const colorScheme = next.querySelector('meta[name="color-scheme"]');
    if (colorScheme) document.head.append(document.importNode(colorScheme, true));
    document.body.replaceWith(document.importNode(next.body, true));
    if (historyMode === 'push') { rememberScroll(); history.pushState({}, '', url); }
    if (historyMode === 'replace') history.replaceState(history.state, '', url);
    document.dispatchEvent(new CustomEvent('app:page-load'));
    const target = invalid ? document.querySelector('[aria-invalid="true"], .is-invalid, form') : document.querySelector('main');
    if (target) {
      if (!target.matches('input, select, textarea, button, a[href]')) target.setAttribute('tabindex', '-1');
      target.focus({preventScroll:true});
    }
    if (restore) scrollTo(...restore);
    else if (new URL(url).hash && document.getElementById(decodeURIComponent(new URL(url).hash.slice(1)))) {
      document.getElementById(decodeURIComponent(new URL(url).hash.slice(1))).scrollIntoView();
    } else scrollTo(0, 0);
    if (invalid) notice('Проверьте поля формы: изменения не сохранены.', true);
  }
  async function visit(url, {method = 'GET', body, historyMode = 'push', restore} = {}) {
    if (busy) return;
    busy = true;
    const buttons = [...document.querySelectorAll('button, input[type="submit"]')].filter(node => !node.disabled);
    buttons.forEach(node => { node.disabled = true; });
    document.documentElement.setAttribute('data-ajax-busy', '');
    document.querySelector('main')?.setAttribute('aria-busy', 'true');
    notice(method === 'GET' ? 'Загрузка…' : 'Сохранение…');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(url, {method, body, credentials:'same-origin', cache:'no-store',
        signal:controller.signal, headers:{'Accept':'text/html', 'X-Requested-With':'XMLHttpRequest'}});
      if (!sameOrigin(new URL(response.url))) throw new Error('unexpected-origin');
      if (!response.ok && response.status !== 422) {
        const messages = {403:'Форма устарела или действие запрещено. Обновите страницу перед повтором.',
          404:'Страница или объект недоступны.', 409:'Данные изменились или действие уже выполнено. Проверьте актуальное состояние.'};
        notice(messages[response.status] || 'Сервер не смог обработать запрос. Введённые данные сохранены в форме.', true);
        return;
      }
      if (!response.headers.get('Content-Type')?.includes('text/html')) throw new Error('unexpected-content');
      const invalid = response.status === 422;
      const destination = invalid ? location.href : response.url;
      await render(await response.text(), destination, invalid ? null : historyMode, restore, invalid);
    } catch {
      notice(method === 'GET' ? 'Не удалось загрузить страницу. Проверьте соединение и повторите переход.'
        : 'Не удалось получить ответ. Данные могли сохраниться. Проверьте состояние перед повторной отправкой; введённый код и поля остаются в форме.', true);
    } finally {
      clearTimeout(timeout);
      busy = false; buttons.forEach(node => { node.disabled = false; });
      document.documentElement.removeAttribute('data-ajax-busy');
      document.querySelector('main')?.removeAttribute('aria-busy');
      if (pendingPop) { const pending = pendingPop; pendingPop = null; visit(pending.url, {historyMode:'replace', restore:pending.scroll}); }
    }
  }
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-ajax') || form.getAttribute('target') && form.getAttribute('target') !== '_self') return;
    const submitter = event.submitter;
    // Named controls (notably coding buttons name="action") can shadow form.action/method.
    const url = new URL(submitter?.getAttribute('formaction') || form.getAttribute('action') || location.href, location.href);
    const method = (submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'GET').toUpperCase();
    if (!sameOrigin(url) || !['GET','POST'].includes(method)) return;
    event.preventDefault(); if (busy) return;
    const body = new FormData(form);
    if (submitter?.name) body.append(submitter.name, submitter.value);
    if (method === 'GET') { url.search = new URLSearchParams(body).toString(); visit(url.href); }
    else visit(url.href, {method, body});
  });
  document.addEventListener('click', event => {
    const link = event.target.closest?.('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
      || link.hasAttribute('download') || link.hasAttribute('data-no-ajax') || link.target && link.target !== '_self') return;
    const url = new URL(link.href, location.href);
    if (!sameOrigin(url) || url.pathname.startsWith('/api/') || /\.(?:zip|pdf|png|jpg|svg|json)$/i.test(url.pathname)
      || url.pathname === location.pathname && url.search === location.search && url.hash) return;
    event.preventDefault(); if (!busy) visit(url.href);
  });
  addEventListener('popstate', event => {
    const pending = {url:location.href, scroll:event.state?.ajaxScroll || [0,0]};
    if (busy) pendingPop = pending; else visit(pending.url, {historyMode:'replace', restore:pending.scroll});
  });
})();
