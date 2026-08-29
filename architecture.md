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

Also owns the topic catalogue the prompt rotates through. `topic_groups()` returns
labelled groups — Technology plus general-purpose ones — each mapping a stable slug
to the English phrase dropped into the prompt; `topics()` flattens them. The slug is
what the `ai_fq_topics` option stores, so slugs must not change once sites have
saved a selection.

Technology is one group among several by design: most WordPress sites are not tech
sites. Two things follow from that and are easy to break. The system prompt names no
subject domain — it defers to the subject in the user turn — and every entry in
`ANGLES` is domain-neutral; three angles used to name software and error messages,
which dragged technology into jokes about cats and baking. A category the model
reports that is not in the allowed list falls back to `general`, not `technology`.

`active_topics()` resolves the option down to the topics actually in
play, and returns an EMPTY array for Random. Empty is meaningful rather than an
error: the prompt then asks the model to choose its own subject instead of naming
one, which is what lets Random reach subjects the catalogue does not list. Every
unusable state — missing option, corrupt value, slugs that have drifted out of the
catalogue — resolves there too, so a site with a broken selection keeps generating
rather than stopping.

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
