# AI Fun Questions

AI-powered WordPress widget that generates a fresh technology joke/riddle on demand.

## Core idea

The plugin does not use a hard-coded question bank.

The flow is:

1. Visitor opens the widget.
2. WordPress requests one fresh question from the configured AI provider.
3. The visitor enters an answer.
4. WordPress receives the answer through a POST request.
5. The server reveals the AI-generated punchline.
6. The visitor can request another question.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- A reachable AI provider:
  - Ollama
  - Hugging Face Inference Providers
  - Any compatible OpenAI-style chat-completions endpoint

## Installation

1. Upload the `ai-fun-questions` directory to `wp-content/plugins/`.
2. Activate the plugin.
3. Open **Settings > AI Fun Questions**.
4. Configure the provider.
5. Add `[ai_fun_question]` to a page.

## Ollama

Default endpoint:

```text
http://localhost:11434/api/chat
```

Default model:

```text
gemma3
```

The WordPress server must be able to reach the Ollama server.

## Shortcode

```text
[ai_fun_question]
```

## Architecture

See:

- `architecture.md`
- `claude.md`
- `docs/ai-providers.md`
- `docs/rest-api.md`
- `docs/security.md`
- `docs/frontend.md`
- `docs/testing.md`
- `docs/development.md`
- `docs/production-readiness.md`

## Production notes

The plugin is designed around temporary question state rather than a permanent question database.

Provider secrets can be defined in `wp-config.php`:

```php
define( 'AI_FQ_HF_TOKEN', 'your-token' );
define( 'AI_FQ_OPENAI_KEY', 'your-key' );
```

Do not commit credentials to source control.

## POC scope

This project intentionally remains small and modular. It does not include analytics, a question-management UI, user accounts, persistent answer history, or a permanent question bank.
