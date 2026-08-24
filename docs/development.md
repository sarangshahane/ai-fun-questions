# Development Guide

## Requirements

- WordPress 6.4+
- PHP 7.4+
- A configured AI provider
- HTTPS is recommended for any remotely hosted WordPress installation

## Local Installation

1. Copy the plugin directory into `wp-content/plugins/`.
2. Activate the plugin from the WordPress admin.
3. Open **Settings → AI Fun Questions**.
4. Configure an AI provider.
5. Create a page containing:

```text
[ai_fun_question]
```

## Ollama Development

For local development, configure:

```text
Provider: Ollama
URL: http://localhost:11434/api/chat
Model: gemma3
```

Make sure the WordPress PHP environment can connect to that URL.

## Debugging

Enable WordPress debugging in the normal development configuration:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Do not expose debug output on a public site.

## Coding Conventions

- Follow WordPress PHP coding conventions.
- Use WordPress escaping and sanitization APIs.
- Return `WP_Error` for expected API failures.
- Keep provider-specific logic isolated.
- Avoid introducing a framework for this small POC.

## Documentation Rule

When a code change changes behavior, update the closest relevant Markdown document in `docs/` and update the root README if the user-facing setup changes.
