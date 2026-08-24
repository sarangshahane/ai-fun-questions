/**
 * AI Fun Questions - settings screen behaviour.
 *
 * Progressive enhancement only: the page submits and saves correctly with
 * JavaScript disabled, since the provider picker is a real radio group.
 */
(() => {
	'use strict';

	const form = document.querySelector('[data-ai-fq-form]');

	if (!form || typeof AI_FQ_ADMIN === 'undefined') {
		return;
	}

	const panels = form.querySelectorAll('[data-ai-fq-panel]');
	const radios = form.querySelectorAll('[data-ai-fq-provider-input]');
	const status = form.querySelector('[data-ai-fq-status]');

	const syncPanels = () => {
		const checked = form.querySelector('[data-ai-fq-provider-input]:checked');
		const active = checked ? checked.value : null;

		panels.forEach((panel) => {
			const isActive = panel.dataset.aiFqPanel === active;
			const state = panel.querySelector('[data-ai-fq-panel-state]');

			panel.classList.toggle('is-active', isActive);

			if (state) {
				state.textContent = isActive
					? AI_FQ_ADMIN.i18n.selected
					: AI_FQ_ADMIN.i18n.inUse;
			}
		});
	};

	let dirty = false;

	const markDirty = () => {
		if (dirty) {
			return;
		}

		dirty = true;

		if (status) {
			status.textContent = AI_FQ_ADMIN.i18n.unsaved;
			status.classList.add('is-dirty');
		}
	};

	radios.forEach((radio) => {
		radio.addEventListener('change', () => {
			syncPanels();
			markDirty();
		});
	});

	form.addEventListener('input', markDirty);
	form.addEventListener('change', markDirty);

	// A submit is about to reload the page, so drop the warning first.
	form.addEventListener('submit', () => {
		dirty = false;
	});

	window.addEventListener('beforeunload', (event) => {
		if (!dirty) {
			return;
		}

		event.preventDefault();
		// Legacy browsers require returnValue to be set.
		event.returnValue = '';
	});

	/*
	 * Ticking "clear" and typing a replacement at the same time is
	 * contradictory, so the checkbox parks the input while it is on.
	 */
	form.querySelectorAll('[data-ai-fq-clear]').forEach((checkbox) => {
		const input = document.getElementById(checkbox.dataset.aiFqClear);

		if (!input) {
			return;
		}

		checkbox.addEventListener('change', () => {
			if (checkbox.checked) {
				input.value = '';
			}

			input.disabled = checkbox.checked;
		});
	});

	syncPanels();
})();
