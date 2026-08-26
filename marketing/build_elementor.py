#!/usr/bin/env python3
"""Build an Elementor template JSON for the AI Fun Questions landing page.

Free-core widgets only: container, heading, text-editor, button, icon-box,
icon, shortcode, divider. Verified against Elementor 4.2.3 (containers active).
"""
import json, itertools

_ids = itertools.count(1)
def eid():
    return format(next(_ids) + 0x1a2b000, 'x')[:7]

INK      = "#111827"
MUTED    = "#6B7280"
BODY     = "#4B5563"
LINE     = "#E5E7EB"
BG       = "#FBFAF8"
ACCENT   = "#E4922B"
TINT     = "#FBEFD9"
BROWN    = "#8A570F"
DEEP     = "#23160A"
CTA_TEXT = "#5C3A0C"

DISPLAY = "Bricolage Grotesque"
SANS    = "Public Sans"
MONO    = "JetBrains Mono"


def dims(t, r, b, l, unit="px", linked=False):
    return {"unit": unit, "top": str(t), "right": str(r), "bottom": str(b),
            "left": str(l), "isLinked": linked}


def slider(size, unit="px"):
    return {"unit": unit, "size": size, "sizes": []}


def gap(size):
    return {"unit": "px", "size": size, "column": str(size), "row": str(size), "isLinked": True}


def typo(prefix="typography", family=SANS, size=None, weight=None, lh=None,
         ls=None, transform=None):
    s = {f"{prefix}_typography": "custom", f"{prefix}_font_family": family}
    if size is not None:
        s[f"{prefix}_font_size"] = slider(size)
    if weight is not None:
        s[f"{prefix}_font_weight"] = str(weight)
    if lh is not None:
        s[f"{prefix}_line_height"] = {"unit": "em", "size": lh, "sizes": []}
    if ls is not None:
        s[f"{prefix}_letter_spacing"] = slider(ls)
    if transform:
        s[f"{prefix}_text_transform"] = transform
    return s


def container(children=None, **settings):
    return {"id": eid(), "elType": "container", "settings": settings,
            "elements": children or [], "isInner": False}


def widget(wtype, settings):
    return {"id": eid(), "elType": "widget", "settings": settings,
            "elements": [], "widgetType": wtype}


# ---------- widget helpers ----------

def heading(title, size=40, tag="h2", color=INK, family=DISPLAY, weight=800,
            lh=1.05, align=None, extra=None):
    s = {"title": title, "header_size": tag, "title_color": color}
    s.update(typo(family=family, size=size, weight=weight, lh=lh))
    if align:
        s["align"] = align
    if extra:
        s.update(extra)
    return widget("heading", s)


def text(html, size=15, color=BODY, lh=1.6, family=SANS, weight=None, align=None,
         extra=None):
    s = {"editor": html, "text_color": color}
    s.update(typo(family=family, size=size, weight=weight, lh=lh))
    if align:
        s["align"] = align
    if extra:
        s.update(extra)
    return widget("text-editor", s)


def eyebrow(label, color=BROWN, size=11):
    return text(f"<p>{label}</p>", size=size, color=color, lh=1.2,
                extra=typo(family=SANS, size=size, weight=700, ls=1.6,
                           transform="uppercase"))


def button(label, url="#", bg=INK, fg="#FFFFFF", size=15, radius=10,
           pad=(15, 26, 15, 26), align=None):
    s = {
        "text": label,
        "link": {"url": url, "is_external": "", "nofollow": "", "custom_attributes": ""},
        "background_color": bg,
        "button_text_color": fg,
        "border_radius": dims(radius, radius, radius, radius),
        "text_padding": dims(*pad),
        "button_type": "",
    }
    s.update(typo(family=SANS, size=size, weight=600))
    if align:
        s["align"] = align
    return widget("button", s)


def iconbox(icon_class, title, desc, num=None, icon_color=INK, title_color=INK,
            desc_color=MUTED, title_size=17):
    s = {
        "selected_icon": {"value": icon_class, "library": "fa-solid"},
        "title_text": title,
        "description_text": desc,
        "position": "top",
        "title_size": "h3",
        "primary_color": icon_color,
        "icon_space": slider(14),
        "title_color": title_color,
        "description_color": desc_color,
        "content_vertical_alignment": "top",
        "text_align": "left",
    }
    s.update(typo(prefix="title_typography", family=DISPLAY, size=title_size,
                  weight=800, lh=1.25))
    s.update(typo(prefix="description_typography", family=SANS, size=14, lh=1.55))
    s["icon_size"] = slider(26)
    return widget("icon-box", s)


def card(children, bg="#FFFFFF", radius=16, pad=26, border=True, gap_px=12,
         shadow=False, width=None):
    s = {
        "content_width": "full",
        "flex_direction": "column",
        "flex_gap": gap(gap_px),
        "background_background": "classic",
        "background_color": bg,
        "border_radius": dims(radius, radius, radius, radius),
        "padding": dims(pad, pad, pad, pad),
    }
    if border:
        s.update({"border_border": "solid",
                  "border_width": dims(1, 1, 1, 1, linked=True),
                  "border_color": LINE})
    if shadow:
        s.update({"box_shadow_box_shadow_type": "yes",
                  "box_shadow_box_shadow": {"horizontal": 0, "vertical": 8, "blur": 30,
                                            "spread": 0, "color": "rgba(0,0,0,0.06)"}})
    if width:
        s["width"] = slider(width, "%")
    return container(children, **s)


def section(children, bg=None, pad_top=100, pad_bottom=0, boxed_gap=0,
            direction="column", el_id=None):
    inner = {
        "content_width": "boxed",
        "boxed_width": slider(1160),
        "flex_direction": direction,
        "flex_gap": gap(boxed_gap),
        "padding": dims(pad_top, 24, pad_bottom, 24),
    }
    if bg:
        inner["background_background"] = "classic"
        inner["background_color"] = bg
    if el_id:
        inner["_element_id"] = el_id
    return container(children, **inner)


def navlink(label, href):
    s = {"editor": f'<p><a href="{href}" style="color:#4B5563;text-decoration:none;">{label}</a></p>',
         "text_color": BODY}
    s.update(typo(family=SANS, size=14, weight=600, lh=1.2))
    return widget("text-editor", s)


def nav_bar():
    """Top bar. Elementor free has no nav-menu widget (that is Pro), so this is
    an icon + heading on the left and anchor links + a button on the right."""
    return section([
        row([
            row([
                widget("icon", {
                    "selected_icon": {"value": "fas fa-comment-dots", "library": "fa-solid"},
                    "primary_color": INK, "size": slider(22), "view": "default",
                }),
                heading("AI Fun Questions", size=17, tag="div", lh=1.2),
            ], gap_px=10, align="center", wrap="nowrap"),
            row([
                navlink("How it works", "#how"),
                navlink("Where to use it", "#use"),
                navlink("Setup", "#setup"),
                button("Get the plugin", "#get", size=14, pad=(11, 18, 11, 18), radius=9),
            ], gap_px=24, align="center", wrap="nowrap"),
        ], gap_px=24, align="center", justify="space-between", wrap="nowrap"),
    ], bg=BG, pad_top=28)


def footer_bar():
    return section([
        row([
            text("<p>AI Fun Questions &mdash; a small WordPress plugin</p>",
                 size=14, color=MUTED, lh=1.4),
            text("<p>GPL-2.0-or-later</p>", size=12, color=MUTED, lh=1.4,
                 extra=typo(family=MONO, size=12)),
        ], gap_px=24, align="center", justify="space-between"),
    ], bg=BG, pad_top=44, pad_bottom=56)


def row(children, gap_px=20, align="stretch", justify=None, wrap="wrap"):
    """Child widths are percentages but the gap is px, so they must sum to
    comfortably under 100% or the row wraps and the columns stack."""
    s = {"content_width": "full", "flex_direction": "row", "flex_gap": gap(gap_px),
         "flex_align_items": align, "flex_wrap": wrap}
    if justify:
        s["flex_justify_content"] = justify
    return container(children, **s)


def col(children, gap_px=18, width=None, justify=None):
    s = {"content_width": "full", "flex_direction": "column", "flex_gap": gap(gap_px)}
    if width:
        s["width"] = slider(width, "%")
    if justify:
        s["flex_justify_content"] = justify
    return container(children, **s)


def codebox(code, bg=INK, color=TINT, size=14):
    """Code boxes: a HEADING widget with entity-encoded brackets. Both halves
    are load-bearing.

    Elementor runs do_shortcode over the ENTIRE rendered document
    (core/base/document.php:1903), so any literal [ai_fun_question] text
    anywhere on the page renders a live widget no matter which widget holds it,
    and [[double brackets]] get unescaped by an earlier pass and then executed.
    That final pass sees rendered HTML, so &#91; never matches the shortcode
    regex. heading is used because wp_kses_post preserves the entities, where
    text-editor's own filter chain does not."""
    s = {"title": code, "header_size": "div", "title_color": color}
    s.update(typo(family=MONO, size=size, weight=700, lh=1.5))
    return container([widget("heading", s)],
                     content_width="full",
                     background_background="classic",
                     background_color=bg,
                     border_radius=dims(10, 10, 10, 10),
                     padding=dims(14, 16, 14, 16))



def arrow():
    return container([widget("icon", {
        "selected_icon": {"value": "fas fa-arrow-right", "library": "fa-solid"},
        "primary_color": ACCENT,
        "size": slider(18),
        "view": "default",
        "align": "center",
    })], content_width="full", width=slider(3, "%"),
       flex_direction="column", flex_justify_content="center")


def step_row(steps):
    out = []
    for i, (ic, t, d) in enumerate(steps):
        last = i == len(steps) - 1
        out.append(card([iconbox(
            ic, t, d,
            icon_color=ACCENT,
            title_color="#FFFFFF" if last else INK,
            desc_color="#9CA3AF" if last else MUTED,
        )], pad=22, gap_px=0, width=21,
            bg=INK if last else "#FFFFFF",
            border=not last))
        if not last:
            out.append(arrow())
    return out


content = []

content.append(nav_bar())

# ---------------- HERO ----------------
content.append(section([
    row([
        col([
            eyebrow("WordPress plugin &middot; Requires WP 6.4+ &middot; PHP 7.4+"),
            heading("Nobody has heard this joke before.", size=64, tag="h1", lh=0.99),
            text("A tiny widget that asks an AI for one fresh riddle, right when a "
                 "visitor wants one. There is no joke database to run dry &mdash; every "
                 "question is written on the spot, held for ten minutes, then thrown away.",
                 size=19, lh=1.55),
            row([
                button("Add it to your site", "[YOUR DOWNLOAD URL]"),
                button("Read the docs", "[YOUR DOCS URL]", bg="#F3F4F6", fg=INK),
            ], gap_px=14, align="center"),
            text("<p>3 AI providers &nbsp;&middot;&nbsp; 0 questions stored "
                 "&nbsp;&middot;&nbsp; GPL-2.0-or-later</p>", size=12, color=MUTED,
                 extra=typo(family=MONO, size=12, weight=500)),
        ], gap_px=24, width=48),
        col([
            widget("shortcode", {"shortcode": "[ai_fun_question]"}),
        ], width=44),
    ], gap_px=48, align="center"),
], bg=BG, pad_top=64, pad_bottom=0))

# ---------------- SHORTCODE BAND ----------------
content.append(section([
    container([
        row([
            col([
                eyebrow("One shortcode", color=ACCENT),
                heading("That is the entire integration.", size=38, color="#FFFFFF", lh=1.06),
                text("Drop it into a post, a page, a block or a widget area. No settings "
                     "per instance, no shortcode attributes to learn.",
                     size=16, color="#9CA3AF"),
            ], gap_px=18, width=52),
            col([codebox("&#91;ai_fun_question&#93;", bg="#1F2937", size=16)],
                width=42, justify="center"),
        ], gap_px=48, align="center"),
    ], content_width="full",
       background_background="classic", background_color=INK,
       border_radius=dims(24, 24, 24, 24),
       padding=dims(48, 48, 48, 48)),
], bg=BG, pad_top=96))

# ---------------- HOW IT WORKS ----------------
steps = [
    ("fas fa-user", "A visitor lands",
     "The widget renders and asks your site for one question. No account, no cookie, no login."),
    ("fas fa-server", "WordPress asks the AI",
     "Your server calls the provider you configured, with your key. The browser never sees it."),
    ("fas fa-comment-dots", "The question appears",
     "Question and hint go to the browser. The punchline stays behind, in a ten-minute transient."),
    ("fas fa-lock", "They answer, you reveal",
     "The answer is posted back, the punchline is released once, and the question is discarded."),
]
content.append(section([
    eyebrow("How it works"),
    heading("Four steps, and the punchline never leaves the server early.", size=44),
    row(step_row(steps), gap_px=10, align="stretch"),
    container([
        text("<p><strong>Why that matters:</strong> the punchline is never sent with the "
             "question, and it is only released over POST, once per question. Provider keys "
             "stay server-side, AI output is sanitised before it is stored, and requests are "
             "rate limited per visitor and per IP.</p>", size=15, color="#6B4310", lh=1.6),
    ], content_width="full", background_background="classic", background_color=TINT,
       border_radius=dims(16, 16, 16, 16), padding=dims(24, 26, 24, 26)),
], bg=BG, pad_top=104, boxed_gap=26, el_id="how"))

# ---------------- PURPOSE ----------------
content.append(section([
    row([
        col([
            eyebrow("What it is for"),
            heading("A small reason to stay a few seconds longer.", size=44),
        ], gap_px=14, width=44),
        col([
            text("Most &ldquo;fun&rdquo; widgets ship a hundred jokes in an array. Visitors "
                 "see the same one twice and stop looking. This plugin has no bank to "
                 "exhaust: it asks a model for something new each time, so the tenth visit "
                 "is as fresh as the first.", size=17, color="#374151", lh=1.65),
            text("It is deliberately small. One shortcode, one settings screen, no tracking, "
                 "no accounts, nothing stored about the person answering.",
                 size=17, color="#374151", lh=1.65),
        ], gap_px=18, width=48),
    ], gap_px=48, align="start"),
], bg=BG, pad_top=104))

# ---------------- WHERE ----------------
places = [
    ("fas fa-triangle-exclamation", "The 404 page",
     "Someone hit a dead link. Give them a riddle instead of an apology and a search box."),
    ("fas fa-desktop", "A booth screen",
     "WordCamp, a meetup, a careers fair. A laptop on a stand and something to talk about."),
    ("fas fa-newspaper", "A blog sidebar",
     "A recurring bit of personality on a long-form blog, without another content chore."),
    ("fas fa-mug-hot", "An intranet break page",
     "Self-host the model with Ollama and nothing leaves the building."),
    ("fas fa-envelope-open-text", "A thank-you page",
     "After a signup or a purchase, when the work is done and the mood is good."),
    ("fas fa-hourglass-half", "A coming-soon page",
     "Something to do while the real site is being built, other than leaving."),
]
content.append(section([
    eyebrow("Where to put it"),
    heading("Anywhere a page goes quiet.", size=44),
    row([card([iconbox(ic, t, d, icon_color=ACCENT, title_size=18)], width=31)
         for ic, t, d in places], gap_px=20),
], bg=BG, pad_top=104, boxed_gap=26, el_id="use"))

# ---------------- WHO ----------------
who = [
    ("01", "Site owners &amp; bloggers",
     "No code. Install, choose a provider, paste the shortcode. The whole setup is one screen under Settings."),
    ("02", "Agencies &amp; freelancers",
     "A cheap bit of delight for a client build. Keys live in wp-config.php, so they never sit in a database you hand over."),
    ("03", "Developers",
     "A provider interface to implement, filters for rate limits and client IP, and a self-hosted path through Ollama."),
]
content.append(section([
    eyebrow("Who it is for"),
    heading("If you can add a shortcode, you can run it.", size=44),
    row([card([
        text(f"<p>{n}</p>", size=12, color=ACCENT,
             extra=typo(family=MONO, size=12, weight=700)),
        heading(t, size=21, tag="h3", lh=1.2),
        text(d, size=15, color=MUTED),
    ], pad=30, gap_px=12, width=31) for n, t, d in who], gap_px=20),
], bg=BG, pad_top=104, boxed_gap=26))

# ---------------- SETUP ----------------
content.append(section([
    eyebrow("How to use it"),
    heading("Three steps, start to finish.", size=44),
    row([
        card([
            text("<p>1</p>", size=14, color=ACCENT, extra=typo(family=MONO, size=14, weight=700)),
            heading("Install and activate", size=20, tag="h3", lh=1.2),
            text("Upload the plugin and activate it. It creates one small table for rate "
                 "limiting and nothing else.", size=15, color=MUTED),
        ], pad=28, width=31),
        card([
            text("<p>2</p>", size=14, color=ACCENT, extra=typo(family=MONO, size=14, weight=700)),
            heading("Point it at a model", size=20, tag="h3", lh=1.2),
            text("Open <strong>Settings &rsaquo; AI Fun Questions</strong>, pick a provider "
                 "and paste a key &mdash; or keep it out of the database entirely:",
                 size=15, color=MUTED),
            codebox("define( 'AI_FQ_OPENAI_KEY', 'sk-…' );", bg="#F8FAFC",
                    color="#374151", size=12),
        ], pad=28, width=31),
        card([
            text("<p>3</p>", size=14, color=ACCENT, extra=typo(family=MONO, size=14, weight=700)),
            heading("Drop in the shortcode", size=20, tag="h3", lh=1.2),
            text("Paste it into any post, page, block or widget area.", size=15, color=MUTED),
            codebox("&#91;ai_fun_question&#93;", bg=INK, size=14),
        ], pad=28, width=31),
    ], gap_px=20, align="stretch"),
    card([
        text("<p><strong>Pick a provider:</strong> &nbsp;Ollama &mdash; self-hosted, nothing "
             "leaves your network &nbsp;&middot;&nbsp; OpenAI-compatible &mdash; any endpoint "
             "speaking the chat API &nbsp;&middot;&nbsp; Hugging Face &mdash; hosted inference</p>",
             size=15, color=BODY),
    ], pad=26, gap_px=0),
], bg=BG, pad_top=104, boxed_gap=26, el_id="setup"))

# ---------------- CTA ----------------
content.append(section([
    container([
        heading("Go on. Give them something to smile at.", size=48, color=DEEP,
                align="center", lh=1.04),
        text("Free and GPL-2.0-or-later. Bring your own model, or run one on your own hardware.",
             size=18, color=CTA_TEXT, align="center", lh=1.55),
        row([
            button("Download the plugin", "[YOUR DOWNLOAD URL]", size=16, pad=(16, 30, 16, 30)),
            button("Read the docs", "[YOUR DOCS URL]", bg="rgba(255,255,255,0.55)",
                   fg=DEEP, size=16, pad=(16, 26, 16, 26)),
        ], gap_px=14, justify="center", align="center"),
    ], content_width="full", flex_direction="column", flex_gap=gap(20),
       flex_align_items="center",
       background_background="classic", background_color=ACCENT,
       border_radius=dims(24, 24, 24, 24), padding=dims(64, 40, 64, 40)),
], bg=BG, pad_top=104, pad_bottom=0, el_id="get"))

content.append(footer_bar())

template = {
    "content": content,
    "page_settings": {
        "background_background": "classic",
        "background_color": BG,
        "hide_title": "yes",
    },
    "version": "0.4",
    "title": "AI Fun Questions — Landing Page",
    "type": "page",
}

out = "ai-fun-questions-landing-elementor.json"
with open(out, "w") as f:
    json.dump(template, f, indent=1)

n_widgets = json.dumps(content).count('"elType": "widget"')
print(f"wrote {out}: {len(content)} sections, {n_widgets} widgets")
