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

	/*
	 * The model dropdown owns the saved value; the free-text box only matters
	 * while "Custom model…" is picked, so it stays out of the way otherwise.
	 */
	form.querySelectorAll('[data-ai-fq-model]').forEach((select) => {
		const custom = document.getElementById(select.dataset.aiFqModel);

		if (!custom) {
			return;
		}

		select.addEventListener('change', () => {
			const isCustom = select.value === '__custom';

			custom.classList.toggle('is-hidden', !isCustom);

			if (isCustom) {
				custom.focus();
			}
		});
	});

	/*
	 * Random and the individual topics are mutually exclusive. The sanitizer
	 * already enforces that on save; this only makes the form agree before
	 * the round-trip, so the selection never reads as something it is not.
	 */
	const topicsField = form.querySelector('[data-ai-fq-topics]');

	if (topicsField) {
		const randomTopic = topicsField.querySelector('[data-ai-fq-topic-random]');
		const topics = topicsField.querySelectorAll('[data-ai-fq-topic]');

		if (randomTopic) {
			randomTopic.addEventListener('change', () => {
				if (!randomTopic.checked) {
					return;
				}

				topics.forEach((topic) => {
					topic.checked = false;
				});
			});

			topics.forEach((topic) => {
				topic.addEventListener('change', () => {
					if (topic.checked) {
						randomTopic.checked = false;
					}
				});
			});
		}
	}

	/*
	 * Mark the section the reader is in. Purely presentational: the links are
	 * ordinary anchors and still work with this disabled.
	 */
	const jumpLinks = form.parentElement.querySelectorAll('.ai-fq-jump a');

	if (jumpLinks.length) {
		const sections = [...jumpLinks]
			.map((link) => ({ link, target: document.querySelector(link.getAttribute('href')) }))
			.filter((pair) => pair.target);

		const markCurrent = () => {
			// Anything above this line has been scrolled past the sticky header.
			const line = 140;
			let current = sections[0];

			sections.forEach((pair) => {
				if (pair.target.getBoundingClientRect().top <= line) {
					current = pair;
				}
			});

			sections.forEach((pair) => {
				pair.link.classList.toggle('is-current', pair === current);
			});
		};

		markCurrent();
		window.addEventListener('scroll', markCurrent, { passive: true });
	}

	/*
	 * Connection test. One real generation against the configured provider, so
	 * the button is disabled while it runs and the result is announced rather
	 * than only shown — a screen-reader user gets the verdict too.
	 */
	const testButton = document.querySelector('[data-ai-fq-test]');
	const testResult = document.querySelector('[data-ai-fq-test-result]');

	if (testButton && testResult && AI_FQ_ADMIN.testUrl) {
		testButton.addEventListener('click', () => {
			testButton.disabled = true;
			testResult.textContent = AI_FQ_ADMIN.i18n.testing;

			fetch(AI_FQ_ADMIN.testUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': AI_FQ_ADMIN.nonce },
			})
				.then((response) => response.json())
				.then((data) => {
					// A WP_Error body carries `message` but no `ok`.
					testResult.textContent = data && data.message
						? data.message
						: AI_FQ_ADMIN.i18n.testFail;
				})
				.catch(() => {
					testResult.textContent = AI_FQ_ADMIN.i18n.testFail;
				})
				.finally(() => {
					testButton.disabled = false;
				});
		});
	}

	syncPanels();
})();
