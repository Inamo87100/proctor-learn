(function () {
  if (typeof window.TLPC === 'undefined') return;

  const cfg = window.TLPC;

  function el(tag, attrs = {}, children = []) {
    const n = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
      if (k === 'class') n.className = v;
      else if (k === 'html') n.innerHTML = v;
      else n.setAttribute(k, v);
    });
    children.forEach((c) => n.appendChild(c));
    return n;
  }

  async function api(path, body) {
    const res = await fetch(cfg.restUrl + path, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
      },
      body: JSON.stringify(body),
      credentials: 'same-origin',
    });
    return await res.json();
  }

  function showOverlay(title, message, actions = []) {
    let overlay = document.querySelector('.tlpc-overlay');
    if (!overlay) {
      overlay = el('div', { class: 'tlpc-overlay' });
      document.body.appendChild(overlay);
    }

    overlay.innerHTML = '';

    const card = el('div', { class: 'tlpc-card' }, [
      el('h2', { html: title }),
      el('div', { html: message }),
    ]);

    if (actions.length) {
      const act = el('div', { class: 'tlpc-actions' });
      actions.forEach((a) => act.appendChild(a));
      card.appendChild(act);
    }

    overlay.appendChild(card);
    overlay.classList.add('is-visible');
  }

  function hideOverlay() {
    const overlay = document.querySelector('.tlpc-overlay');
    if (overlay) overlay.classList.remove('is-visible');
  }

  async function requireFullscreen() {
    if (document.fullscreenElement) return true;
    try {
      await document.documentElement.requestFullscreen();
      return !!document.fullscreenElement;
    } catch (e) {
      return false;
    }
  }

  async function runPreflightIfNeeded() {
    if (!cfg.preflightRequired || cfg.preflightPassed) return;

    return new Promise((resolve) => {
      const btn = el('button', { type: 'button' });
      btn.textContent = 'Inizia (attiva fullscreen)';

      btn.addEventListener('click', async () => {
        const ok = await requireFullscreen();
        if (!ok) {
          showOverlay('Pre-flight non superato', 'Non riesco ad attivare la modalità fullscreen. Abilitala per continuare.');
          return;
        }

        await api('/preflight-pass', { course_id: cfg.courseId });
        cfg.preflightPassed = true;
        hideOverlay();
        resolve();
      });

      showOverlay(
        'Controllo pre-esame',
        '<p>Prima di iniziare il primo quiz di questo corso devi completare un controllo rapido.</p><ul><li>Fullscreen obbligatorio</li><li>Non cambiare tab durante il quiz</li></ul>',
        [btn]
      );
    });
  }

  let switchCount = 0;
  let invalidated = false;

  async function report(event) {
    if (invalidated) return;

    switchCount += 1;

    const data = await api('/event', {
      course_id: cfg.courseId,
      quiz_id: cfg.quizId,
      event,
      count: switchCount,
    });

    const max = typeof data.max === 'number' ? data.max : cfg.maxTabSwitches;

    if (data.invalidate || switchCount > max) {
      invalidated = true;
      showOverlay(
        'Tentativo invalidato',
        '<p>Hai cambiato scheda/finestra troppe volte. Il quiz verrà consegnato automaticamente con 0 risposte.</p>'
      );

      // TODO: qui potremo chiamare un endpoint "force-submit" quando implementato lato server.
      // Per ora blocchiamo la UI.
      setTimeout(() => {
        window.location.reload();
      }, 2500);
    }
  }

  function onVisibilityChange() {
    if (document.hidden) report('visibilitychange_hidden');
  }

  function onBlur() {
    report('window_blur');
  }

  document.addEventListener('DOMContentLoaded', async () => {
    await runPreflightIfNeeded();

    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('blur', onBlur);
  });
})();
