# Claude CLI Development Guide

## Project

AI Fun Questions is a modular WordPress plugin that generates fresh technology jokes using configurable AI providers.

## Important principles

1. Do not introduce a permanent question bank.
2. Keep AI provider code behind `AI_FQ_Provider_Interface`.
3. Keep REST API logic separate from frontend rendering.
4. Treat AI output as untrusted input.
5. Validate all AI response fields server-side.
6. Do not expose provider credentials to frontend JavaScript.
7. Do not expose the stored punchline through a GET endpoint.
8. Keep generated questions temporary.
9. Avoid unnecessary dependencies.
10. Follow WordPress coding conventions.

## Main extension points

### New AI provider

Create:

```text
includes/providers/class-example.php
```

Implement:

```php
class AI_FQ_Example_Provider implements AI_FQ_Provider_Interface {
	public function generate_question() {
		// Return normalized question array or WP_Error.
	}
}
```

Register the provider in `AI_FQ_Question_Generator::get_provider()`.

### Frontend

The frontend is vanilla JavaScript. Do not add a framework for this small widget unless there is a demonstrated requirement.

### REST API

Public routes are intentional. Do not make them authenticated merely to add authentication; the widget must work for anonymous visitors.

Instead, preserve:

- short-lived widget tokens;
- database-backed one-minute rate limiting;
- rate limiting;
- question tokens;
- client binding;
- POST-only answer reveal.

## Testing before changes are accepted

Run:

```text
php -l <every PHP file>
```

Also run JavaScript syntax validation where Node.js is available:

```text
node --check assets/js/frontend.js
```

Then test:

1. Provider configuration.
2. Question generation.
3. Invalid provider response.
4. Expired question token.
5. Answer submission.
6. Duplicate answer submission.
7. Rate limiting.
8. Multiple widgets on one page.
9. Provider failure.
10. Mobile frontend behavior.

## Do not

- Add hard-coded questions as a fallback without explicit product approval.
- Store customer answers permanently.
- Put API keys in JavaScript.
- Return raw provider/API errors to visitors.
- Trust AI-generated HTML.
- Remove rate limiting from public endpoints.
