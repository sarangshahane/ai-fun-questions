=== AI Fun Questions ===
Contributors: sarangshahane
Tags: ai, jokes, openai, ollama, shortcode
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered widget that generates a fresh joke on demand, on subjects you choose. No question bank is ever stored.

== Description ==

AI Fun Questions adds a shortcode widget that asks visitors a fresh, AI-generated riddle, lets them type an answer, and then reveals the punchline.

Pick the subjects it draws on — technology, everyday life, food, animals, work, travel, sport — or leave it on Random and let the AI choose anything at all.

There is no question bank. Every question is generated live by the AI provider you configure, held briefly in a transient, and then discarded.

**How it works**

1. A visitor opens the widget.
2. WordPress requests one fresh question from the configured AI provider.
3. The visitor types an answer.
4. The answer is sent back over a POST request.
5. The server reveals the AI-generated punchline.
6. The visitor can request another question.

**Supported providers**

* Ollama - local and self-hosted
* Hugging Face - Inference Providers
* OpenAI-compatible - any OpenAI-shaped chat-completions endpoint

**Privacy**

Visitor answers are never stored permanently. Questions live only in a short-lived transient bound to the visitor who requested them. Provider credentials stay on the server and are never exposed to frontend JavaScript.

This plugin sends a prompt to whichever third-party AI provider you configure. Review that provider's own privacy policy and terms before enabling it on a production site.

== Installation ==

1. Upload the `ai-fun-questions` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress. Activation creates the rate-limiting table, so activate through WordPress rather than only copying files.
3. Go to **Settings > AI Fun Questions**.
4. Choose a provider, fill in its fields, and save.
5. Add the shortcode `[ai_fun_question]` to any page or post.

= Ollama =

Default endpoint is `http://localhost:11434/api/chat` and the default model is `gemma3`. Start it with `ollama serve` and pull a model with `ollama pull gemma3`. Your WordPress server must be able to reach the Ollama server. Loopback and private addresses are allowed for this provider by design.

= Hugging Face =

Requests go to the Hugging Face router endpoint. The default model is `Qwen/Qwen3-4B-Instruct-2507`. Use a chat-completion model available through Hugging Face Inference Providers.

= OpenAI-compatible =

The default endpoint is the OpenAI chat-completions URL and the default model is `gpt-4o-mini`. This provider rejects loopback and private addresses, so it cannot point at a local LLM server. Use the Ollama provider for local models.

= Storing credentials =

For production, define secrets in `wp-config.php` rather than the database:

`define( 'AI_FQ_HF_TOKEN', 'your-token' );`
`define( 'AI_FQ_OPENAI_KEY', 'your-key' );`

These lines must be placed above the "That's all, stop editing" comment. Anything added after `wp-settings.php` loads is defined too late and silently ignored. A constant always overrides the stored option, and the settings screen marks any field a constant is supplying.

== External services ==

This plugin sends a request to a third-party AI service every time a visitor asks for a
question. Which service is used depends on the provider you select on the settings screen.
No visitor data is transmitted: the request contains only the plugin's own prompt, a randomly
chosen subject, a variation key, and the model name you configured.

**OpenAI-compatible**

Used when the OpenAI-compatible provider is selected. The request goes to the endpoint you
configure, which defaults to https://api.openai.com/v1/chat/completions, and carries your API
key. Sent on every question generation.

* Terms of use: https://openai.com/policies/row-terms-of-use/
* Privacy policy: https://openai.com/policies/row-privacy-policy/

Pointing the endpoint at a different OpenAI-compatible service means that service's own terms
and privacy policy apply instead.

**Hugging Face**

Used when the Hugging Face provider is selected. The request goes to
https://router.huggingface.co/v1/chat/completions and carries your access token. Sent on every
question generation.

* Terms of service: https://huggingface.co/terms-of-service
* Privacy policy: https://huggingface.co/privacy

**Ollama**

Used when the Ollama provider is selected. Ollama is self-hosted software that you run
yourself, and the request goes only to the URL you configure, which defaults to
http://localhost:11434/api/chat. Nothing leaves your own infrastructure and no third party is
involved.

* Project site: https://ollama.com
* License: https://github.com/ollama/ollama/blob/main/LICENSE

**Why not the WordPress AI Client?**

WordPress 7.0 introduced wp_ai_client_prompt(). This plugin talks to the providers directly
because it supports self-hosted Ollama and any OpenAI-compatible endpoint you point it at,
including local model servers, which the AI Client does not currently cover. Provider code is
isolated behind AI_FQ_Provider_Interface, so switching later is a contained change.

== Screenshots ==

1. The settings screen: choose a provider, enter its credentials, pick a model.
2. Question topics: pick the subjects questions are generated from, or leave it on Random.
3. The widget on the front end, before the visitor answers.
4. The widget after answering, with the punchline revealed.

== Frequently Asked Questions ==

= Do I need an API key? =

You need one reachable AI provider. Ollama runs locally and needs no key. Hugging Face and OpenAI-compatible endpoints need a token or key.

= Why does the widget say the AI service is temporarily unavailable? =

That is the deliberate generic message shown to visitors, because raw provider errors are never returned to the frontend. The real reason is written to the debug log. Enable `WP_DEBUG` and `WP_DEBUG_LOG`, then look in `wp-content/debug.log` for lines tagged `[AI Fun Questions]`.

Common causes are a provider that is not running, a wrong URL or port, a missing or expired key returning HTTP 401, or a model that did not return usable JSON.

= Why do I get "Please wait before requesting another question"? =

You hit one of the plugin's rate limits. There are three, checked in order: a site-wide ceiling of 120 questions per minute, a per-IP ceiling of 15 per minute, and 5 per client per minute. Wait for the window to roll over, or raise the relevant limit with the `ai_fq_rate_limit` filter.

= Can I put more than one widget on a page? =

Yes. Each widget generates and tracks its own question. Note that every widget fires its own generation request, so a page with more than five widgets will see the extras rate limited on first load unless you raise the limit.

= How do I change the rate limit? =

Use the `ai_fq_rate_limit` filter. It receives the current limit and the bucket key, so every tier can be tuned separately:

* `generate-global` — the whole site, default 120 per minute. This is the one that caps what the plugin can spend on your AI account, so raise it deliberately.
* `generate-ip|…` — one visitor address, default 15 per minute.
* `generate|…` — one client, default 5 per minute.
* `answer-ip|…` and `answer|…` — the same two tiers for answer submissions.

The site-wide ceiling exists because the per-visitor limits only bound one address each. Without it, a page elsewhere on the web could make its own visitors' browsers call your endpoint, and every call would be billed to you.

= My site is behind Cloudflare or a reverse proxy =

Configure the `ai_fq_client_ip` filter. Without it every visitor appears to arrive from the
proxy, so the whole site shares one per-IP allowance and the limits will fire far too early.
Return the real client address from whichever header your proxy sets, after validating it.

If the public host differs from what WordPress reports — a mapped multisite domain, or a proxy
in front of a different internal host — also add it with the `ai_fq_allowed_origin_hosts`
filter, or question requests will be refused as cross-origin.

= Are the REST endpoints public? =

Yes, intentionally, because the widget must work for anonymous visitors. Both routes are POST only. They are protected by short-lived widget tokens, question tokens bound to the requesting client, three tiers of database-backed rate limiting including a site-wide ceiling, and a POST-only answer reveal. Question generation also refuses requests carrying a cross-origin `Origin` header, so the endpoint cannot be driven from someone else's page. The punchline is never available over a GET request.

= How do I remove a saved API key? =

Leaving a secret field blank keeps the existing value, so unrelated saves never wipe your key. To actually remove one, tick "Clear the saved value" next to that field and save.

= Does the plugin store visitor answers? =

No. Answers are not persisted. Questions are held only in a short-lived transient.

= What happens when I delete the plugin? =

Deleting it from the Plugins screen removes its settings, including saved API keys, and drops
the rate-limit table. Deactivating changes nothing, so you can deactivate and reactivate
without losing your configuration.

== Changelog ==

= 1.0.0 =
* First stable release.
* Added an explicit "Clear the saved value" option for stored provider credentials.
* Added GPLv2 license and complete plugin metadata.
* Documented setup, configuration, and troubleshooting.

= 0.3.0 =
* Redesigned the settings screen with a card-based provider picker and per-provider panels.
* Added an unsaved-changes indicator and a warning when navigating away with unsaved edits.

= 0.2.1 =
* Fixed the shortcode rendering its markup outside the content flow.
* Fixed rate limiting never triggering because of a sliding window key.
* Fixed a rate-limit bypass through a client-supplied widget token.
* Fixed saved provider credentials being wiped whenever settings were saved.
* Fixed the Ollama provider rejecting valid loopback URLs.
* Added rate limiting to the answer endpoint.
* Fixed a fatal error on malformed question transients.
* Fixed answer length being measured in bytes rather than characters.
* Added feedback when submitting an empty answer, and a retry action when generation fails.

= 0.2.0 =
* Initial modular release with pluggable AI providers.

== Upgrade Notice ==

= 1.0.0 =
First stable release. Includes important earlier fixes: rate limiting previously never triggered, and saving settings could wipe stored API credentials.
