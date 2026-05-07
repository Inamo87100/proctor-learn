(function () {
  if (typeof window.TLPC === 'undefined') return;

  const cfg = window.TLPC;
  const i18n = cfg.i18n || {};

  function t(key, fallback) {
    return typeof i18n[key] === 'string' && i18n[key] ? i18n[key] : fallback;
  }

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
    const data = await res.json().catch((error) => {
      console.error('TLPC invalid JSON response', error);
      return { error: 'invalid_json_response' };
    });
    if (!res.ok) {
      const error = new Error(data.error || 'request_failed');
      error.response = data;
      throw error;
    }
    return data;
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
      btn.textContent = t('preflightStartButton', 'Inizia (attiva fullscreen)');

      btn.addEventListener('click', async () => {
        const ok = await requireFullscreen();
        if (!ok) {
          showOverlay(
            t('preflightFailedTitle', 'Pre-flight non superato'),
            t('preflightFailedMessage', 'Non riesco ad attivare la modalità fullscreen. Abilitala per continuare.')
          );
          return;
        }

        cfg.preflightPassed = true;
        hideOverlay();
        resolve();
      });

      showOverlay(
        t('preflightTitle', 'Controllo pre-esame'),
        t(
          'preflightMessage',
          '<p>Prima di iniziare il primo quiz di questo corso devi completare un controllo rapido.</p><ul><li>Fullscreen obbligatorio</li><li>Non cambiare tab durante il quiz</li></ul>'
        ),
        [btn]
      );
    });
  }

  let switchCount = 0;
  let invalidated = false;

  async function invalidateQuiz(reason) {
    invalidated = true;

    showOverlay(
      t('invalidationTitle', 'Tentativo invalidato'),
      t('invalidationSubmittingMessage', '<p>Hai cambiato scheda/finestra troppe volte. Sto consegnando il quiz con 0 risposte.</p>')
    );

    try {
      await api('/force-submit', {
        course_id: cfg.courseId,
        quiz_id: cfg.quizId,
        reason,
      });

      showOverlay(
        t('invalidationTitle', 'Tentativo invalidato'),
        t('invalidationSuccessMessage', '<p>Il quiz è stato invalidato e consegnato con 0 risposte.</p>')
      );
    } catch (error) {
      showOverlay(
        t('invalidationTitle', 'Tentativo invalidato'),
        t('invalidationErrorMessage', '<p>Non sono riuscito a completare il force submit. Il quiz resta comunque bloccato e la pagina verrà ricaricata.</p>')
      );
    }

    setTimeout(() => {
      window.location.reload();
    }, 2500);
  }

  async function report(event) {
    if (invalidated) return;

    switchCount += 1;

    let data = {};
    try {
      data = await api('/event', {
        course_id: cfg.courseId,
        quiz_id: cfg.quizId,
        event,
        count: switchCount,
      });
    } catch (error) {
      data = {};
    }

    const max = typeof data.max === 'number' ? data.max : cfg.maxTabSwitches;

    if (data.invalidate || switchCount > max) {
      await invalidateQuiz(`tab_switch:${event}:${switchCount}`);
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
