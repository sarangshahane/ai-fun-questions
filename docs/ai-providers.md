# AI Providers

## Provider Architecture

The plugin uses a common interface:

```php
interface AI_FQ_Provider_Interface {
    public function generate_question();
}
```

The configured provider is selected by `AI_FQ_Question_Generator`.

## Ollama

### Configuration

```text
Provider: Ollama
URL: http://localhost:11434/api/chat
Model: gemma3
```

The plugin sends a chat request with streaming disabled and expects JSON content in the assistant message.

### Network Consideration

If WordPress runs inside Docker, a VM, or on a remote server, `localhost` is relative to that environment. Configure a reachable Ollama URL instead.

## Hugging Face

The plugin uses an OpenAI-compatible chat-completion endpoint and sends a bearer token server-side.

Configure:

- Hugging Face token
- Hugging Face model

The token must remain server-side and must never be passed to frontend JavaScript.

## OpenAI-Compatible

The plugin accepts a configurable endpoint, API key, and model.

This is intentionally generic so the same adapter can be used with services that expose a compatible chat-completion API.

## Common Output Contract

Every provider should ultimately produce:

```json
{
  "question": "...",
  "answer": "...",
  "category": "...",
  "hint": "..."
}
```

The question generator validates the structure before it is stored.

## Failure Handling

Provider errors are converted to `WP_Error` and returned through the REST layer.

Future production improvements should include:

- Retry policy.
- Provider timeout tuning.
- Model availability checks.
- Better structured error diagnostics.
- Optional provider fallback.
