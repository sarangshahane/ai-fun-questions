---
paths:
  - "includes/class-rest-api.php"
  - "includes/class-rate-limiter.php"
  - "includes/class-frontend.php"
  - "assets/js/frontend.js"
  - "templates/question-widget.php"
---

# widget-security

The punchline is minted server-side, held in a short-lived transient keyed by an unguessable question token, and released exactly once — only over POST, only to a caller re-presenting both tokens with a matching IP+UA fingerprint. This invariant spans four files and no single one of them owns it.

## Rules

- `$data['question']['answer']` reaches the wire from exactly one place: `submit_answer` (`class-rest-api.php:204`). The `/question` response projects only `token`, `question`, `category`, `hint` (`:96-103`) — never spread `$question` wholesale, because `normalize_response()` returns `answer` inside that same array.
- Both routes stay `WP_REST_Server::CREATABLE` (`class-rest-api.php:21`, `:31`). Never use `READABLE` on either one, and never add a convenience `GET /question/{token}` — either breaks the seam open.
- `X-AI-FQ-Widget` (`frontend.js:41,100` ↔ `class-rest-api.php:49,109`) and `data-widget-token` (`class-frontend.php:57` ↔ `frontend.js:41`) must match on both sides.
- Both tokens are `wp_generate_password( 48, false, false )` and must satisfy `valid_token()`'s `/^[A-Za-z0-9]{32,64}$/` (`class-rest-api.php:221`). Change the length or the character-class flags at one mint site and every request fails `ai_fq_invalid_widget`.
- The HMAC recipe at write time (`class-rest-api.php:88-89`) and its re-derivation at read time (`:156`, `:164`) must stay byte-identical, compared with `hash_equals`. Changing the salt context on one side breaks every in-flight question closed.
- Never put the client-supplied widget token in a rate-limit bucket (`class-rest-api.php:59-63`) — rotating it would hand any caller unlimited quota. Keep `'generate|'`, `'generate-ip|'` and `'answer|'` namespaced apart. Generation is charged against two of them: the per-client bucket (IP+UA, `LIMIT`) and a per-IP ceiling (`IP_LIMIT`) that must stay keyed on `ip_hash()` alone — putting the User-Agent into the ceiling restores the rotation bypass it exists to close.
- Keep `PRIMARY KEY (bucket_key, window_start)` (`class-rate-limiter.php:112`) and the fixed-window snap `$now - ( $now % WINDOW )` (`:28-33`). Either one removed and `ON DUPLICATE KEY UPDATE` inserts instead of increments — **rate limiting stops with no error and no test failure**.
- The limiter fails closed on a DB error (`class-rate-limiter.php:53-56`) and rejects non-positive `ai_fq_rate_limit` values (`:81-83`). Both must stay.
- The reveal is one-shot: check `revealed`, set it, re-persist (`class-rest-api.php:181-199`). Drop the re-`set_transient` and the reveal is replayable within the TTL.
- Only `restUrl` and `i18n` go into the localized `AI_FQ` object (`class-frontend.php:32-46`). No provider setting is ever added.

## Gotchas

- **`client_hash()` is caller-forgeable**: it mixes the IP with the attacker-controlled `HTTP_USER_AGENT`, so rotating the UA still yields a fresh per-client bucket *and* a fresh binding. Generation spend is bounded only by the per-IP ceiling; the reveal binding gains nothing adversarially from the UA half.
- **Behind a proxy/CDN** `REMOTE_ADDR` is the proxy: visitors share one IP ceiling (breaks closed at scale) and one `client_hash`, so the binding stops distinguishing them (breaks open). Forwarded headers are never trusted by default — a site that terminates its own proxy supplies the real address through the `ai_fq_client_ip` filter, and it must not be wired to a raw client-supplied header.
- **The widget token is client-invented**, never recorded server-side (`class-frontend.php:53`) — only its shape is checked. It is a per-widget correlation value, not authentication. Do not build auth on it.
- **`templates/question-widget.php` is dead and stale** — nothing loads it; the live markup is inlined in `AI_FQ_Frontend::shortcode()`. It lacks every `data-ai-fq-*` node `frontend.js` requires, so reviving it throws a `TypeError` inside `widgets.forEach` that kills every widget on the page.
- **Six or more widgets on one page** exhaust the 5/min per-client quota on load (`frontend.js:134`), and 16+ would hit the IP ceiling. The `ai_fq_rate_limit` filter receives the bucket, so raise the two independently.
- **Reveal shortens the TTL to 2 minutes** (`class-rest-api.php:195-199`) so a duplicate submit gets a deterministic 409 rather than a confusing 404. Do not drop it.
- **The table is created on activation only** (`class-plugin.php:16-18`), with no upgrade routine. A missing table fails closed everywhere — every widget shows "Please wait before requesting another question."
- **The absent nonce is deliberate**, not an oversight: `permission_callback` is `__return_true` on both routes because the widget must work for anonymous visitors, and the endpoints read no cookie and mutate nothing user-owned. The header/token pair is the substitute. Adding a nonce breaks anonymous rendering.

## The contract

There is no schema, type, or spec defining what crosses this seam — the shapes live only in the code on both sides. That absence is why the header name, DOM attribute, token regex, and HMAC recipe are each listed above as a two-sided rule.
