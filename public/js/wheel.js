(function () {
    'use strict';

    var storageKey = 'wtlw_guest_session_v1';
    var fullTurn = 360;
    var settleTurns = 3;
    var instantSpeed = 0.72; // degrees per millisecond while waiting for the server.

    function label(key, fallback) {
        return window.WTLW_DATA && window.WTLW_DATA.labels && window.WTLW_DATA.labels[key]
            ? window.WTLW_DATA.labels[key]
            : fallback;
    }

    function post(action, fields) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', window.WTLW_DATA.nonce);
        Object.keys(fields || {}).forEach(function (key) {
            body.append(key, fields[key]);
        });

        return fetch(window.WTLW_DATA.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (response) {
            return response.json();
        });
    }

    function requestId() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        return 'wtlw-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    }

    function normalizeAngle(value) {
        var normalized = Number(value || 0) % fullTurn;
        return normalized < 0 ? normalized + fullTurn : normalized;
    }

    function nextRotation(currentRotation, serverAngle) {
        var current = normalizeAngle(currentRotation);
        var target = normalizeAngle(serverAngle);
        var delta = (target - current + fullTurn) % fullTurn;
        return currentRotation + (settleTurns * fullTurn) + delta;
    }

    function defaultAttempts(app) {
        var fromApp = Number(app.getAttribute('data-default-attempts'));
        if (Number.isFinite(fromApp)) {
            return Math.max(0, fromApp);
        }
        return Math.max(0, Number(window.WTLW_DATA && window.WTLW_DATA.defaultAttempts || 0));
    }

    function formatAttempts(value) {
        var number = Math.max(0, Number(value || 0));
        try {
            return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }).format(number);
        } catch (e) {
            return String(number);
        }
    }

    function saveSession(session) {
        var raw = JSON.stringify(session);
        try {
            window.localStorage.setItem(storageKey, raw);
            return;
        } catch (e) {}
        try {
            window.sessionStorage.setItem(storageKey, raw);
        } catch (ignore) {}
    }

    function loadSession() {
        var raw = null;
        try {
            raw = window.localStorage.getItem(storageKey);
        } catch (e) {}

        if (!raw) {
            try {
                raw = window.sessionStorage.getItem(storageKey);
                if (raw) {
                    window.localStorage.setItem(storageKey, raw);
                }
            } catch (ignore) {}
        }

        try {
            return raw ? JSON.parse(raw) : null;
        } catch (e2) {
            return null;
        }
    }

    function clearSession() {
        try {
            window.localStorage.removeItem(storageKey);
        } catch (e) {}
        try {
            window.sessionStorage.removeItem(storageKey);
        } catch (ignore) {}
    }

    function getAttempts(app) {
        var current = Number(app.getAttribute('data-attempts-current'));
        if (Number.isFinite(current)) {
            return Math.max(0, current);
        }

        current = Number(app.getAttribute('data-initial-remaining'));
        return Number.isFinite(current) ? Math.max(0, current) : 0;
    }

    function setBusy(app, busy) {
        app.__wtlwBusy = !!busy;
        var game = app.querySelector('.wtlw-game');
        var button = game ? game.querySelector('.wtlw-spin-button') : null;
        var remaining = getAttempts(app);

        if (game) {
            game.classList.toggle('is-preparing', !!busy);
        }
        if (button) {
            button.disabled = !!busy || remaining < 1;
        }
    }

    function setAttempts(app, value) {
        var remaining = Math.max(0, Number(value || 0));
        var game = app.querySelector('.wtlw-game');
        var count = game ? game.querySelector('.wtlw-attempt-count') : null;
        var button = game ? game.querySelector('.wtlw-spin-button') : null;

        app.setAttribute('data-attempts-current', String(remaining));
        app.setAttribute('data-has-attempts', remaining > 0 ? '1' : '0');

        if (count) {
            count.textContent = formatAttempts(remaining);
        }
        if (button) {
            button.disabled = !!app.__wtlwBusy || remaining < 1;
        }
        return remaining;
    }

    function prepareLabelFlips(app, finalRotation) {
        app.querySelectorAll('.wtlw-wheel-labels [data-label-angle]').forEach(function (element) {
            var base = Number(element.getAttribute('data-label-angle') || 0);
            var screenAngle = normalizeAngle(base + finalRotation);
            var flip = screenAngle > 90 && screenAngle < 270 ? 180 : 0;
            element.style.setProperty('--wtlw-label-flip', flip + 'deg');
        });
    }

    function startImmediateSpin(wheel, startRotation) {
        var state = {
            rotation: Number(startRotation || 0),
            raf: 0,
            startedAt: performance.now()
        };

        wheel.style.transition = 'none';

        function frame(now) {
            state.rotation = Number(startRotation || 0) + ((now - state.startedAt) * instantSpeed);
            wheel.style.transform = 'rotate(' + state.rotation + 'deg)';
            state.raf = window.requestAnimationFrame(frame);
        }

        // Make the click feel immediate even before the first animation frame.
        state.rotation += 2;
        wheel.style.transform = 'rotate(' + state.rotation + 'deg)';
        state.raf = window.requestAnimationFrame(frame);

        return {
            stop: function () {
                if (state.raf) {
                    window.cancelAnimationFrame(state.raf);
                    state.raf = 0;
                }
                return state.rotation;
            }
        };
    }

    function settleWheel(wheel, currentRotation, serverAngle, onEnd) {
        var finalRotation = nextRotation(currentRotation, serverAngle);
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var duration = reducedMotion ? 900 : 3200;
        var finished = false;

        function done(event) {
            if (finished) {
                return;
            }
            if (event && (event.target !== wheel || event.propertyName !== 'transform')) {
                return;
            }
            finished = true;
            wheel.removeEventListener('transitionend', done);
            if (typeof onEnd === 'function') {
                onEnd();
            }
        }

        wheel.style.transition = 'none';
        wheel.style.transform = 'rotate(' + currentRotation + 'deg)';
        void wheel.offsetWidth;
        wheel.addEventListener('transitionend', done);

        window.requestAnimationFrame(function () {
            wheel.style.transition = 'transform ' + duration + 'ms cubic-bezier(.12,.72,.12,1)';
            wheel.style.transform = 'rotate(' + finalRotation + 'deg)';
        });

        // Defensive fallback for themes/plugins that interfere with transitionend.
        window.setTimeout(function () {
            done();
        }, duration + 250);

        return finalRotation;
    }

    function showModal(app, result) {
        var modal = app.querySelector('.wtlw-modal');
        var name = app.querySelector('.wtlw-result-name');
        var code = app.querySelector('.wtlw-result-code');
        var type = result.reward_type || '';

        name.textContent = result.reward_name || '';

        if (result.coupon_code) {
            code.innerHTML = '<span>' + label('discountCode', 'کد تخفیف شما') + '</span><code dir="ltr">' + result.coupon_code + '</code>';
        } else if ('nothing' === type) {
            code.innerHTML = '<span>' + label('noLuck', 'این بار برنده نشدید.') + '</span>';
        } else if ('extra_attempts' === type) {
            code.innerHTML = '<span>' + label('extraAdded', 'شانس اضافه برای شما ثبت شد.') + '</span>';
        } else {
            code.innerHTML = '<span>' + label('walletAdded', 'اعتبار جایزه برای شما ثبت شد.') + '</span>';
        }

        var confetti = app.querySelector('.wtlw-confetti');
        confetti.innerHTML = '';
        for (var i = 0; i < 28; i += 1) {
            var piece = document.createElement('i');
            piece.style.setProperty('--x', (Math.random() * 180 - 90) + 'px');
            piece.style.setProperty('--y', (Math.random() * 180 - 90) + 'px');
            piece.style.setProperty('--delay', (Math.random() * 0.25) + 's');
            piece.style.setProperty('--hue', Math.round(Math.random() * 360));
            confetti.appendChild(piece);
        }

        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-visible');
    }

    function closeModal(app) {
        var modal = app.querySelector('.wtlw-modal');
        if (!modal) {
            return;
        }

        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');

        if ('0' === app.getAttribute('data-has-attempts')) {
            var shell = app.closest('.wtlw-popup-shell');
            if (shell) {
                closePopup(shell);
                shell.hidden = true;
            }
        }
    }

    function updateGuestCache(session, status) {
        if (!session || session.member) {
            return;
        }
        session.name = status.name || session.name || '';
        session.attempts_remaining = Math.max(0, Number(status.attempts_remaining || 0));
        session.cached_at = Date.now();
        saveSession(session);
    }

    function activateGame(app, session, status) {
        var form = app.querySelector('.wtlw-entry-form');
        var game = app.querySelector('.wtlw-game');

        if (form) {
            form.classList.add('wtlw-is-hidden');
        }
        if (game) {
            game.classList.remove('wtlw-is-hidden');
        }

        var name = game ? game.querySelector('.wtlw-participant-name') : null;
        if (name) {
            name.textContent = status.name || '';
        }

        app.__wtlwSession = session;
        setAttempts(app, status.attempts_remaining);
        setBusy(app, false);
        updateGuestCache(session, status);
    }

    function hidePopupIfNoAttempts(app) {
        if ('0' !== app.getAttribute('data-has-attempts')) {
            return;
        }
        var shell = app.closest('.wtlw-popup-shell');
        if (shell) {
            closePopup(shell);
            shell.hidden = true;
        }
    }

    function initApp(app) {
        var isMember = '1' === app.getAttribute('data-member');
        var registerForm = app.querySelector('.wtlw-entry-form');
        var game = app.querySelector('.wtlw-game');
        var spinButton = game ? game.querySelector('.wtlw-spin-button') : null;
        var wheel = game ? game.querySelector('.wtlw-wheel') : null;
        var message = game ? game.querySelector('.wtlw-spin-message') : null;
        var rotation = 0;

        app.__wtlwBusy = false;

        if (isMember) {
            app.__wtlwSession = { member: 1 };
            setAttempts(app, app.getAttribute('data-initial-remaining'));
        } else if (registerForm) {
            registerForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var formMessage = registerForm.querySelector('.wtlw-form-message');
                var button = registerForm.querySelector('button[type="submit"]');
                var fields = {};
                new FormData(registerForm).forEach(function (value, key) {
                    fields[key] = value;
                });

                var enteredName = String(fields.name || '').trim();
                var enteredPhone = String(fields.phone || '').trim();

                formMessage.classList.remove('is-error');
                if (!enteredName || !enteredPhone) {
                    formMessage.textContent = !enteredName ? 'نام و نام خانوادگی را وارد کنید.' : 'شماره موبایل را وارد کنید.';
                    formMessage.classList.add('is-error');
                    return;
                }

                button.disabled = true;
                formMessage.textContent = '';

                // Optimistic transition: move to the wheel immediately while the secure server request runs.
                registerForm.classList.add('wtlw-is-hidden');
                if (game) {
                    game.classList.remove('wtlw-is-hidden');
                }
                var participantName = game ? game.querySelector('.wtlw-participant-name') : null;
                if (participantName) {
                    participantName.textContent = enteredName;
                }
                setBusy(app, true);
                setAttempts(app, defaultAttempts(app));
                if (message) {
                    message.classList.remove('is-error');
                    message.textContent = label('entering', 'در حال آماده‌سازی شانس شما...');
                }

                post('wtlw_register', fields).then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.data && payload.data.message ? payload.data.message : label('entryFailed', 'ثبت اطلاعات انجام نشد.'));
                    }

                    var session = {
                        participant_id: payload.data.participant_id,
                        participant_token: payload.data.participant_token
                    };

                    activateGame(app, session, payload.data);
                    if (message) {
                        message.textContent = '';
                    }
                    hidePopupIfNoAttempts(app);
                }).catch(function (error) {
                    app.__wtlwSession = null;
                    setBusy(app, false);
                    setAttempts(app, 0);

                    if (game) {
                        game.classList.add('wtlw-is-hidden');
                    }
                    registerForm.classList.remove('wtlw-is-hidden');
                    button.disabled = false;

                    if (message) {
                        message.textContent = '';
                    }
                    formMessage.textContent = error.message;
                    formMessage.classList.add('is-error');
                });
            });
        }

        if (spinButton && wheel) {
            spinButton.addEventListener('click', function () {
                var session = app.__wtlwSession;
                var before = getAttempts(app);

                if (!session || app.__wtlwBusy || before < 1) {
                    return;
                }

                setBusy(app, true);
                setAttempts(app, before - 1);

                if (message) {
                    message.classList.remove('is-error');
                    message.textContent = label('spinning', 'گردونه در حال چرخش است...');
                }

                // Start motion on the same click; never wait for AJAX before giving visual feedback.
                var immediateMotion = startImmediateSpin(wheel, rotation);
                var fields = { request_id: requestId() };

                if (session.member) {
                    fields.member = '1';
                } else {
                    fields.participant_id = session.participant_id;
                    fields.participant_token = session.participant_token;
                }

                post('wtlw_spin', fields).then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.data && payload.data.message ? payload.data.message : label('spinFailed', 'چرخش گردونه انجام نشد.'));
                    }

                    var result = payload.data;
                    var currentRotation = immediateMotion.stop();
                    var targetAngle = result.target_angle !== undefined ? result.target_angle : result.angle;
                    var finalRotation = nextRotation(currentRotation, targetAngle);

                    // Calculate the 180-degree readability correction before the wheel finishes.
                    // It is therefore already correct at the exact stop frame, without a visible delay.
                    prepareLabelFlips(app, finalRotation);
                    setAttempts(app, result.attempts_remaining);
                    updateGuestCache(session, result);

                    rotation = settleWheel(wheel, currentRotation, targetAngle, function () {
                        setBusy(app, false);
                        if (message) {
                            message.textContent = '';
                        }
                        showModal(app, result);
                    });
                }).catch(function (error) {
                    rotation = immediateMotion.stop();
                    wheel.style.transition = 'transform 280ms ease-out';
                    wheel.style.transform = 'rotate(' + rotation + 'deg)';

                    setAttempts(app, before);
                    setBusy(app, false);

                    if (!isMember && String(error.message).indexOf('نشست') !== -1) {
                        clearSession();
                        app.__wtlwSession = null;
                        if (game) {
                            game.classList.add('wtlw-is-hidden');
                        }
                        if (registerForm) {
                            registerForm.classList.remove('wtlw-is-hidden');
                        }
                        app.removeAttribute('data-has-attempts');
                    }

                    if (message) {
                        message.textContent = error.message;
                        message.classList.add('is-error');
                    }
                });
            });
        }

        app.querySelectorAll('.wtlw-modal-close, .wtlw-modal-ok, .wtlw-modal-backdrop').forEach(function (element) {
            element.addEventListener('click', function () {
                closeModal(app);
            });
        });

        if (isMember) {
            app.__wtlwReady = Promise.resolve();
            return;
        }

        var saved = loadSession();
        if (saved && saved.participant_id && saved.participant_token) {
            if (saved.attempts_remaining !== undefined) {
                activateGame(app, saved, {
                    name: saved.name || '',
                    attempts_remaining: saved.attempts_remaining
                });
                setBusy(app, true);
            }

            app.__wtlwReady = post('wtlw_guest_status', saved).then(function (payload) {
                if (!payload.success) {
                    throw new Error('invalid');
                }
                activateGame(app, saved, payload.data);
                hidePopupIfNoAttempts(app);
            }).catch(function () {
                clearSession();
                app.__wtlwSession = null;
                app.removeAttribute('data-has-attempts');
                setBusy(app, false);
                if (game) {
                    game.classList.add('wtlw-is-hidden');
                }
                if (registerForm) {
                    registerForm.classList.remove('wtlw-is-hidden');
                }
            });
        } else {
            app.__wtlwReady = Promise.resolve();
        }
    }

    function openPopup(shell) {
        var popup = shell.querySelector('.wtlw-popup');
        var app = shell.querySelector('.wtlw-app');

        if (!popup || shell.hidden || (app && '0' === app.getAttribute('data-has-attempts'))) {
            return;
        }

        popup.classList.add('is-visible');
        popup.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('wtlw-popup-open');
    }

    function closePopup(shell) {
        var popup = shell.querySelector('.wtlw-popup');
        if (!popup) {
            return;
        }

        popup.classList.remove('is-visible');
        popup.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.wtlw-popup.is-visible')) {
            document.documentElement.classList.remove('wtlw-popup-open');
        }
    }

    function enablePopupShell(shell) {
        var trigger = shell.querySelector('.wtlw-popup-trigger');
        if (trigger) {
            trigger.disabled = false;
            if (!trigger.__wtlwBound) {
                trigger.__wtlwBound = true;
                trigger.addEventListener('click', function () {
                    openPopup(shell);
                });
            }
        }

        if ('1' === shell.getAttribute('data-auto-open') && !shell.__wtlwAutoOpenScheduled) {
            shell.__wtlwAutoOpenScheduled = true;
            window.setTimeout(function () {
                openPopup(shell);
            }, Math.max(0, Number(shell.getAttribute('data-delay') || 800)));
        }
    }

    function initPopup(shell) {
        var trigger = shell.querySelector('.wtlw-popup-trigger');
        var app = shell.querySelector('.wtlw-app');

        shell.querySelectorAll('[data-wtlw-popup-close]').forEach(function (element) {
            element.addEventListener('click', function () {
                closePopup(shell);
            });
        });

        // New visitors do not need a status request before seeing the entry form.
        var session = loadSession();
        if (!session || !session.participant_id || !session.participant_token) {
            enablePopupShell(shell);
            return;
        }

        // A positive cached balance lets the UI become interactive immediately;
        // the secure server status still reconciles in the background.
        if (app && '1' === app.getAttribute('data-has-attempts')) {
            enablePopupShell(shell);
        } else if (trigger) {
            trigger.disabled = true;
        }

        Promise.resolve(app && app.__wtlwReady ? app.__wtlwReady : null).then(function () {
            if (app && '0' === app.getAttribute('data-has-attempts')) {
                shell.hidden = true;
                return;
            }
            enablePopupShell(shell);
        });
    }

    document.addEventListener('keydown', function (event) {
        if ('Escape' === event.key) {
            document.querySelectorAll('.wtlw-popup-shell').forEach(closePopup);
            document.querySelectorAll('.wtlw-app').forEach(closeModal);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wtlw-app').forEach(initApp);
        document.querySelectorAll('.wtlw-popup-shell').forEach(initPopup);
    });
}());
