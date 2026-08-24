# Testing Checklist

## Installation

- [ ] Plugin installs without PHP fatal errors.
- [ ] Plugin activates successfully.
- [ ] Settings page appears under **Settings → AI Fun Questions**.
- [ ] Plugin can be deactivated without errors.

## Configuration

- [ ] Ollama settings save correctly.
- [ ] Hugging Face settings save correctly.
- [ ] OpenAI-compatible settings save correctly.
- [ ] Provider selection persists after reload.

## Question Generation

- [ ] Shortcode renders correctly.
- [ ] Loading state appears while the request is running.
- [ ] A fresh question is generated.
- [ ] The punchline is not included in the initial question response.
- [ ] The category and hint render when returned.
- [ ] Provider failures show a usable frontend error.

## Answer Flow

- [ ] Empty answers are rejected client-side.
- [ ] Submitted answer is displayed.
- [ ] Punchline is revealed.
- [ ] Next Question generates another question.
- [ ] Previous temporary question expires after its TTL.

## Rate Limiting

- [ ] Rapid repeated generation requests are throttled.
- [ ] The visitor can request another question after the limit expires.

## Security

- [ ] API keys are never present in page source.
- [ ] API keys are never present in localized JavaScript data.
- [ ] Provider credentials are never logged.
- [ ] Generated content is escaped before HTML output.
- [ ] Invalid AI JSON is rejected.

## Production Readiness

The following should remain unchecked for the POC and be addressed before a production release:

- [ ] Single-use reveal token.
- [ ] Dedicated POST reveal endpoint.
- [ ] Stronger rate limiting.
- [ ] Automated PHPUnit tests.
- [ ] AI output moderation.
- [ ] Provider fallback/retry strategy.
- [ ] Privacy review.
