=== Video Bubble ===
Contributors: pythonandvba
Tags: video, bubble, contact form, webhook, bunny stream
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.5
License: GPLv2 or later

A lightweight video bubble widget with muted autoplay, contact form, and webhook integration.

== Description ==

Video Bubble adds a small circular video to the corner of your site that plays on mute. When visitors click it, it expands into a panel with the full video and a contact form. Submissions are sent to any webhook URL you configure.

Think Warm Welcome, but simpler and self-hosted.

== Changelog ==

= 1.3.5 =
* Use helpful form prompts instead of technical validation errors.
* Prevent the bubble flashing before styles load and the scroll threshold is reached.
* Keep focused fields and their glow in the configured accent color.

= 1.3.4 =
* Support direct PythonAndVBA API contact delivery, server-side email verification, and form error feedback.

= 1.3.3 =
* Fixed rectangle still showing around bubble on Themify themes — root cause was Themify's `:where([style*="border-color"])` rule matching our `--vb-border-color` custom property by substring. Renamed the variable to `--vb-accent` (no functional change) and added an explicit border reset on the container for defense.

= 1.3.2 =
* Fixed stray rectangular outline drawn around the circular bubble on sites where the theme or WP core injects global outline/border styles on iframe/button elements (defensive CSS resets inside the widget container)
* Close (×) button now uses visibility instead of opacity to hide when idle, so themes that override opacity cannot force the button into view

= 1.3.1 =
* Fixed bubble showing on mobile despite "Hide on Mobile" being enabled (now uses CSS media query instead of server-side check, works with page caching)
* Added responsive mobile styles — panel opens nearly fullscreen on small screens
* Smaller bubble size on mobile for less intrusion

= 1.3.0 =
* Fixed black screen when opening bubble panel (recreated iframes now preserve original Bunny Stream attributes)
* Added data-no-lazy to iframes to prevent caching plugins (FlyingPress, etc.) from interfering with iframe loading

= 1.2.8 =
* Improved IP detection with Cloudflare, proxy chain, and IPv6 support

= 1.2.7 =
* Webhook payload now includes the visitor's IP address

= 1.2.6 =
* Fixed bubble video preview appearing small/broken after closing panel

= 1.2.5 =
* Fixed browser back button requiring multiple clicks (iframes are now removed/recreated instead of src-swapped)

= 1.2.4 =
* Initial attempt at fixing browser history pollution from iframe src changes

= 1.2.3 =
* Wider page selector and admin layout (960px max-width, taller multi-select)

= 1.2.2 =
* API key and webhook URL fields are now masked (password type) for security

= 1.2.1 =
* Video rules layout now stacks full-width (page selector and video URL each span the full row)

= 1.2.0 =
* Scroll threshold now defaults to 1% (bubble always appears on scroll, never immediately)
* Wider page selector dropdown so full page names are visible
* Added save confirmation toast notification
* Increased multi-select height for better page visibility

= 1.1.1 =
* Fixed flash of bubble on page load when scroll threshold is set

= 1.1.0 =
* Added configurable email validation (toggle on/off, accepted statuses, quick/power mode)
* Added scroll threshold setting — show bubble after X% page scroll
* Integrated plugin-update-checker for auto-updates from GitHub

= 1.0.0 =
* Initial release
* Muted autoplay video bubble with expandable panel
* Contact form with webhook integration
* Reoon email validation (optional, configurable statuses and mode)
* Bunny Stream iframe embed support
* Per-page video rules with wildcard support
* Customizable appearance (size, position, colors, text)
* Mobile visibility toggle
* Auto-updates from GitHub
