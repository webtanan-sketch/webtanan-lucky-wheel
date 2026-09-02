(function () {
    'use strict';

    var storageKey = 'wtlw_guest_session_v1';
    var fullTurn = 360;
    var spinTurns = 5;

    function label(key, fallback) {
        return window.WTLW_DATA && window.WTLW_DATA.labels && window.WTLW_DATA.labels[key] ? window.WTLW_DATA.labels[key] : fallback;
    }

    function post(action, fields) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', window.WTLW_DATA.nonce);
        Object.keys(fields || {}).forEach(function (key) { body.append(key, fields[key]); });
        return fetch(window.WTLW_DATA.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) { return response.json(); });
    }

    function requestId() {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
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
        return currentRotation + (spinTurns * fullTurn) + delta;
    }

    function saveSession(session) {
        var raw = JSON.stringify(session);
        try { window.localStorage.setItem(storageKey, raw); return; } catch (e) {}
        try { window.sessionStorage.setItem(storageKey, raw); } catch (ignore) {}
    }

    function loadSession() {
        var raw = null;
        try { raw = window.localStorage.getItem(storageKey); } catch (e) {}
        if (!raw) {
            try {
                raw = window.sessionStorage.getItem(storageKey);
                if (raw) { window.localStorage.setItem(storageKey, raw); }
            } catch (ignore) {}
        }
        try { return raw ? JSON.parse(raw) : null; } catch (e2) { return null; }
    }

    function clearSession() {
        try { window.localStorage.removeItem(storageKey); } catch (e) {}
        try { window.sessionStorage.removeItem(storageKey); } catch (ignore) {}
    }

    function setAttempts(app, value) {
        var remaining = Math.max(0, Number(value || 0));
        var game = app.querySelector('.wtlw-game');
        var count = game ? game.querySelector('.wtlw-attempt-count') : null;
        var button = game ? game.querySelector('.wtlw-spin-button') : null;
        app.setAttribute('data-has-attempts', remaining > 0 ? '1' : '0');
        if (count) { count.textContent = remaining; }
        if (button) { button.disabled = remaining < 1; }
        return remaining;
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
        if (!modal) { return; }
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

    function activateGame(app, session, status) {
        var form = app.querySelector('.wtlw-entry-form');
        var game = app.querySelector('.wtlw-game');
        if (form) { form.classList.add('wtlw-is-hidden'); }
        if (game) { game.classList.remove('wtlw-is-hidden'); }
        var name = game ? game.querySelector('.wtlw-participant-name') : null;
        if (name) { name.textContent = status.name || ''; }
        setAttempts(app, status.attempts_remaining);
        app.__wtlwSession = session;
    }

    function initApp(app) {
        var isMember = '1' === app.getAttribute('data-member');
        var registerForm = app.querySelector('.wtlw-entry-form');
        var game = app.querySelector('.wtlw-game');
        var spinButton = game ? game.querySelector('.wtlw-spin-button') : null;
        var wheel = game ? game.querySelector('.wtlw-wheel') : null;
        var message = game ? game.querySelector('.wtlw-spin-message') : null;
        var rotation = 0;

        if (isMember) {
            app.__wtlwSession = { member: 1 };
            setAttempts(app, app.getAttribute('data-initial-remaining'));
        } else if (registerForm) {
            registerForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var formMessage = registerForm.querySelector('.wtlw-form-message');
                var button = registerForm.querySelector('button[type="submit"]');
                button.disabled = true;
                formMessage.classList.remove('is-error');
                formMessage.textContent = label('entering', 'در حال ثبت اطلاعات...');
                var fields = {};
                new FormData(registerForm).forEach(function (value, key) { fields[key] = value; });
                post('wtlw_register', fields).then(function (payload) {
                    if (!payload.success) { throw new Error(payload.data && payload.data.message ? payload.data.message : label('entryFailed', 'ثبت اطلاعات انجام نشد.')); }
                    var session = { participant_id: payload.data.participant_id, participant_token: payload.data.participant_token };
                    saveSession(session);
                    formMessage.textContent = payload.data.message || '';
                    activateGame(app, session, payload.data);
                }).catch(function (error) {
                    formMessage.textContent = error.message;
                    formMessage.classList.add('is-error');
                    button.disabled = false;
                });
            });
        }

        if (spinButton && wheel) {
            spinButton.addEventListener('click', function () {
                var session = app.__wtlwSession;
                if (!session || spinButton.disabled) { return; }
                spinButton.disabled = true;
                message.classList.remove('is-error');
                message.textContent = label('spinning', 'گردونه در حال چرخش است...');
                var fields = { request_id: requestId() };
                if (session.member) {
                    fields.member = '1';
                } else {
                    fields.participant_id = session.participant_id;
                    fields.participant_token = session.participant_token;
                }
                post('wtlw_spin', fields).then(function (payload) {
                    if (!payload.success) { throw new Error(payload.data && payload.data.message ? payload.data.message : label('spinFailed', 'چرخش گردونه انجام نشد.')); }
                    var result = payload.data;
                    rotation = nextRotation(rotation, result.target_angle !== undefined ? result.target_angle : result.angle);
                    wheel.style.transform = 'rotate(' + rotation + 'deg)';
                    setAttempts(app, result.attempts_remaining);
                    var onWheelEnd = function (event) {
                        if (event.target !== wheel || 'transform' !== event.propertyName) { return; }
                        wheel.removeEventListener('transitionend', onWheelEnd);
                        showModal(app, result);
                        message.textContent = '';
                    };
                    wheel.addEventListener('transitionend', onWheelEnd);
                }).catch(function (error) {
                    if (!isMember && String(error.message).indexOf('نشست') !== -1) {
                        clearSession();
                        app.__wtlwSession = null;
                        if (game) { game.classList.add('wtlw-is-hidden'); }
                        if (registerForm) { registerForm.classList.remove('wtlw-is-hidden'); }
                        app.removeAttribute('data-has-attempts');
                    }
                    message.textContent = error.message;
                    message.classList.add('is-error');
                    spinButton.disabled = false;
                });
            });
        }

        app.querySelectorAll('.wtlw-modal-close, .wtlw-modal-ok, .wtlw-modal-backdrop').forEach(function (element) {
            element.addEventListener('click', function () { closeModal(app); });
        });

        if (isMember) {
            app.__wtlwReady = Promise.resolve();
            return;
        }

        var saved = loadSession();
        if (saved && saved.participant_id && saved.participant_token) {
            app.__wtlwReady = post('wtlw_guest_status', saved).then(function (payload) {
                if (!payload.success) { throw new Error('invalid'); }
                activateGame(app, saved, payload.data);
            }).catch(function () {
                clearSession();
                app.__wtlwSession = null;
                app.removeAttribute('data-has-attempts');
            });
        } else {
            app.__wtlwReady = Promise.resolve();
        }
    }

    function openPopup(shell) {
        var popup = shell.querySelector('.wtlw-popup');
        var app = shell.querySelector('.wtlw-app');
        if (!popup || shell.hidden || (app && '0' === app.getAttribute('data-has-attempts'))) { return; }
        popup.classList.add('is-visible');
        popup.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('wtlw-popup-open');
    }

    function closePopup(shell) {
        var popup = shell.querySelector('.wtlw-popup');
        if (!popup) { return; }
        popup.classList.remove('is-visible');
        popup.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.wtlw-popup.is-visible')) { document.documentElement.classList.remove('wtlw-popup-open'); }
    }

    function initPopup(shell) {
        var trigger = shell.querySelector('.wtlw-popup-trigger');
        var app = shell.querySelector('.wtlw-app');
        if (trigger) { trigger.disabled = true; }
        shell.querySelectorAll('[data-wtlw-popup-close]').forEach(function (element) {
            element.addEventListener('click', function () { closePopup(shell); });
        });
        Promise.resolve(app && app.__wtlwReady ? app.__wtlwReady : null).then(function () {
            if (app && '0' === app.getAttribute('data-has-attempts')) {
                shell.hidden = true;
                return;
            }
            if (trigger) {
                trigger.disabled = false;
                trigger.addEventListener('click', function () { openPopup(shell); });
            }
            if ('1' === shell.getAttribute('data-auto-open')) {
                window.setTimeout(function () { openPopup(shell); }, Math.max(0, Number(shell.getAttribute('data-delay') || 800)));
            }
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
