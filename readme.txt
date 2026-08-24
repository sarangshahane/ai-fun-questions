=== AI Fun Questions ===
Contributors: sarangshahane
Tags: ai, jokes, openai, ollama, shortcode
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered widget that generates a fresh technology joke on demand. Nothing is stored in a question bank.

== Description ==

AI Fun Questions adds a shortcode widget that asks visitors a fresh, AI-generated technology riddle, lets them type an answer, and then reveals the punchline.

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

Requests go to the Hugging Face router endpoint. The default model is `google/gemma-2-2b-it`. Use a chat-completion model available through Hugging Face Inference Providers.

= OpenAI-compatible =

The default endpoint is the OpenAI chat-completions URL and the default model is `gpt-4o-mini`. This provider rejects loopback and private addresses, so it cannot point at a local LLM server. Use the Ollama provider for local models.

= Storing credentials =

For production, define secrets in `wp-config.php` rather than the database:

`define( 'AI_FQ_HF_TOKEN', 'your-token' );`
`define( 'AI_FQ_OPENAI_KEY', 'your-key' );`

These lines must be placed above the "That's all, stop editing" comment. Anything added after `wp-settings.php` loads is defined too late and silently ignored. A constant always overrides the stored option, and the settings screen marks any field a constant is supplying.

== Frequently Asked Questions ==

= Do I need an API key? =

You need one reachable AI provider. Ollama runs locally and needs no key. Hugging Face and OpenAI-compatible endpoints need a token or key.

= Why does the widget say the AI service is temporarily unavailable? =

That is the deliberate generic message shown to visitors, because raw provider errors are never returned to the frontend. The real reason is written to the debug log. Enable `WP_DEBUG` and `WP_DEBUG_LOG`, then look in `wp-content/debug.log` for lines tagged `[AI Fun Questions]`.

Common causes are a provider that is not running, a wrong URL or port, a missing or expired key returning HTTP 401, or a model that did not return usable JSON.

= Why do I get "Please wait before requesting another question"? =

You hit the plugin's rate limit. The default allowance is 5 requests per client per 60 second window. Wait for the window to roll over or raise the limit with the `ai_fq_rate_limit` filter.

= Can I put more than one widget on a page? =

Yes. Each widget generates and tracks its own question. Note that every widget fires its own generation request, so a page with more than five widgets will see the extras rate limited on first load unless you raise the limit.

= How do I change the rate limit? =

Use the `ai_fq_rate_limit` filter. It receives the current limit and the bucket key, which is prefixed `generate|` for question generation and `answer|` for answer submission, so the two can be tuned separately.

= Are the REST endpoints public? =

Yes, intentionally, because the widget must work for anonymous visitors. Both routes are POST only. They are protected by short-lived widget tokens, question tokens bound to the requesting client, database-backed rate limiting, and a POST-only answer reveal. The punchline is never available over a GET request.

= How do I remove a saved API key? =

Leaving a secret field blank keeps the existing value, so unrelated saves never wipe your key. To actually remove one, tick "Clear the saved value" next to that field and save.

= Does the plugin store visitor answers? =

No. Answers are not persisted. Questions are held only in a short-lived transient.

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
