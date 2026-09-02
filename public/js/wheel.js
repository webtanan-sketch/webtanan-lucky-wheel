(function () {
    'use strict';

    function label(key, fallback) {
        return window.WTLW_DATA && window.WTLW_DATA.labels && window.WTLW_DATA.labels[key] ? window.WTLW_DATA.labels[key] : fallback;
    }

    function post(action, fields) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', window.WTLW_DATA.nonce);
        Object.keys(fields || {}).forEach(function (key) { body.append(key, fields[key]); });
        return fetch(window.WTLW_DATA.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) {
            return response.json();
        });
    }

    function requestId() {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
        return 'wtlw-' + Date.now() + '-' + Math.random().toString(36).slice(2);
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
            code.innerHTML = '<span>' + label('extraAdded', 'شانس اضافه به حساب شما افزوده شد.') + '</span>';
        } else {
            code.innerHTML = '<span>' + label('walletAdded', 'اعتبار جایزه به کیف پول شما افزوده شد.') + '</span>';
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
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
    }

    function initApp(app) {
        var registerForm = app.querySelector('.wtlw-register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var message = registerForm.querySelector('.wtlw-form-message');
                var button = registerForm.querySelector('button[type="submit"]');
                button.disabled = true;
                message.classList.remove('is-error');
                message.textContent = label('saving', 'در حال ثبت اطلاعات...');
                var fields = {};
                new FormData(registerForm).forEach(function (value, key) { fields[key] = value; });
                post('wtlw_register', fields).then(function (payload) {
                    if (!payload.success) { throw new Error(payload.data && payload.data.message ? payload.data.message : label('registrationFailed', 'ثبت‌نام انجام نشد.')); }
                    message.textContent = payload.data.message;
                    window.setTimeout(function () { window.location.reload(); }, 450);
                }).catch(function (error) {
                    message.textContent = error.message;
                    message.classList.add('is-error');
                    button.disabled = false;
                });
            });
            return;
        }

        var game = app.querySelector('.wtlw-game');
        if (!game) { return; }
        var button = game.querySelector('.wtlw-spin-button');
        var wheel = game.querySelector('.wtlw-wheel');
        var count = game.querySelector('.wtlw-attempt-count');
        var message = game.querySelector('.wtlw-spin-message');
        var rotation = 0;
        button.addEventListener('click', function () {
            if (button.disabled) { return; }
            button.disabled = true;
            message.classList.remove('is-error');
            message.textContent = label('spinning', 'گردونه در حال چرخش است...');
            post('wtlw_spin', { request_id: requestId() }).then(function (payload) {
                if (!payload.success) { throw new Error(payload.data && payload.data.message ? payload.data.message : label('spinFailed', 'چرخش گردونه انجام نشد.')); }
                var result = payload.data;
                rotation += Number(result.angle || 180);
                wheel.style.transform = 'rotate(' + rotation + 'deg)';
                count.textContent = result.attempts_remaining;
                wheel.addEventListener('transitionend', function () {
                    showModal(app, result);
                    message.textContent = '';
                    button.disabled = Number(result.attempts_remaining) < 1;
                }, { once: true });
            }).catch(function (error) {
                message.textContent = error.message;
                message.classList.add('is-error');
                button.disabled = false;
            });
        });
        app.querySelectorAll('.wtlw-modal-close, .wtlw-modal-ok, .wtlw-modal-backdrop').forEach(function (element) {
            element.addEventListener('click', function () { closeModal(app); });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wtlw-app').forEach(initApp);
    });
}());
