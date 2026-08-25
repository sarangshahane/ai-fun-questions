# REST API

## Namespace

```text
ai-fun-questions/v1
```

The plugin follows WordPress's REST API model and registers routes during `rest_api_init`. WordPress documents `register_rest_route()` as the mechanism for registering custom REST routes and requires the route to be registered on `rest_api_init`.

Official references:

- https://developer.wordpress.org/reference/functions/register_rest_route/
- https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/

## Generate Question

### Request

```http
POST /wp-json/ai-fun-questions/v1/question
Content-Type: application/json
```

No request body is currently required.

### Success Response

```json
{
  "token": "temporary-token",
  "question": "A fresh AI-generated question...",
  "category": "computer",
  "hint": "A small hint..."
}
```

The punchline is intentionally omitted from this response.

## Reveal Punchline

### Request

```http
GET /wp-json/ai-fun-questions/v1/question?token=temporary-token
```

### Success Response

```json
{
  "answer": "The AI-generated punchline."
}
```

## Temporary Storage

The complete generated question is stored in a WordPress transient for 10 minutes.

The token is random and is used as the transient key suffix.

## Rate Limiting

The plugin applies a short transient-based request limit to question generation. It is intentionally lightweight and should not be treated as a production-grade abuse-prevention system.

## Production Recommendation

Replace the current reveal GET request with a dedicated POST endpoint that:

1. Accepts the token and visitor answer.
2. Validates the token.
3. Optionally validates that the answer has been submitted only once.
4. Returns the punchline.
5. Deletes or invalidates the token after successful reveal.

This prevents the punchline from being directly retrievable by simply knowing the token.
