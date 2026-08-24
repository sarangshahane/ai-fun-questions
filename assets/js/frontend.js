(() => {
	'use strict';

	const widgets = document.querySelectorAll('[data-ai-fq]');

	if (!widgets.length || typeof AI_FQ === 'undefined') {
		return;
	}

	const requestQuestion = async (widget) => {
		const loading = widget.querySelector('[data-ai-fq-loading]');
		const content = widget.querySelector('[data-ai-fq-content]');
		const error = widget.querySelector('[data-ai-fq-error]');
		const errorText = widget.querySelector('[data-ai-fq-error-text]');
		const retry = widget.querySelector('[data-ai-fq-retry]');
		const question = widget.querySelector('[data-ai-fq-question]');
		const token = widget.querySelector('[data-ai-fq-token]');
		const answer = widget.querySelector('[data-ai-fq-answer]');
		const submit = widget.querySelector('[data-ai-fq-submit]');
		const next = widget.querySelector('[data-ai-fq-next]');
		const result = widget.querySelector('[data-ai-fq-result]');
		const hint = widget.querySelector('[data-ai-fq-hint]');

		loading.hidden = false;
		content.hidden = true;
		error.hidden = true;
		errorText.textContent = '';
		retry.hidden = true;
		result.hidden = true;
		next.hidden = true;
		submit.hidden = false;
		answer.value = '';
		hint.textContent = '';
		submit.disabled = false;

		try {
			const response = await fetch(`${AI_FQ.restUrl}/question`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-AI-FQ-Widget': widget.dataset.widgetToken
				},
				body: JSON.stringify({})
			});

			const data = await response.json();

			if (!response.ok) {
				throw new Error(data.message || AI_FQ.i18n.error);
			}

			question.textContent = data.question;
			token.value = data.token;
			hint.textContent = data.hint ? `Hint: ${data.hint}` : '';

			loading.hidden = true;
			content.hidden = false;
			answer.focus();
		} catch (requestError) {
			loading.hidden = true;
			errorText.textContent = requestError.message || AI_FQ.i18n.error;
			retry.hidden = false;
			error.hidden = false;
		}
	};

	widgets.forEach((widget) => {
		const submit = widget.querySelector('[data-ai-fq-submit]');
		const next = widget.querySelector('[data-ai-fq-next]');
		const answer = widget.querySelector('[data-ai-fq-answer]');
		const result = widget.querySelector('[data-ai-fq-result]');
		const yourAnswer = widget.querySelector('[data-ai-fq-your-answer]');
		const punchline = widget.querySelector('[data-ai-fq-punchline]');
		const token = widget.querySelector('[data-ai-fq-token]');
		const error = widget.querySelector('[data-ai-fq-error]');
		const errorText = widget.querySelector('[data-ai-fq-error-text]');
		const retry = widget.querySelector('[data-ai-fq-retry]');

		submit.addEventListener('click', async () => {
			const value = answer.value.trim();

			if (!value) {
				errorText.textContent = AI_FQ.i18n.emptyAnswer;
				retry.hidden = true;
				error.hidden = false;
				answer.focus();
				return;
			}

			submit.disabled = true;
			error.hidden = true;
			errorText.textContent = '';
			retry.hidden = true;

			try {
				const response = await fetch(`${AI_FQ.restUrl}/answer`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-AI-FQ-Widget': widget.dataset.widgetToken
					},
					body: JSON.stringify({
						token: token.value,
						answer: value
					})
				});

				const data = await response.json();

				if (!response.ok) {
					throw new Error(data.message || AI_FQ.i18n.error);
				}

				yourAnswer.textContent = data.your_answer;
				punchline.textContent = data.answer;
				result.hidden = false;
				submit.hidden = true;
				next.hidden = false;
			} catch (requestError) {
				submit.disabled = false;
				errorText.textContent = requestError.message || AI_FQ.i18n.error;
				error.hidden = false;
			}
		});

		retry.addEventListener('click', () => {
			requestQuestion(widget);
		});

		next.addEventListener('click', () => {
			requestQuestion(widget);
		});

		requestQuestion(widget);
	});
})();
