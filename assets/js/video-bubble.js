/* ─── Video Bubble – Frontend JS ─────────────────────────────────────────── */

(function () {
    'use strict';

    var config = window.vbConfig || {};
    var container = document.getElementById('vb-container');
    if (!container) return;

    var videoType = container.getAttribute('data-video-type'); // 'bunny' or 'direct'

    // Elements
    var bubbleWrap = document.getElementById('vb-bubble-wrap');
    var bubble = document.getElementById('vb-bubble');
    var bubbleVideo = document.getElementById('vb-bubble-video');   // only for direct
    var bubbleIframe = document.getElementById('vb-bubble-iframe'); // only for bunny
    var bubbleClose = document.getElementById('vb-bubble-close');
    var nameInput = document.getElementById('vb-field-name');
    var panel = document.getElementById('vb-panel');
    var panelClose = document.getElementById('vb-panel-close');
    var panelVideo = document.getElementById('vb-panel-video');     // only for direct
    var panelIframe = document.getElementById('vb-panel-iframe');   // only for bunny
    var videoView = document.getElementById('vb-panel-video-view');
    var formView = document.getElementById('vb-panel-form-view');
    var successView = document.getElementById('vb-panel-success-view');
    var ctaBtn = document.getElementById('vb-cta-btn');
    var formBack = document.getElementById('vb-form-back');
    var form = document.getElementById('vb-contact-form');
    var submitBtn = document.getElementById('vb-submit-btn');
    var emailInput = document.getElementById('vb-field-email');
    var emailStatus = document.getElementById('vb-email-status');
    var feedback = document.getElementById('vb-form-feedback');

    // State
    var emailValid = false;
    var emailCheckTimer = null;
    var isSubmitting = false;

    // Cache iframe URLs before any removal (references go stale after remove)
    var bubbleIframeSrc      = bubbleIframe ? (bubbleIframe.getAttribute('data-src') || '') : '';
    var panelIframeSrc       = panelIframe  ? (panelIframe.getAttribute('data-src') || '') : '';
    var panelIframeSrcMuted  = panelIframe  ? (panelIframe.getAttribute('data-src-muted') || panelIframeSrc) : '';

    // ─── Iframe helpers (zero history pollution) ─────────────────────────────
    // Setting iframe.src ALWAYS adds a browser history entry.  The only
    // reliable way to avoid this is to remove the iframe from the DOM to
    // stop it, and insert a brand-new element to start it.

    // Store insertion anchors so we can put iframes back in the right spot.
    var bubbleIframeAnchor = bubbleIframe ? createAnchor(bubbleIframe) : null;
    var panelIframeAnchor  = panelIframe  ? createAnchor(panelIframe)  : null;

    function createAnchor(el) {
        var marker = document.createComment(el.id + '-anchor');
        el.parentNode.insertBefore(marker, el);
        return marker;
    }

    function stopIframe(iframeId) {
        var el = document.getElementById(iframeId);
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    function loadIframe(iframeId, anchor, url) {
        if (!anchor) return null;
        // Remove any existing instance first
        var old = document.getElementById(iframeId);
        if (old && old.parentNode) old.parentNode.removeChild(old);
        // Build a fresh iframe — never touches an existing .src
        var fresh = document.createElement('iframe');
        fresh.id = iframeId;
        fresh.src = url;
        fresh.setAttribute('loading', 'lazy');
        fresh.setAttribute('allow', 'autoplay; encrypted-media');
        fresh.setAttribute('allowfullscreen', '');
        fresh.style.cssText = 'width:100%;height:100%;border:none;border-radius:inherit;display:block;';
        anchor.parentNode.insertBefore(fresh, anchor.nextSibling);
        return fresh;
    }

    // ─── Scroll Threshold ────────────────────────────────────────────────────

    var scrollThreshold = config.scrollThreshold || 1;

    function checkScroll() {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        if (docHeight <= 0) return;
        var scrolled = (scrollTop / docHeight) * 100;
        if (scrolled >= scrollThreshold) {
            container.classList.remove('vb-scroll-hidden');
            container.classList.add('vb-scroll-visible');
            window.removeEventListener('scroll', checkScroll);
        }
    }

    window.addEventListener('scroll', checkScroll, { passive: true });

    // ─── Bubble Click → Open Panel ──────────────────────────────────────────

    bubble.addEventListener('click', function () {
        openPanel();
    });

    bubbleClose.addEventListener('click', function (e) {
        e.stopPropagation();
        container.classList.add('vb-bubble-hidden');
        // Stop bubble iframe when dismissed
        stopIframe('vb-bubble-iframe');
    });

    // ─── Panel Controls ─────────────────────────────────────────────────────

    panelClose.addEventListener('click', function () {
        // If on success view, hide the entire bubble
        if (successView.style.display === 'block') {
            dismissBubble();
        } else {
            closePanel();
        }
    });

    ctaBtn.addEventListener('click', function () {
        videoView.style.display = 'none';
        formView.style.display = 'block';
        if (panelVideo) panelVideo.pause();
        stopIframe('vb-panel-iframe');
    });

    formBack.addEventListener('click', function () {
        formView.style.display = 'none';
        videoView.style.display = 'flex';
        if (panelVideo) panelVideo.play();
        if (panelIframeAnchor && panelIframeSrcMuted) {
            loadIframe('vb-panel-iframe', panelIframeAnchor, panelIframeSrcMuted);
        }
    });

    function openPanel() {
        bubbleWrap.style.display = 'none';
        panel.classList.add('vb-panel-open');
        panel.setAttribute('aria-hidden', 'false');

        // Stop bubble media
        stopIframe('vb-bubble-iframe');
        if (bubbleVideo) bubbleVideo.pause();

        // Reset to video view
        videoView.style.display = 'flex';
        formView.style.display = 'none';
        successView.style.display = 'none';

        if (videoType === 'bunny' && panelIframeAnchor && panelIframeSrc) {
            // Load panel iframe — unmuted, from beginning
            loadIframe('vb-panel-iframe', panelIframeAnchor, panelIframeSrc);
        } else if (panelVideo) {
            // Play panel video unmuted from start
            panelVideo.muted = false;
            panelVideo.currentTime = 0;
            panelVideo.play().catch(function () {
                panelVideo.muted = true;
                panelVideo.play().catch(function () {});
            });
        }
    }

    function closePanel() {
        panel.classList.remove('vb-panel-open');
        panel.setAttribute('aria-hidden', 'true');

        // Stop panel media
        if (panelVideo) panelVideo.pause();
        stopIframe('vb-panel-iframe');

        // Show bubble again with muted loop
        bubbleWrap.style.display = '';
        if (bubbleIframeAnchor && bubbleIframeSrc) {
            loadIframe('vb-bubble-iframe', bubbleIframeAnchor, bubbleIframeSrc);
        }
        if (bubbleVideo) {
            bubbleVideo.muted = true;
            bubbleVideo.currentTime = 0;
            bubbleVideo.play().catch(function () {});
        }

        // Reset form
        form.reset();
        clearEmailStatus();
        clearFeedback();
        emailValid = false;
    }

    function dismissBubble() {
        panel.classList.remove('vb-panel-open');
        panel.setAttribute('aria-hidden', 'true');

        // Stop all media
        if (panelVideo) panelVideo.pause();
        stopIframe('vb-panel-iframe');
        stopIframe('vb-bubble-iframe');

        // Hide entire bubble
        container.classList.add('vb-bubble-hidden');

        // Reset everything
        form.reset();
        clearEmailStatus();
        clearFeedback();
        emailValid = false;
        videoView.style.display = 'flex';
        formView.style.display = 'none';
        successView.style.display = 'none';
    }

    // Close panel on outside click
    document.addEventListener('click', function (e) {
        if (panel.classList.contains('vb-panel-open') && !panel.contains(e.target) && e.target !== bubble && !bubble.contains(e.target)) {
            closePanel();
        }
    });

    // ─── Email Validation ───────────────────────────────────────────────────

    emailInput.addEventListener('input', function () {
        clearTimeout(emailCheckTimer);
        var val = emailInput.value.trim();

        if (!val) {
            clearEmailStatus();
            emailValid = false;
            return;
        }

        // Basic client-side regex first
        if (!isValidEmailFormat(val)) {
            setEmailStatus('Invalid email format.', 'error');
            emailInput.classList.remove('vb-input-valid');
            emailInput.classList.add('vb-input-error');
            emailValid = false;
            return;
        }

        // If validation is disabled, accept immediately after regex passes
        if (!config.emailValidation) {
            clearEmailStatus();
            clearFeedback();
            emailInput.classList.remove('vb-input-error');
            emailInput.classList.add('vb-input-valid');
            emailValid = true;
            return;
        }

        // Debounce API call
        clearFeedback(); // Clear any stale form-level error
        setEmailStatus('Checking…', 'checking');
        emailCheckTimer = setTimeout(function () {
            verifyEmail(val);
        }, 600);
    });

    function isValidEmailFormat(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
    }

    function verifyEmail(email) {
        var url = config.ajaxUrl +
            '?action=vb_verify_email' +
            '&nonce=' + encodeURIComponent(config.nonce) +
            '&email=' + encodeURIComponent(email);

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    setEmailStatus('', 'valid');
                    emailInput.classList.remove('vb-input-error');
                    emailInput.classList.add('vb-input-valid');
                    emailValid = true;
                    clearFeedback();
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'Please use a valid email address.';
                    setEmailStatus(msg, 'error');
                    emailInput.classList.remove('vb-input-valid');
                    emailInput.classList.add('vb-input-error');
                    emailValid = false;
                }
            })
            .catch(function () {
                // On network error, silently allow through
                emailValid = true;
                clearEmailStatus();
                clearFeedback();
            });
    }

    function setEmailStatus(msg, type) {
        emailStatus.textContent = msg;
        emailStatus.className = 'vb-status-' + type;
    }

    function clearEmailStatus() {
        emailStatus.textContent = '';
        emailStatus.className = '';
        emailInput.classList.remove('vb-input-error', 'vb-input-valid');
    }

    // ─── Form Submission ────────────────────────────────────────────────────

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (isSubmitting) return;

        clearFeedback();

        var name = form.querySelector('[name="name"]').value.trim();
        var email = form.querySelector('[name="email"]').value.trim();
        var message = form.querySelector('[name="message"]').value.trim();

        // Basic validation
        if (!name || !email || !message) {
            setFeedback('Please fill in all fields.', 'error');
            return;
        }

        if (!isValidEmailFormat(email)) {
            setFeedback('Please enter a valid email address.', 'error');
            return;
        }

        if (!emailValid) {
            setFeedback('Please wait for email verification or use a valid email.', 'error');
            return;
        }

        // Submit via AJAX
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';

        var body = new FormData();
        body.append('action', 'vb_submit_form');
        body.append('nonce', config.nonce);
        body.append('name', name);
        body.append('email', email);
        body.append('message', message);
        body.append('page_url', window.location.href);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    // Show success view
                    formView.style.display = 'none';
                    successView.style.display = 'block';
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'Something went wrong.';
                    setFeedback(msg, 'error');
                }
            })
            .catch(function () {
                setFeedback('Network error. Please try again.', 'error');
            })
            .finally(function () {
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send';
            });
    });

    function setFeedback(msg, type) {
        feedback.textContent = msg;
        feedback.className = 'vb-fb-' + type;
    }

    function clearFeedback() {
        feedback.textContent = '';
        feedback.className = '';
    }

    // ─── Auto-play bubble video (direct only) ────────────────────────────────

    if (bubbleVideo) {
        bubbleVideo.play().catch(function () {});
    }

    // ─── Auto-capitalize name ────────────────────────────────────────────────

    nameInput.addEventListener('input', function () {
        var v = nameInput.value;
        if (v.length === 1) {
            nameInput.value = v.charAt(0).toUpperCase();
        } else if (v.length > 1 && v.charAt(0) !== v.charAt(0).toUpperCase()) {
            nameInput.value = v.charAt(0).toUpperCase() + v.slice(1);
        }
    });

})();
