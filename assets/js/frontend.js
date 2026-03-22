/* AI FAQ Generator — Accordion (no dependencies) */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var triggers = document.querySelectorAll('.aifaq-accordion-trigger');
		if (!triggers.length) return;

		triggers.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var expanded = btn.getAttribute('aria-expanded') === 'true';
				var panelId  = btn.getAttribute('aria-controls');
				var panel    = document.getElementById(panelId);
				if (!panel) return;

				if (expanded) {
					// Collapse
					btn.setAttribute('aria-expanded', 'false');
					panel.classList.remove('is-open');
					// Re-apply hidden after transition
					panel.addEventListener('transitionend', function handler() {
						if (!panel.classList.contains('is-open')) {
							panel.setAttribute('hidden', '');
						}
						panel.removeEventListener('transitionend', handler);
					});
				} else {
					// Expand
					panel.removeAttribute('hidden');
					// Force reflow so transition fires
					void panel.offsetHeight;
					panel.classList.add('is-open');
					btn.setAttribute('aria-expanded', 'true');
				}
			});
		});
	});
}());
