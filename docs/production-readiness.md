# Production Readiness

## Current status

This version is suitable as a small production-oriented POC, but it still depends on the selected AI provider's uptime, limits, model quality, and infrastructure.

## Security controls

- AI answers are not exposed through a public GET endpoint.
- Answers are revealed only through `POST /answer`.
- Question tokens are random and short-lived.
- Question tokens are bound to the widget token and a client hash.
- Generated questions are stored temporarily in WordPress transients.
- Public generation has a short rate limit.
- AI provider errors returned to visitors are generic.
- AI output is treated as plain text and length-limited.
- Provider secrets can be supplied through `wp-config.php`.

## Provider secrets

Prefer:

```php
define( 'AI_FQ_HF_TOKEN', 'your-token' );
define( 'AI_FQ_OPENAI_KEY', 'your-key' );
```

Do not commit these values to source control.

## Remaining operational considerations

For high-traffic deployments, replace transient-based rate limiting with a shared persistent object-cache or dedicated rate-limit store. WordPress transients are adequate for the POC and low-volume usage but are not a perfect distributed rate limiter.

For a public site, consider adding analytics, abuse detection, bot protection, provider failover, and a configurable maximum generation budget.

## AI quality

AI-generated jokes are nondeterministic. The plugin validates structure and length, but it does not guarantee that every generated joke is genuinely funny or unique.

## Rate limiting

Generation and answer requests use the `wp_ai_fq_rate_limits` table with a one-minute window, bucketed per client (IP + user agent). Old rows are cleaned hourly by WP-Cron.

The default allowance is 5 requests per bucket per window. Every widget on a page fires its own generation request, so a page holding more than five widgets will see the extras rejected with HTTP 429 on first load. Raise the allowance with the `ai_fq_rate_limit` filter:

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

The bucket prefix is `generate|` for question generation and `answer|` for answer submission, so the two can be tuned independently. A filtered value of zero or less is ignored and the default applies.
