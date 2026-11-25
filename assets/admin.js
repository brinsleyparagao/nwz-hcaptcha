(function () {
    'use strict';
    var root = document.querySelector('.nwz-hc-admin');
    if (!root) { return; }
    // Links da barra lateral do NIBWOZ UI; a seção vem do parâmetro view de cada link.
    var links = Array.prototype.slice.call(root.querySelectorAll('.nwz-nav-link'));
    function viewOf(link) { return new URL(link.getAttribute('href'), window.location.href).searchParams.get('view') || 'overview'; }
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-hc-view-panel]'));
    var referer = root.querySelector('#nwz-hc-save-form input[name="_wp_http_referer"]');
    var reveal = root.querySelector('.nwz-hc-reveal-secret');
    var secret = root.querySelector('#nwz-hc-secret-key');
    var secretStatus = root.querySelector('#nwz-hc-secret-status');
    var loadedSecret = false;
    var revealRequest = 0;
    var labels = window.nibwozHCaptchaAdmin || {};
    function text(key, fallback) { return typeof labels[key] === 'string' ? labels[key] : fallback; }
    // Botão do kit com texto visível: ícone, texto e aria-pressed mudam juntos.
    function setRevealed(on) {
        secret.type = on ? 'text' : 'password';
        reveal.setAttribute('aria-pressed', on ? 'true' : 'false');
        reveal.querySelector('.dashicons').className = 'dashicons ' + (on ? 'dashicons-hidden' : 'dashicons-visibility');
        reveal.querySelector('.nwz-hc-reveal-label').textContent = on ? text('hide', 'Hide secret key') : text('show', 'Show secret key');
    }
    function hideSecret() {
        if (!secret || !reveal) { return; }
        revealRequest++;
        if (loadedSecret) { secret.value = ''; loadedSecret = false; }
        setRevealed(false);
    }
    if (reveal && secret && secretStatus) {
        reveal.addEventListener('click', function () {
            if (secret.type === 'text') { hideSecret(); secretStatus.textContent = text('hidden', 'Key hidden.'); return; }
            if (secret.value !== '') {
                setRevealed(true);
                secretStatus.textContent = text('visible', 'Key visible.');
                return;
            }
            reveal.disabled = true;
            var request = ++revealRequest;
            secretStatus.textContent = text('loading', 'Loading saved key…');
            var params = new URLSearchParams({ action: 'nibwoz_hcaptcha_reveal_secret', nonce: reveal.getAttribute('data-hc-secret-nonce') });
            fetch(reveal.getAttribute('data-hc-secret-url'), { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
                .then(function (response) { if (!response.ok) { throw new Error('HTTP ' + response.status); } return response.json(); })
                .then(function (result) {
                    if (request !== revealRequest || secret.value !== '' || root.querySelector('[data-hc-view-panel="keys"]').hidden) { return; }
                    if (!result.success || !result.data || !result.data.secret) { throw new Error('Secret key not configured.'); }
                    secret.value = result.data.secret;
                    loadedSecret = true;
                    setRevealed(true);
                    secretStatus.textContent = text('loaded', 'Saved key visible. Click again to hide.');
                })
                .catch(function () { if (request === revealRequest) { secretStatus.textContent = text('failed', 'Could not show the saved key. Reload the page and try again.'); } })
                .finally(function () { reveal.disabled = false; });
        });
        document.addEventListener('visibilitychange', function () { if (document.hidden && loadedSecret) { hideSecret(); secretStatus.textContent = text('hidden', 'Key hidden.'); } });
    }
    var known = {};
    links.forEach(function (link) { known[viewOf(link)] = true; });
    function select(view, updateHistory) {
        if (view !== 'keys') { hideSecret(); }
        if (view === 'behavior' || view === 'overrides') { view = 'appearance'; }
        if (view === 'exceptions' || view === 'security') { view = 'network'; }
        if (view === 'reset') { view = 'about'; }
        if (!Object.prototype.hasOwnProperty.call(known, view)) { view = 'overview'; }
        links.forEach(function (link) {
            var current = viewOf(link) === view;
            link.classList.toggle('is-current', current);
            if (current) { link.setAttribute('aria-current', 'page'); }
            else { link.removeAttribute('aria-current'); }
        });
        panels.forEach(function (panel) { panel.hidden = panel.getAttribute('data-hc-view-panel') !== view; });
        if (updateHistory && window.history && window.history.pushState) {
            var url = new URL(window.location.href);
            url.searchParams.set('view', view);
            window.history.pushState({ hcView: view }, '', url.toString());
        }
    }
    // O retorno após salvar segue a seção aberta; o campo só muda no envio para não marcar alterações não salvas.
    if (referer && referer.form) {
        referer.form.addEventListener('submit', function () {
            var currentUrl = new URL(window.location.href);
            referer.value = currentUrl.pathname + currentUrl.search;
        });
    }
    links.forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
            event.preventDefault();
            select(viewOf(link), true);
        });
    });
    window.addEventListener('popstate', function () {
        select(new URL(window.location.href).searchParams.get('view') || 'overview', false);
    });
}());
