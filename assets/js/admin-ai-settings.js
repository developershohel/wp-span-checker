/**
 * AI Span Settings admin screen.
 */
(function () {
	'use strict';

	var cfg = typeof window.VEFGAiSettings !== 'undefined' ? window.VEFGAiSettings : {};
	var masks = cfg.keyMasks || {};
	var replacePlaceholder = cfg.replacePlaceholder || 'Enter new key to replace';

	function togglePanels() {
		var sel = document.getElementById('vefg_ai_provider');
		if (!sel) {
			return;
		}
		var v = sel.value;
		document.querySelectorAll('.vefg-ai-provider-panel').forEach(function (el) {
			el.style.display = el.getAttribute('data-vefg-provider') === v ? '' : 'none';
		});
	}

	var providerSel = document.getElementById('vefg_ai_provider');
	if (providerSel) {
		providerSel.addEventListener('change', togglePanels);
	}
	togglePanels();

	document.querySelectorAll('.vefg-toggle-key').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var targetId = btn.getAttribute('data-target');
			var input = document.getElementById(targetId);
			var icon = btn.querySelector('.dashicons');

			if (!input || !icon) {
				return;
			}

			if (input.type === 'password') {
				input.type = 'text';
				icon.classList.remove('dashicons-visibility');
				icon.classList.add('dashicons-hidden');
			} else {
				input.type = 'password';
				icon.classList.remove('dashicons-hidden');
				icon.classList.add('dashicons-visibility');
			}
		});
	});

	document.querySelectorAll('.vefg-api-key-input').forEach(function (input) {
		input.addEventListener('focus', function () {
			if (input.dataset.hasKey === '1' && input.value === '') {
				input.placeholder = replacePlaceholder;
			}
		});
		input.addEventListener('blur', function () {
			if (input.dataset.hasKey === '1' && input.value === '') {
				input.placeholder = masks[input.id] || '';
			}
		});
	});
})();
