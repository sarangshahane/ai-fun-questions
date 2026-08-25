# AI Fun Questions

AI-powered WordPress widget that generates a fresh technology joke/riddle on demand.

No question bank is ever stored. Every question is generated live by the AI provider you configure, held briefly in a transient, and discarded.

---

## Core idea

1. Visitor opens the widget.
2. WordPress requests one fresh question from the configured AI provider.
3. The visitor enters an answer.
4. WordPress receives the answer through a POST request.
5. The server reveals the AI-generated punchline.
6. The visitor can request another question.

---

## Requirements

- WordPress 6.4+
- PHP 7.4+
- One reachable AI provider:
  - **Ollama** — local, self-hosted
  - **Hugging Face** — Inference Providers
  - **OpenAI-compatible** — any OpenAI-shaped chat-completions endpoint

---

## Setup

### 1. Install

Copy the plugin into your WordPress install and activate it:

```bash
cp -r ai-fun-questions /path/to/wp-content/plugins/
wp plugin activate ai-fun-questions
```

Activation creates the `{prefix}_ai_fq_rate_limits` table used for rate limiting. If you install by unzipping rather than through WordPress, make sure you activate through WordPress so that table gets created.

### 2. Choose a provider

Open **Settings → AI Fun Questions**, pick a provider card, fill in its fields, and save.

### 3. Add the widget

Put the shortcode in any page, post, template, or shortcode-enabled area:

```text
[ai_fun_question]
```

You can place several widgets on one page — each one generates and tracks its own question independently. See [Rate limiting](#rate-limiting) before doing so.

---

## Provider configuration

### Ollama (local, self-hosted)

| Setting | Option | Default |
| --- | --- | --- |
| Ollama URL | `ai_fq_ollama_url` | `http://localhost:11434/api/chat` |
| Ollama Model | `ai_fq_ollama_model` | `gemma3` |

```bash
ollama serve
ollama pull gemma3
```

The WordPress server must be able to reach the Ollama server. Loopback and private addresses are allowed for this provider by design, since that is how a self-hosted Ollama runs. The allowlist is `localhost`, `127.0.0.1`, and `::1`; extend it with filters:

```php
// Allow an extra host.
add_filter(
	'ai_fq_allowed_ollama_hosts',
	function ( $hosts ) {
		$hosts[] = 'ollama.internal';
		return $hosts;
	}
);

// Or allow any remote host (understand the SSRF trade-off first).
add_filter( 'ai_fq_allow_remote_ollama', '__return_true' );
```

### Hugging Face

| Setting | Option | Default |
| --- | --- | --- |
| Hugging Face Token | `ai_fq_hf_token` | — |
| Hugging Face Model | `ai_fq_hf_model` | `Qwen/Qwen3-4B-Instruct-2507` |

Requests go to `https://router.huggingface.co/v1/chat/completions`. Use a model available through Hugging Face Inference Providers.

### OpenAI-compatible

| Setting | Option | Default |
| --- | --- | --- |
| Endpoint | `ai_fq_openai_endpoint` | `https://api.openai.com/v1/chat/completions` |
| API Key | `ai_fq_openai_key` | — |
| Model | `ai_fq_openai_model` | `gpt-4o-mini` |

> **Note:** this provider validates the endpoint with `wp_http_validate_url()`, which rejects loopback and private addresses. It therefore cannot currently point at a local LLM server such as LM Studio, llama.cpp, or LocalAI. Use the Ollama provider for local models.

---

## Credentials

Secrets can live in the database (via the settings screen) or, preferably for production, in `wp-config.php`:

```php
define( 'AI_FQ_HF_TOKEN', 'your-token' );
define( 'AI_FQ_OPENAI_KEY', 'your-key' );
```

A constant always wins over the stored option, and the settings screen shows a `wp-config` badge on any field it is supplying.

> **Important:** these lines must go **above** the `/* That's all, stop editing! */` comment in `wp-config.php`. Anything added after `require_once ABSPATH . 'wp-settings.php';` is defined too late and silently ignored.

### How the secret fields behave

- Stored secrets are **never** rendered into the page. The field shows dots as a placeholder only to signal that something is saved.
- Submitting the form with a secret field left **blank keeps** the existing stored value. This is why saving unrelated settings does not wipe your API key.
- To actually remove a stored credential, tick **Clear the saved value** next to that field and save.

Do not commit credentials to source control.

---

## Rate limiting

Public endpoints are unauthenticated by design — the widget has to work for anonymous visitors — so abuse is contained with rate limiting, short-lived widget tokens, client binding, and POST-only answer reveal.

Default allowance is **5 requests per bucket per 60-second window**, bucketed per client (IP + user agent). Buckets are prefixed `generate|` for question generation and `answer|` for answer submission.

Because every widget on a page fires its own generation request, a page with more than five widgets will see the extras rejected with HTTP 429 on first load. Raise the allowance with the `ai_fq_rate_limit` filter:

```php
add_filter(
	'ai_fq_rate_limit',
	function ( $limit, $bucket ) {
		return 0 === strpos( $bucket, 'generate|' ) ? 20 : $limit;
	},
	10,
	2
);
```

A filtered value of zero or less is ignored and the default applies. Old rows are cleaned hourly by WP-Cron.

---

## REST API

Both routes are public and **POST-only**. The stored punchline is never exposed through a GET endpoint.

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/wp-json/ai-fun-questions/v1/question` | Generate a question |
| `POST` | `/wp-json/ai-fun-questions/v1/answer` | Submit an answer and reveal the punchline |

Both require an `X-AI-FQ-Widget` header carrying the widget token. Question tokens are bound to the issuing client and expire after 10 minutes. See `docs/rest-api.md`.

---

## Troubleshooting

**"The AI service is temporarily unavailable. Please try again."**

This is the deliberate generic message shown to visitors; raw provider errors are never returned to the frontend. The real reason is written to the debug log. Enable logging in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Then check `wp-content/debug.log` for lines tagged `[AI Fun Questions]`. Common causes:

| Log line | Cause |
| --- | --- |
| `cURL error 7: Failed to connect` | Provider is not running or the URL/port is wrong |
| `HTTP status: 401` | API key or token is missing, wrong, or expired |
| `HTTP status: 429` | You have hit the provider's own rate limit |
| `The AI provider returned an invalid response` | Model did not return usable JSON — try a stronger model |
| `The AI response is missing the "…" field` | Model returned JSON in the wrong shape |

**Widget shows "Please wait before requesting another question."**

You hit the plugin's own rate limit (HTTP 429). Wait for the current 60-second window to roll over, or raise the limit with `ai_fq_rate_limit`.

---

## Development

```bash
# PHP syntax check
find . -name '*.php' -exec php -l {} \;

# JavaScript syntax check
node --check assets/js/frontend.js
node --check assets/js/admin.js
```

See `docs/testing.md` for the manual test checklist.

---

## Shortcode

```text
[ai_fun_question]
```

---

## Architecture

- `architecture.md`
- `claude.md`
- `docs/ai-providers.md`
- `docs/rest-api.md`
- `docs/security.md`
- `docs/frontend.md`
- `docs/testing.md`
- `docs/development.md`
- `docs/production-readiness.md`

---

## Scope

This project intentionally remains small and modular. It does not include analytics, a question-management UI, user accounts, persistent answer history, or a permanent question bank.
