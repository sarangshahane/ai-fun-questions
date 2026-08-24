# Frontend

## Shortcode

```text
[ai_fun_question]
```

The shortcode is registered by `AI_FQ_Frontend`.

## Runtime Flow

1. The widget is rendered.
2. JavaScript requests a fresh question.
3. A loading state is shown.
4. The question and optional hint are displayed.
5. The visitor enters an answer.
6. The visitor clicks **Submit Answer**.
7. The POC retrieves and displays the AI punchline.
8. **Next Question** requests another AI-generated question.

## Browser State

The browser stores only the temporary token and the currently displayed question. The provider API key is never included in frontend configuration.

## Styling

The POC CSS is intentionally simple and contained under `.ai-fq` / `.ai-fq__*` selectors to minimize theme conflicts.

## Future UI Improvements

Potential later enhancements:

- Animated reveal.
- Better mobile layout.
- Multiple visual themes.
- Optional category display.
- Optional timer.
- AI-generated reaction to the visitor's answer.
- Feedback controls such as funny/not funny.
- Block editor block instead of shortcode.
