/* NIBWOZ hCaptcha 1.2.24: aguarda o callback oficial do SDK antes de renderizar. */
(function () {
    'use strict';
    var settings = window.nibwozHCaptchaSettings || {};
    var sdkReady = false;
    var explicitRequested = false;
    function error(root, message) {
        var target = root.querySelector('.nibwoz-hcaptcha-error');
        if (target && settings.showErrors) target.textContent = message || settings.error || 'hCaptcha error';
    }
    function clear(root) { var target = root.querySelector('.nibwoz-hcaptcha-error'); if (target) target.textContent = ''; }
    function scaleWidget(root, frame, target, size, requested) {
        var width = target.offsetWidth || (size === 'normal' ? 303 : 164);
        var height = target.offsetHeight || (size === 'normal' ? 78 : 144);
        var available = root.clientWidth || width * requested;
        var effective = Math.min(requested, available / width);
        // The smaller official widget is selected before rendering if 70% of normal cannot fit.
        effective = Math.max(0.7, effective);
        frame.style.width = Math.ceil(width * effective) + 'px';
        frame.style.height = Math.ceil(height * effective) + 'px';
        target.style.transform = 'scale(' + effective + ')';
        root.dataset.effectiveScale = String(Math.round(effective * 100));
    }
    function attach(root) {
        if (root.dataset.nibwozRendered || !window.hcaptcha) return;
        var form = root.closest('form'), frame = root.querySelector('.nibwoz-hcaptcha-frame');
        var target = root.querySelector('.nibwoz-hcaptcha-widget');
        if (!form || !frame || !target) return;
        var invisible = root.dataset.mode === 'invisible';
        var size = root.dataset.size;
        var requested = Math.max(0.7, Math.min(1.3, (Number(root.dataset.scale) || 100) / 100));
        if (!invisible && size === 'normal' && root.clientWidth && root.clientWidth < 303 * 0.7) size = 'compact';
        if (!invisible) target.style.width = (size === 'normal' ? 303 : 164) + 'px';
        var options = {
            sitekey: root.dataset.sitekey, theme: root.dataset.theme, size: size,
            callback: function (token) {
                clear(root);
                if (!invisible) return;
                // hCaptcha normally creates a textarea; keep the token in this form in all SDK modes.
                var field = form.querySelector('textarea[name="h-captcha-response"],input[name="h-captcha-response"]');
                if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'h-captcha-response'; form.appendChild(field); }
                field.value = token;
                root.dataset.nibwozSolved = '1';
                if (form.requestSubmit && root._submitter && root._submitter.isConnected) form.requestSubmit(root._submitter);
                else if (form.requestSubmit) form.requestSubmit();
                else { var button = root._submitter; if (button && button.isConnected) button.click(); else HTMLFormElement.prototype.submit.call(form); }
            },
            'expired-callback': function () { root.dataset.nibwozSolved = ''; error(root, settings.error); if (settings.resetOnExpire) window.hcaptcha.reset(root._widgetId); },
            'error-callback': function () { root.dataset.nibwozSolved = ''; error(root, settings.error); }
        };
        if (root.dataset.language !== 'auto') options.hl = root.dataset.language;
        try { root._widgetId = window.hcaptcha.render(target, options); root.dataset.nibwozRendered = '1'; }
        catch (e) { error(root, settings.error); return; }
        if (!invisible) {
            scaleWidget(root, frame, target, size, requested);
            if (window.ResizeObserver) {
                var resize = new ResizeObserver(function () { scaleWidget(root, frame, target, size, requested); });
                resize.observe(root);
                root._nibwozResize = resize;
            }
        }
        if (invisible) {
            form.addEventListener('submit', function (event) {
                if (root.dataset.nibwozSolved === '1') { root.dataset.nibwozSolved = ''; return; }
                event.preventDefault(); event.stopImmediatePropagation();
                root._submitter = event.submitter || null;
                clear(root);
                try { window.hcaptcha.execute(root._widgetId); } catch (e) { error(root, settings.error); }
            }, true);
        }
    }
    function scan(includeExplicit) { if (!sdkReady || !window.hcaptcha) return; document.querySelectorAll('.nibwoz-hcaptcha:not([data-nibwoz-rendered])').forEach(function (root) { if (includeExplicit || root.dataset.render !== 'explicit') attach(root); }); }
    window.nibwozHCaptchaRender = function () { explicitRequested = true; scan(true); };
    window.nibwozHCaptchaOnload = function () { sdkReady = true; scan(explicitRequested); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(false); }); else scan(false);
    // Watch only inserted form nodes; no iframe resizing or global style mutation.
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) if (records[i].addedNodes.length) { scan(); break; }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
    window.addEventListener('load', function () { setTimeout(function () { if (!window.hcaptcha) document.querySelectorAll('.nibwoz-hcaptcha').forEach(function (root) { error(root, settings.error); }); }, 15000); });
}());
