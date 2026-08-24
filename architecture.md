# Architecture

## Request flow

```text
Visitor
  |
  v
Shortcode widget
  |
  | POST /wp-json/ai-fun-questions/v1/question
  | X-AI-FQ-Widget
  v
REST API
  |
  v
Question Generator
  |
  +--> Ollama
  +--> Hugging Face
  +--> OpenAI-compatible provider
  |
  v
Validated question
  |
  v
Short-lived WordPress transient
  |
  v
Browser displays question
  |
  | POST /wp-json/ai-fun-questions/v1/answer
  | token + customer answer
  v
REST API validates token/client/reveal state
  |
  v
Return customer's answer + AI punchline
```

## Components

### `ai-fun-questions.php`

Plugin bootstrap and dependency loading.

### `includes/class-plugin.php`

Single initialization point for admin, REST API, and frontend components.

### `includes/class-question-generator.php`

Owns the shared AI prompt, provider selection, JSON normalization, length validation, and category normalization.

### `includes/providers/`

Provider adapters implement `AI_FQ_Provider_Interface`.

### `includes/class-rest-api.php`

Owns public question generation and answer submission.

### `includes/class-frontend.php`

Registers assets and renders the shortcode widget.

### `includes/class-admin.php`

Provides provider configuration under WordPress Settings.

## State

The plugin does not maintain a permanent question bank.

A generated question is stored in a short-lived transient keyed by a random question token. The stored state includes:

- question
- punchline
- category
- hint
- widget token hash
- client hash
- created timestamp
- revealed state

## Security model

The widget is intentionally public, so authentication is not required.

Instead:

- question generation requires a server-issued widget token;
- generation is rate-limited;
- question tokens are short-lived;
- answer submission is POST-only;
- answer submission validates the question token and client binding;
- a question cannot be revealed twice;
- provider errors are not exposed to visitors.

This is appropriate for a small public widget. High-volume sites should use a stronger shared rate-limit store and optionally add bot protection.
