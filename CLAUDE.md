# Claude CLI Development Guide

## Project

AI Fun Questions is a modular WordPress plugin that generates fresh jokes using configurable AI providers. Subjects are configurable and are not limited to technology.

## Important principles

1. Do not introduce a permanent question bank.
2. Keep AI provider code behind `AI_FQ_Provider_Interface`.
3. Keep REST API logic separate from frontend rendering.
4. Treat AI output as untrusted input.
5. Validate all AI response fields server-side.
6. Do not expose provider credentials to frontend JavaScript.
7. Do not expose the stored punchline through a GET endpoint.
8. Keep generated questions temporary.
9. Avoid unnecessary dependencies.
10. Follow WordPress coding conventions.

## Main extension points

### New AI provider

Create:

```text
includes/providers/class-example.php
```

Implement:

```php
class AI_FQ_Example_Provider implements AI_FQ_Provider_Interface {
	public function generate_question() {
		// Return normalized question array or WP_Error.
	}
}
```

Register the provider in `AI_FQ_Question_Generator::get_provider()`.

### Admin notices

`render_page()` prints `<hr class="wp-header-end">` directly after the header
row. Core's `common.js` relocates every `.notice` to sit immediately before the
first `.wp-header-end`; without that marker WordPress drops "Settings saved."
straight after the `<h1>`. Move or remove the marker and the notice moves with
it.

### Frontend

The frontend is vanilla JavaScript. Do not add a framework for this small widget unless there is a demonstrated requirement.

### REST API

Public routes are intentional. Do not make them authenticated merely to add authentication; the widget must work for anonymous visitors.

Instead, preserve:

- short-lived widget tokens;
- database-backed one-minute rate limiting;
- rate limiting;
- question tokens;
- client binding;
- POST-only answer reveal.

## Testing before changes are accepted

Run:

```text
php -l <every PHP file>
```

Also run JavaScript syntax validation where Node.js is available:

```text
node --check assets/js/frontend.js
```

Then test:

1. Provider configuration.
2. Question generation.
3. Invalid provider response.
4. Expired question token.
5. Answer submission.
6. Duplicate answer submission.
7. Rate limiting.
8. Multiple widgets on one page.
9. Provider failure.
10. Mobile frontend behavior.

## Do not

- Add hard-coded questions as a fallback without explicit product approval.
- Store customer answers permanently.
- Put API keys in JavaScript.
- Return raw provider/API errors to visitors.
- Trust AI-generated HTML.
- Remove rate limiting from public endpoints.

<!-- codedna:start — managed section. Edit freely; keep the markers so a
     teammate running `setup` updates this block instead of duplicating it. -->
# CodeDNA

This repository keeps engineering context next to the code. Each module folder
has a `CLAUDE.md` (rules and gotchas, auto-loaded when you read files there)
and an `architecture.md` (what it owns, and why it is that way).

## Before changing a module

1. Read that module's `architecture.md`. Its `CLAUDE.md` has already loaded.
2. Read the existing implementation, tests, and any linked ADRs or contracts.
3. Use only what those sources and the code show. If something is missing,
   write UNKNOWN or ask. Do not invent APIs, modules, or dependencies.

## While changing code

- Reuse existing services and follow existing boundaries.
- Do not bypass a module's public entrypoint.
- Do not add dependencies without justification.

**Never guess a shape.** If this repo has a schema, contract, or type for what
you are touching — an OpenAPI spec, a JSON Schema, a protobuf, a migration, a
type definition — open it and use it. Field names, payload shapes, enum values,
and endpoint paths are the things a model invents most confidently and most
wrongly, and every one of them is written down somewhere in here.

## After changing code

Update the module's docs in the same change when knowledge shifts. Which file:

- a rule or a gotcha — something Claude would get wrong without it → `CLAUDE.md`
- why the module is shaped this way, or what it owns → `architecture.md`

Never write the same fact in both.

Do not update it for renames, formatting, or internal refactors that change
nothing a caller can observe. Do not add a timestamp: git already records when
the file changed, and a hand-written date can be bumped without saying anything.

New architectural decision? Add an ADR under `docs/decisions/`. Do not rewrite
existing ADRs — supersede them.

## Merge conflicts in these files

- **Rules and Gotchas are append-only. Keep both sides**, never the shorter one
  — dropping a rule silently removes a constraint the code still depends on.
- Two rules that *contradict* each other are a disagreement, not a conflict:
  keep both, mark the module `Needs review`, let the authors settle it.

## Context budget

Load only the affected module's files. Do not read every module's docs for a
local change.
<!-- codedna:end -->
