---
module: providers
owner: UNKNOWN
---

# providers

## Responsibility

Turns a configured credential/endpoint set into one HTTP call to an AI chat-completions service and extracts the raw assistant text from that service's response envelope.

Ownership stops at that string. Prompt construction, JSON parsing, validation, sanitization, and the final question array all belong to `AI_FQ_Question_Generator`. Each provider also owns its own credential resolution and its own error masking.

## Why it is this way

Providers are stateless — no constructor, no properties — so `get_provider()` can construct one per request and discard it. There is no registry or autoloader: dispatch is a `switch`, and files are loaded by explicit `require_once`. For three providers that is less machinery than a discovery mechanism would cost.

`public_error()` and `log_error()` are duplicated byte-identically across all three classes rather than living in an abstract base. That is a deliberate trade of DRY for a flat interface-only hierarchy, and the cost is real: a change to error masking or log redaction has to be made three times, and the next provider author will copy a fourth.

The Ollama SSRF boundary is a host allowlist rather than `wp_http_validate_url()` because that function rejects loopback and private addresses — exactly the addresses a self-hosted Ollama runs on. Two filters (`ai_fq_allowed_ollama_hosts`, `ai_fq_allow_remote_ollama`) are the escape hatch for anyone deliberately pointing at a remote instance. This is the module's only SSRF control.

The 30-second synchronous call sits inside the request that serves the public REST endpoint, with no retry, no backoff, and no caching here. Throughput protection lives entirely outside the module, in the rate limiter.

## Related ADRs

None.
