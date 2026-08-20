/* project.portal — public page behaviour.
   No framework, no build step, no inline script (keeps a strict CSP viable). */
(function () {
    'use strict';

    var root = document.documentElement;

    /* ── Unobtrusive form handlers ──────────────────────────────────────
       No inline onchange=/onsubmit= anywhere in the views — a strict CSP
       (script-src with no 'unsafe-inline') blocks those attributes outright,
       so this delegates from markers instead: .archive-select auto-submits
       its form on change, and any form with data-confirm asks before it
       submits. Delegated on document so it also covers admin CRUD tables. */
    document.addEventListener('change', function (e) {
        if (e.target.matches && e.target.matches('.archive-select')) {
            e.target.form.submit();
        }
    });

    /* Double-clicking the Toolbox tile title is a quiet shortcut to admin login. */
    document.addEventListener('dblclick', function (e) {
        if (e.target.closest && e.target.closest('#t3')) {
            window.location.href = '/admin/login';
        }
        /* Double-clicking the admin brand mark returns to the public portal
           without signing out — plain navigation, session cookie untouched. */
        if (e.target.closest && e.target.closest('#adminBrandMark')) {
            window.location.href = '/';
        }
    });

    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    /* Live hex readout next to each theme-color swatch (admin/theme.php). */
    document.addEventListener('input', function (e) {
        if (!e.target.matches || !e.target.matches('input[type="color"]')) return;
        var hex = e.target.parentElement.querySelector('.color-hex');
        if (hex) hex.textContent = e.target.value.toUpperCase();
    });

    /* Live brand-icon preview next to each platform <select> (admin/profile.php). */
    document.addEventListener('change', function (e) {
        if (!e.target.matches || !e.target.matches('.social-platform-select')) return;
        var preview = e.target.closest('form').querySelector('.social-icon-preview');
        if (!preview) return;
        var swatches = preview.querySelectorAll('.social-icon-swatch');
        for (var i = 0; i < swatches.length; i++) {
            swatches[i].classList.toggle('is-active', swatches[i].getAttribute('data-platform') === e.target.value);
        }
    });

    /* ── Live post preview (admin/content/form.php) ─────────────────────
       Debounced so a fast typist doesn't fire a request per keystroke, and
       posts only the fields the preview actually renders — never the
       image file input, so an attached photo isn't re-uploaded on every
       keystroke. Rendered server-side by the same Markdown converter the
       live site uses (AdminContentController::preview()), so the preview
       never drifts from what actually publishes. */
    var previewForm = document.querySelector('[data-preview-form]');
    var previewTarget = document.getElementById('contentPreview');

    if (previewForm && previewTarget) {
        var PREVIEW_FIELDS = ['category', 'title', 'author', 'body', 'link_url', 'image_url', 'published_at'];
        var previewTimer;

        var refreshPreview = function () {
            var params = new URLSearchParams();
            for (var i = 0; i < PREVIEW_FIELDS.length; i++) {
                var field = previewForm.elements[PREVIEW_FIELDS[i]];
                if (field) params.set(PREVIEW_FIELDS[i], field.value);
            }
            params.set('_csrf', previewForm.elements['_csrf'].value);

            fetch('/admin/content/preview', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            }).then(function (res) {
                return res.ok ? res.text() : null;
            }).then(function (html) {
                if (html !== null) previewTarget.innerHTML = html;
            }).catch(function () {});
        };

        previewForm.addEventListener('input', function (e) {
            if (PREVIEW_FIELDS.indexOf(e.target.name) === -1) return;
            clearTimeout(previewTimer);
            previewTimer = setTimeout(refreshPreview, 300);
        });

        previewForm.addEventListener('change', function (e) {
            if (PREVIEW_FIELDS.indexOf(e.target.name) === -1) return;
            clearTimeout(previewTimer);
            refreshPreview();
        });

        /* ── Auto-upload post image ───────────────────────────────────────
           Uploads the moment a file is chosen instead of waiting for "Save
           post" — which was the only way to attach an image, and always
           redirected away to the archive list. Sets the hidden image_url
           field and dispatches a synthetic change on it, which the preview
           listener above already treats like any other field edit — no
           direct call into refreshPreview needed. */
        var imageInput = previewForm.elements['image'];
        var imageUrlField = previewForm.elements['image_url'];
        var uploadPreview = previewForm.querySelector('.upload-preview');
        var uploadPreviewImg = uploadPreview ? uploadPreview.querySelector('img') : null;
        var removeImageWrap = previewForm.querySelector('[data-remove-image-wrap]');
        var removeImageCheckbox = previewForm.elements['remove_image'];

        imageInput.addEventListener('change', function () {
            var file = imageInput.files && imageInput.files[0];
            if (!file) return;

            var body = new FormData();
            body.append('image', file);
            body.append('_csrf', previewForm.elements['_csrf'].value);

            fetch('/admin/content/upload-image', { method: 'POST', body: body })
                .then(function (res) {
                    return res.text().then(function (text) {
                        return { ok: res.ok, text: text };
                    });
                })
                .then(function (result) {
                    imageInput.value = '';
                    if (!result.ok) {
                        toast(result.text || 'Upload failed.');
                        return;
                    }
                    imageUrlField.value = result.text;
                    if (uploadPreviewImg) uploadPreviewImg.src = result.text;
                    if (uploadPreview) uploadPreview.removeAttribute('hidden');
                    if (removeImageWrap) removeImageWrap.removeAttribute('hidden');
                    if (removeImageCheckbox) removeImageCheckbox.checked = false;
                    toast('Image uploaded');
                    imageUrlField.dispatchEvent(new Event('change', { bubbles: true }));
                })
                .catch(function () {
                    imageInput.value = '';
                    toast('Upload failed — network error.');
                });
        });
    }

    /* ── Interaction sound design ───────────────────────────────────────
       Synthesized with Web Audio — no audio files, no extra requests, one
       less thing for the CSP to worry about. Light theme gets a soft
       conventional UI tick; dark theme gets a resonant sci-fi console tone
       (think Tron UI blips), same two triggers, different texture. The
       AudioContext is created lazily on first gesture: browsers won't let
       it make sound before one anyway (autoplay policy). */
    var audioCtx = null;

    function audioContext() {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        if (!audioCtx) audioCtx = new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        return audioCtx;
    }

    /* One short envelope-shaped oscillator tone. freqEnd, if given, sweeps
       the pitch across the duration instead of holding it flat. */
    function tone(ctx, o) {
        var now = ctx.currentTime;
        var osc = ctx.createOscillator();
        var filter = ctx.createBiquadFilter();
        var gain = ctx.createGain();

        osc.type = o.wave || 'sine';
        osc.frequency.setValueAtTime(o.freq, now);
        if (o.freqEnd) osc.frequency.exponentialRampToValueAtTime(o.freqEnd, now + o.duration);

        filter.type = o.filterType || 'lowpass';
        filter.frequency.setValueAtTime(o.filterFreq || 4000, now);
        filter.Q.setValueAtTime(o.q || 0.7, now);

        gain.gain.setValueAtTime(0, now);
        gain.gain.linearRampToValueAtTime(o.gain, now + (o.attack || 0.004));
        gain.gain.exponentialRampToValueAtTime(0.0001, now + o.duration);

        osc.connect(filter);
        filter.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + o.duration + 0.02);
    }

    /* Very short filtered noise burst — adds a bit of "digital grain" under
       the dark-theme click, on top of the tone. */
    function noiseBurst(ctx, o) {
        var now = ctx.currentTime;
        var len = Math.max(1, Math.floor(ctx.sampleRate * o.duration));
        var buffer = ctx.createBuffer(1, len, ctx.sampleRate);
        var data = buffer.getChannelData(0);
        for (var i = 0; i < len; i++) data[i] = Math.random() * 2 - 1;

        var src = ctx.createBufferSource();
        src.buffer = buffer;

        var filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(o.filterFreq, now);
        filter.Q.setValueAtTime(o.q || 6, now);

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0, now);
        gain.gain.linearRampToValueAtTime(o.gain, now + 0.003);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + o.duration);

        src.connect(filter);
        filter.connect(gain);
        gain.connect(ctx.destination);
        src.start(now);
    }

    var SOUND = {
        light: {
            hover: function (ctx) {
                tone(ctx, { wave: 'sine', freq: 720, gain: .045, duration: .07, filterFreq: 2600 });
            },
            click: function (ctx) {
                tone(ctx, { wave: 'sine', freq: 880, freqEnd: 640, gain: .09, duration: .09, filterFreq: 3200 });
            }
        },
        dark: {
            hover: function (ctx) {
                tone(ctx, { wave: 'sawtooth', freq: 340, freqEnd: 880, gain: .035, duration: .09, filterType: 'bandpass', filterFreq: 1400, q: 3.2 });
            },
            click: function (ctx) {
                tone(ctx, { wave: 'square', freq: 1250, freqEnd: 340, gain: .06, duration: .16, filterType: 'bandpass', filterFreq: 1600, q: 4.5 });
                noiseBurst(ctx, { gain: .025, duration: .05, filterFreq: 5200, q: 5 });
            }
        }
    };

    function playSound(kind, themeOverride) {
        var ctx = audioContext();
        if (!ctx) return;
        var theme = themeOverride || (root.getAttribute('data-theme') === 'light' ? 'light' : 'dark');
        SOUND[theme][kind](ctx);
    }

    /* Delegate over every real link/button/form-control so new markup gets
       sound for free, no per-element wiring. pointerover covers mouse, pen
       and touch alike; the relatedTarget check stops it re-firing while the
       pointer moves between child nodes (an icon, a span) already inside
       the element that's hovered. #theme-toggle's click sound is handled
       above instead, so it can announce the theme it's switching *to*. */
    var SOUND_TARGETS = 'a[href], button, select, input[type="checkbox"], label.icon-picker-option';

    document.addEventListener('pointerover', function (e) {
        var target = e.target.closest && e.target.closest(SOUND_TARGETS);
        if (!target || target.contains(e.relatedTarget)) return;
        playSound('hover');
    });

    document.addEventListener('click', function (e) {
        var target = e.target.closest && e.target.closest(SOUND_TARGETS);
        if (!target || target.id === 'theme-toggle') return;
        playSound('click');
    });

    /* ── Toast ───────────────────────────────────────────────────────── */
    var toastEl = document.getElementById('toast');
    var toastTimer;

    function toast(message) {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
        }, 2200);
    }

    /* ── Theme toggle ────────────────────────────────────────────────────
       The server already rendered the correct theme from the cookie, so this
       only handles the switch itself. Cookie, not localStorage: the server
       needs to read it to avoid a flash of the wrong theme on next load. */
    var toggle = document.getElementById('theme-toggle');

    if (toggle) {
        toggle.addEventListener('click', function () {
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            toggle.setAttribute('aria-pressed', next === 'light' ? 'true' : 'false');
            toggle.setAttribute('aria-label', 'Switch to ' + (next === 'dark' ? 'light' : 'dark') + ' theme');
            playSound('click', next); // announce the theme just switched to, not the one just left

            document.cookie = 'portal_theme=' + next +
                ';path=/;max-age=31536000;SameSite=Lax' +
                (location.protocol === 'https:' ? ';Secure' : '');
        });
    }

    /* ── Copy link ───────────────────────────────────────────────────── */
    var copyBtn = document.getElementById('copy-link');

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var value = copyBtn.getAttribute('data-copy') || location.href;

            function done() { toast('Link copied'); }
            function fail() { toast('Copy failed — ' + value); }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(done, fail);
                return;
            }

            // http:// fallback (staging before the cert is in place).
            var scratch = document.createElement('textarea');
            scratch.value = value;
            scratch.setAttribute('readonly', '');
            scratch.style.position = 'fixed';
            scratch.style.opacity = '0';
            document.body.appendChild(scratch);
            scratch.select();
            try { document.execCommand('copy'); done(); } catch (e) { fail(); }
            document.body.removeChild(scratch);
        });
    }

    /* ── Tile 1 avatar oscillation ───────────────────────────────────────
       Runs whenever 2+ of the four possible profile images (headshot, logo,
       photo3, photo4) exist — the .avatar--swaps class is added server-side
       precisely so a missing image is simply absent from the rotation.
       Each transition picks a random effect (blur, zoom, wipe — see the
       .fx-* rules in app.css) so the rotation doesn't read as one repeating
       loop. */
    var avatar = document.querySelector('.avatar--swaps');
    var avatarEffects = ['fx-blur', 'fx-zoom', 'fx-wipe'];

    if (avatar && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var frames = avatar.querySelectorAll('.avatar-img');
        if (frames.length > 1) {
            var index = 0;
            setInterval(function () {
                avatar.classList.remove('fx-blur', 'fx-zoom', 'fx-wipe');
                avatar.classList.add(avatarEffects[Math.floor(Math.random() * avatarEffects.length)]);
                frames[index].classList.remove('is-active');
                index = (index + 1) % frames.length;
                frames[index].classList.add('is-active');
            }, 20000);
        }
    }

    /* ── Contact form CAPTCHA (proof-of-work, solved client-side) ──────────
       Only present once the site has taken 5+ submissions today (see
       Captcha::challenge() server-side). Uses the browser's native
       crypto.subtle.digest — no bundled sha256 library, no CDN. Brute-forces
       upward from 0 until salt+n hashes to the server-issued target, yielding
       to the event loop periodically so the tab doesn't lock up. */
    var captchaEl = document.querySelector('[data-captcha]');

    if (captchaEl && window.crypto && window.crypto.subtle) {
        var form = captchaEl.closest('form');
        var numberField = form.querySelector('[name="captcha_number"]');
        var submitBtn = form.querySelector('[data-captcha-submit]');
        var statusEl = captchaEl.querySelector('.captcha-status');
        var salt = captchaEl.getAttribute('data-salt');
        var target = captchaEl.getAttribute('data-challenge');
        var maxNumber = parseInt(captchaEl.getAttribute('data-maxnumber'), 10) || 0;

        function toHex(buffer) {
            var bytes = new Uint8Array(buffer);
            var hex = '';
            for (var i = 0; i < bytes.length; i++) {
                hex += bytes[i].toString(16).padStart(2, '0');
            }
            return hex;
        }

        function sha256Hex(text) {
            var data = new TextEncoder().encode(text);
            return window.crypto.subtle.digest('SHA-256', data).then(toHex);
        }

        function solve(n) {
            if (n > maxNumber) {
                statusEl.textContent = 'Verification failed — please refresh and try again.';
                return;
            }
            var batchEnd = Math.min(n + 400, maxNumber);
            var check = function (i) {
                if (i > batchEnd) {
                    setTimeout(function () { solve(batchEnd + 1); }, 0);
                    return;
                }
                sha256Hex(salt + i).then(function (hash) {
                    if (hash === target) {
                        numberField.value = String(i);
                        submitBtn.removeAttribute('disabled');
                        statusEl.textContent = 'Verified.';
                        return;
                    }
                    check(i + 1);
                });
            };
            check(n);
        }

        solve(0);
    } else if (captchaEl) {
        // No SubtleCrypto (very old browser / insecure context) — don't trap the user behind a dead form.
        var fallbackBtn = captchaEl.closest('form').querySelector('[data-captcha-submit]');
        if (fallbackBtn) fallbackBtn.removeAttribute('disabled');
    }
})();
