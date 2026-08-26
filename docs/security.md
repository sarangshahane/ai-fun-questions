# Security

## Public widget

The widget is intentionally usable by anonymous visitors.

Public endpoints are protected with:

- short-lived widget tokens;
- short-lived question tokens;
- rate limiting;
- client binding;
- server-side validation.

## AI output

AI output is never rendered as HTML. It is inserted into the frontend using `textContent`.

The server also:

- requires all expected JSON fields;
- limits field lengths;
- normalizes the category;
- rejects malformed responses.

## Provider credentials

Credentials must remain server-side.

Prefer defining secrets in `wp-config.php`:

```php
define( 'AI_FQ_HF_TOKEN', 'your-token' );
define( 'AI_FQ_OPENAI_KEY', 'your-key' );
```

## Answer protection

The punchline is not returned by a GET request.

The frontend submits the visitor's answer to:

```text
POST /wp-json/ai-fun-questions/v1/answer
```

The server validates the temporary question state before returning the punchline.

## Abuse

The current rate limiter is transient-based. This is appropriate for a low-volume or small site.

For large or distributed deployments, use a shared object-cache or dedicated rate-limit service.
