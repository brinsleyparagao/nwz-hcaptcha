/*!
 * NIBWOZ UI 1.2.0 — comportamentos comuns da interface dos plugins NIBWOZ.
 * Sem dependências. Só age dentro de .nwz-ui e por atributos data-nwz-*.
 */
(function () {
	'use strict';

	var root = document.querySelector('.nwz-ui');
	if (!root || root.hasAttribute('data-nwz-ready')) {
		return;
	}
	root.setAttribute('data-nwz-ready', '');

	/* Confirmação de ações sensíveis: data-nwz-confirm="Mensagem". */
	root.addEventListener('click', function (event) {
		var trigger = event.target.closest('[data-nwz-confirm]');
		if (trigger && !window.confirm(trigger.getAttribute('data-nwz-confirm'))) {
			event.preventDefault();
		}
	});

	/* Alterações não salvas: formulários com data-nwz-settings e um marcador data-nwz-dirty. */
	var forms = root.querySelectorAll('form[data-nwz-settings]');
	var marker = root.querySelector('[data-nwz-dirty]');
	var dirty = new Set();

	function serialize(form) {
		return new URLSearchParams(new FormData(form)).toString();
	}

	forms.forEach(function (form) {
		var initial = serialize(form);
		var update = function () {
			if (serialize(form) !== initial) {
				dirty.add(form);
			} else {
				dirty.delete(form);
			}
			if (marker) {
				marker.hidden = dirty.size === 0;
			}
		};
		form.addEventListener('input', update);
		form.addEventListener('change', update);
		form.addEventListener('submit', function () {
			dirty.delete(form);
		});
	});

	window.addEventListener('beforeunload', function (event) {
		if (dirty.size > 0) {
			event.preventDefault();
			event.returnValue = root.getAttribute('data-nwz-leave') || '';
		}
	});

	/* Estado ocupado em botões: data-nwz-busy-on-submit marca o botão ao enviar o formulário. */
	root.addEventListener('submit', function (event) {
		var button = event.submitter;
		if (button && button.hasAttribute('data-nwz-busy-on-submit') && !event.defaultPrevented) {
			button.classList.add('is-busy');
			button.setAttribute('aria-busy', 'true');
		}
	});

	/* Voltar pelo histórico (bfcache) não deixa botão preso em ocupado. */
	window.addEventListener('pageshow', function () {
		root.querySelectorAll('.is-busy[data-nwz-busy-on-submit]').forEach(function (button) {
			button.classList.remove('is-busy');
			button.removeAttribute('aria-busy');
		});
	});

	/*
	 * Aviso temporário (toast): pílula no centro da tela que some com fade.
	 * Marcação do servidor: [data-nwz-toast] (Ui::toast). Do JavaScript do plugin:
	 * root.dispatchEvent(new CustomEvent('nwz:toast', { detail: { message: '...', tone: 'success' } })).
	 * Pausa com o ponteiro ou o foco em cima; Esc ou clique fecham.
	 */
	var current = null;

	function dismiss(toast) {
		if (!toast || toast.classList.contains('is-leaving')) {
			return;
		}
		clearTimeout(toast.nwzTimer);
		toast.classList.remove('is-visible');
		toast.classList.add('is-leaving');
		setTimeout(function () {
			toast.remove();
		}, 450);
		if (current === toast) {
			current = null;
		}
	}

	function arm(toast, ms) {
		clearTimeout(toast.nwzTimer);
		toast.nwzTimer = setTimeout(function () {
			dismiss(toast);
		}, ms);
	}

	function show(toast) {
		var ms = parseInt(toast.getAttribute('data-nwz-toast-ms'), 10) || 4000;
		var content = toast.innerHTML;
		dismiss(current);
		current = toast;
		toast.innerHTML = '';
		toast.hidden = false;
		requestAnimationFrame(function () {
			toast.innerHTML = content; /* Preenche depois de visível para o leitor de tela anunciar. */
			toast.classList.add('is-visible');
		});
		arm(toast, ms);
		toast.addEventListener('mouseenter', function () { clearTimeout(toast.nwzTimer); });
		toast.addEventListener('mouseleave', function () { arm(toast, 1500); });
		toast.addEventListener('click', function () { dismiss(toast); });
	}

	root.querySelectorAll('[data-nwz-toast]').forEach(show);

	root.addEventListener('nwz:toast', function (event) {
		var detail = event.detail || {};
		var tone = detail.tone === 'info' ? 'info' : 'success';
		var toast = document.createElement('div');
		var icon = document.createElement('span');
		var text = document.createElement('span');
		toast.className = 'nwz-toast is-' + tone;
		toast.setAttribute('role', 'status');
		toast.setAttribute('data-nwz-toast', '');
		toast.hidden = true;
		if (detail.ms) {
			toast.setAttribute('data-nwz-toast-ms', String(detail.ms));
		}
		icon.className = 'dashicons ' + (tone === 'info' ? 'dashicons-info' : 'dashicons-yes-alt');
		icon.setAttribute('aria-hidden', 'true');
		text.textContent = String(detail.message || '');
		toast.appendChild(icon);
		toast.appendChild(text);
		root.appendChild(toast);
		show(toast);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && current) {
			dismiss(current);
		}
	});
}());
