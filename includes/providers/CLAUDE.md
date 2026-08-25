# Module: providers

One outbound HTTP call to an AI chat-completions service, and the raw assistant text pulled out of that service's response envelope. Nothing more.

Read `architecture.md` in this folder before changing this module.

## Rules

- Implement `AI_FQ_Provider_Interface`; return `array|WP_Error` from `generate_question()` — never a raw string, never `false` or `null`.
- Build no prompt and no payload in this module: call `AI_FQ_Question_Generator::request_body()`, then mutate only provider-specific keys on what it returns — `format` (Ollama) or `response_format` (OpenAI).
- Leave all JSON parsing and validation to the generator: hand the raw content string to `AI_FQ_Question_Generator::normalize_response()` and return its result verbatim, `WP_Error`s included.
- Never return upstream error text to the caller: `log_error()` (debug-only), then the generic `public_error()`.
- Read secrets from a constant first, option second — `AI_FQ_HF_TOKEN`, `AI_FQ_OPENAI_KEY`. Option-only breaks the wp-config override path.
- Set `'timeout' => 30` and `'redirection' => 2` on every request.
- Never call `set_transient` in this module. Caching and reuse belong to the caller; a provider makes one call and returns.
- Success is 2xx **and** non-empty content in one condition. A 200 with empty content is a failure, not an empty question.
- Register a new provider in `AI_FQ_Question_Generator::get_provider()` and add its `require_once` to `ai-fun-questions.php` *after* the interface.

## Gotchas

- The envelope path differs per provider: HF and OpenAI-compatible read `choices[0].message.content`, Ollama reads `message.content` (`class-ollama.php:43`). Copy a provider without changing it and every call silently returns `public_error()`.
- Ollama deliberately skips `wp_http_validate_url()` — it rejects loopback, which is the point of self-hosted Ollama (`class-ollama.php:62-66`). Swap it in and the default localhost config dies.
- Ollama's SSRF boundary is a host allowlist escapable via `ai_fq_allow_remote_ollama` (`class-ollama.php:68-88`) — a plugin returning `true` makes this a forwarder to an admin-set URL.
- An unregistered provider name is not an error: `get_provider()` falls through to Ollama (`class-question-generator.php:53-63`).

## After changes

- Rule or boundary changed? Update `architecture.md` in the same change.
- Run: `php -l` on every changed file.
