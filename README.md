# Video Bubble

A lightweight WordPress plugin that adds a video bubble to your site — think [Warm Welcome](https://warmwelcome.com), but simpler and self-hosted.

A small circular video plays on mute in the corner of your page. When clicked, it expands into a panel with the full video and a contact form. Form submissions are sent to any webhook you configure.

**Website:** [pythonandvba.com](https://pythonandvba.com)

## Features

- **Muted autoplay bubble** — portrait video loops silently in a small circle
- **Expandable panel** — click to watch with sound + contact form
- **Webhook integration** — form submissions POST as JSON to any URL
- **Email validation** — optional Reoon API integration with configurable accepted statuses
- **Bunny Stream support** — works with Bunny Stream iframe embeds out of the box
- **Per-page video rules** — show different videos on different pages (or use a wildcard for all)
- **Customizable** — bubble size, position, border color, overlay text, success message, and more
- **Mobile toggle** — option to hide the bubble on mobile devices
- **Auto-updates** — updates directly from this GitHub repo via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)

## Installation

1. Download the [latest release](https://github.com/Sven-Bo/wordpress-video-bubble/releases/latest)
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip file and activate
4. Go to **Video Bubble** in the admin sidebar to configure

## Configuration

### General
- **Webhook URL** — where form submissions are sent (JSON POST)

### Email Validation
- **Enable/Disable** — toggle Reoon API email verification
- **API Key** — your [Reoon](https://emailverifier.reoon.com) API key
- **Mode** — Quick (faster) or Power (more accurate, uses more credits)
- **Accepted Statuses** — choose which verification results to accept (valid, safe, unknown, role-based, disposable, etc.)

### Appearance
- Overlay text, font size, and padding
- Bubble size, position, margins, border color
- CTA button text and success message
- Always show dismiss button (or only on hover)
- Hide on mobile

### Video Rules
- Assign different videos to different pages
- Use `*` as a wildcard to show a video on all pages
- Supports direct MP4 URLs and Bunny Stream embed URLs

## Requirements

- WordPress 5.6+
- PHP 7.4+

## License

GPL v2 or later


## PythonAndVBA API delivery (1.3.4+)

After deploying the API, install this plugin version and set **Webhook URL** to
`https://api.pythonandvba.com/contact/web`. Save and purge the site's page cache.
The browser submits directly to the public API, preserving visitor IP rate limits
and origin checks. The plugin's Reoon setting is bypassed in this mode: the API
verifies once at submission, rejects blocked addresses, and shows errors in the form.
If verification is unavailable, the message reaches Sven but no confirmation is
sent to the unverified address, matching the other contact forms.

The shared contact pipeline includes customer license/CRM details, page URL,
notification with customer Reply-To, Todoist task, and confirmation for valid
addresses. Other webhook URLs retain the existing WordPress proxy behavior.
No API token or plugin Reoon key is needed for API mode.


## Browser regression checks

With Playwright available, run `node tests/browser.cjs`. Uses headless Microsoft Edge
by default (`BROWSER_CHANNEL` overrides it); `PLAYWRIGHT_MODULE` can point to a
shared installation. All outbound requests are blocked. Checks cover delayed CSS,
the scroll gate, focus colors, validation, and safe API errors.
