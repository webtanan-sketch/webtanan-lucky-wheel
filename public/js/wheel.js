(function () {
    'use strict';

    var storageKey = 'wtlw_guest_session_v1';

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

    function saveSession(session) {
        try { window.sessionStorage.setItem(storageKey, JSON.stringify(session)); } catch (e) {}
    }

    function loadSession() {
        try {
            var raw = window.sessionStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function clearSession() {
        try { window.sessionStorage.removeItem(storageKey); } catch (e) {}
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
            code.innerHTML = '<span>' + label('walletAdded', 'اعتبار جایزه برای شماره موبایل شما ثبت شد.') + '</span>';
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
    }

    function activateGame(app, session, status) {
        var form = app.querySelector('.wtlw-entry-form');
        var game = app.querySelector('.wtlw-game');
        form.classList.add('wtlw-is-hidden');
        game.classList.remove('wtlw-is-hidden');
        game.querySelector('.wtlw-attempt-count').textContent = status.attempts_remaining;
        game.querySelector('.wtlw-participant-name').textContent = status.name || '';
        game.querySelector('.wtlw-spin-button').disabled = Number(status.attempts_remaining) < 1;
        app.__wtlwSession = session;
    }

    function initApp(app) {
        var registerForm = app.querySelector('.wtlw-entry-form');
        var game = app.querySelector('.wtlw-game');
        var spinButton = game.querySelector('.wtlw-spin-button');
        var wheel = game.querySelector('.wtlw-wheel');
        var count = game.querySelector('.wtlw-attempt-count');
        var message = game.querySelector('.wtlw-spin-message');
        var rotation = 0;

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

        spinButton.addEventListener('click', function () {
            var session = app.__wtlwSession;
            if (!session || spinButton.disabled) { return; }
            spinButton.disabled = true;
            message.classList.remove('is-error');
            message.textContent = label('spinning', 'گردونه در حال چرخش است...');
            post('wtlw_spin', { participant_id: session.participant_id, participant_token: session.participant_token, request_id: requestId() }).then(function (payload) {
                if (!payload.success) { throw new Error(payload.data && payload.data.message ? payload.data.message : label('spinFailed', 'چرخش گردونه انجام نشد.')); }
                var result = payload.data;
                rotation += Number(result.angle || 180);
                wheel.style.transform = 'rotate(' + rotation + 'deg)';
                count.textContent = result.attempts_remaining;
                wheel.addEventListener('transitionend', function () {
                    showModal(app, result);
                    message.textContent = '';
                    spinButton.disabled = Number(result.attempts_remaining) < 1;
                }, { once: true });
            }).catch(function (error) {
                if (String(error.message).indexOf('نشست') !== -1) {
                    clearSession();
                    app.__wtlwSession = null;
                    game.classList.add('wtlw-is-hidden');
                    registerForm.classList.remove('wtlw-is-hidden');
                }
                message.textContent = error.message;
                message.classList.add('is-error');
                spinButton.disabled = false;
            });
        });

        app.querySelectorAll('.wtlw-modal-close, .wtlw-modal-ok, .wtlw-modal-backdrop').forEach(function (element) {
            element.addEventListener('click', function () { closeModal(app); });
        });

        var saved = loadSession();
        if (saved && saved.participant_id && saved.participant_token) {
            post('wtlw_guest_status', saved).then(function (payload) {
                if (!payload.success) { throw new Error('invalid'); }
                activateGame(app, saved, payload.data);
            }).catch(function () { clearSession(); });
        }
    }

    function openPopup(shell) {
        var popup = shell.querySelector('.wtlw-popup');
        if (!popup) { return; }
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
        if (trigger) { trigger.addEventListener('click', function () { openPopup(shell); }); }
        shell.querySelectorAll('[data-wtlw-popup-close]').forEach(function (element) {
            element.addEventListener('click', function () { closePopup(shell); });
        });
        if ('1' === shell.getAttribute('data-auto-open')) {
            window.setTimeout(function () { openPopup(shell); }, Math.max(0, Number(shell.getAttribute('data-delay') || 800)));
        }
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
