(function () {
    'use strict';

    var rootSelector = '[data-slide-captcha]';

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        callback();
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function round(value) {
        return Math.round(value * 100) / 100;
    }

    function json(response) {
        return response.json().then(function (data) {
            if (! response.ok) {
                throw data;
            }

            return data;
        });
    }

    function init(root) {
        if (root.__slideCaptchaInitialized) {
            return;
        }

        root.__slideCaptchaInitialized = true;

        var stage = root.querySelector('[data-slide-captcha-stage]');
        var background = root.querySelector('[data-slide-captcha-background]');
        var piece = root.querySelector('[data-slide-captcha-piece]');
        var reload = root.querySelector('[data-slide-captcha-reload]');
        var rotationControls = root.querySelector('[data-slide-captcha-rotation-controls]');
        var rotateLeft = root.querySelector('[data-slide-captcha-rotate-left]');
        var rotateRight = root.querySelector('[data-slide-captcha-rotate-right]');
        var checkButton = root.querySelector('[data-slide-captcha-check]');
        var challengeInput = root.querySelector('[data-slide-captcha-challenge-id]');
        var tokenInput = root.querySelector('[data-slide-captcha-token]');
        var verifiedInput = root.querySelector('[data-slide-captcha-verified]');

        if (! stage || ! background || ! piece) {
            return;
        }

        var state = {
            challengeId: null,
            dragging: false,
            left: 0,
            top: 0,
            grabX: 0,
            grabY: 0,
            pieceWidth: 0,
            pieceHeight: 0,
            scaleX: 1,
            scaleY: 1,
            rotation: 0,
            rotationEnabled: false,
            rotationStep: 15,
            startedAt: 0,
            movement: []
        };

        function newUrl() {
            return root.getAttribute('data-slide-captcha-new-url') || '/slide-captcha/new';
        }

        function verifyUrl() {
            return root.getAttribute('data-slide-captcha-verify-url') || '/slide-captcha/verify';
        }

        function dispatch(name, detail) {
            root.dispatchEvent(new CustomEvent(name, {
                bubbles: true,
                detail: detail || {}
            }));
        }

        function setState(name) {
            root.classList.remove('is-loading', 'is-success', 'is-error', 'is-disabled', 'is-ready');

            if (name) {
                root.classList.add('is-' + name);
            }
        }

        function clearValidation() {
            if (tokenInput) {
                tokenInput.value = '';
            }

            if (verifiedInput) {
                verifiedInput.value = '0';
            }
        }

        function setPiecePosition(left, top) {
            state.left = left;
            state.top = top;
            piece.style.left = left + 'px';
            piece.style.top = top + 'px';
            piece.style.transform = 'rotate(' + state.rotation + 'deg)';
        }

        function normalizeRotation(value) {
            return ((value % 360) + 360) % 360;
        }

        function setRotation(value) {
            state.rotation = normalizeRotation(value);
            setPiecePosition(state.left, state.top);
        }

        function updateScale() {
            var rect = stage.getBoundingClientRect();
            var configuredWidth = parseFloat(root.getAttribute('data-slide-captcha-width')) || 320;
            var configuredHeight = parseFloat(root.getAttribute('data-slide-captcha-height')) || 180;
            var naturalWidth = background.naturalWidth || configuredWidth;
            var naturalHeight = background.naturalHeight || configuredHeight;

            state.scaleX = naturalWidth / Math.max(rect.width, 1);
            state.scaleY = naturalHeight / Math.max(rect.height, 1);
            state.pieceWidth = (piece.naturalWidth || 50) / state.scaleX;
            state.pieceHeight = (piece.naturalHeight || 50) / state.scaleY;

            piece.style.width = state.pieceWidth + 'px';
            piece.style.height = state.pieceHeight + 'px';

            setPiecePosition(
                clamp(state.left, 0, Math.max(0, rect.width - state.pieceWidth)),
                clamp(state.top, 0, Math.max(0, rect.height - state.pieceHeight))
            );
        }

        function recordMovement() {
            state.movement.push({
                x: round(state.left * state.scaleX),
                y: round(state.top * state.scaleY),
                r: round(state.rotation),
                t: Date.now() - state.startedAt
            });
        }

        function pointFromEvent(event) {
            return {
                x: event.clientX,
                y: event.clientY
            };
        }

        function verifyCurrentPosition() {
            setState('loading');

            fetch(verifyUrl(), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify({
                    challenge_id: state.challengeId,
                    x: round(state.left * state.scaleX),
                    y: round(state.top * state.scaleY),
                    rotation: round(state.rotation),
                    movement: state.movement
                })
            })
                .then(json)
                .then(function (data) {
                    if (data.success) {
                        if (tokenInput) {
                            tokenInput.value = data.verification_token || '';
                        }

                        if (verifiedInput) {
                            verifiedInput.value = '1';
                        }

                        setState('success');
                        dispatch('slide-captcha:success', data);
                        return;
                    }

                    clearValidation();
                    setState('error');
                    dispatch('slide-captcha:error', data);
                    window.setTimeout(loadChallenge, 500);
                })
                .catch(function (error) {
                    clearValidation();
                    setState('error');
                    dispatch('slide-captcha:error', error);
                    window.setTimeout(loadChallenge, 500);
                });
        }

        function onPointerMove(event) {
            if (! state.dragging) {
                return;
            }

            event.preventDefault();
            updateScale();

            var point = pointFromEvent(event);
            var rect = stage.getBoundingClientRect();
            var nextLeft = point.x - rect.left - state.grabX;
            var nextTop = point.y - rect.top - state.grabY;

            setPiecePosition(
                clamp(nextLeft, 0, Math.max(0, rect.width - state.pieceWidth)),
                clamp(nextTop, 0, Math.max(0, rect.height - state.pieceHeight))
            );
            recordMovement();
        }

        function onPointerUp(event) {
            if (! state.dragging) {
                return;
            }

            event.preventDefault();
            state.dragging = false;
            root.classList.remove('is-dragging');
            document.removeEventListener('pointermove', onPointerMove);
            document.removeEventListener('pointercancel', onPointerUp);

            recordMovement();

            if (! state.rotationEnabled) {
                verifyCurrentPosition();
            }
        }

        function onPointerDown(event) {
            if (! state.challengeId || root.classList.contains('is-loading') || root.classList.contains('is-success')) {
                return;
            }

            event.preventDefault();
            updateScale();

            var point = pointFromEvent(event);
            var rect = stage.getBoundingClientRect();

            state.dragging = true;
            state.startedAt = Date.now();
            state.movement = [];
            state.grabX = point.x - rect.left - state.left;
            state.grabY = point.y - rect.top - state.top;

            root.classList.add('is-dragging');

            if (piece.setPointerCapture && event.pointerId !== undefined) {
                piece.setPointerCapture(event.pointerId);
            }

            recordMovement();
            document.addEventListener('pointermove', onPointerMove);
            document.addEventListener('pointerup', onPointerUp, { once: true });
            document.addEventListener('pointercancel', onPointerUp, { once: true });
        }

        function loadChallenge() {
            clearValidation();
            state.challengeId = null;
            state.left = 0;
            state.top = 0;
            state.rotation = 0;
            state.rotationEnabled = false;
            state.rotationStep = 15;
            state.startedAt = 0;
            state.movement = [];
            setPiecePosition(0, 0);
            setState('loading');

            if (rotationControls) {
                rotationControls.hidden = true;
            }

            fetch(newUrl(), {
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(json)
                .then(function (data) {
                    if (data.enabled === false) {
                        if (verifiedInput) {
                            verifiedInput.value = '1';
                        }

                        setState('disabled');
                        dispatch('slide-captcha:disabled', data);
                        return;
                    }

                    state.challengeId = data.challenge_id;
                    state.rotationEnabled = !! data.rotation_enabled;
                    state.rotationStep = parseFloat(data.rotation_step) || 15;

                    if (rotationControls) {
                        rotationControls.hidden = ! state.rotationEnabled;
                    }

                    if (rotateLeft) {
                        rotateLeft.textContent = '-' + state.rotationStep + '°';
                    }

                    if (rotateRight) {
                        rotateRight.textContent = '+' + state.rotationStep + '°';
                    }

                    if (challengeInput) {
                        challengeInput.value = data.challenge_id;
                    }

                    background.onload = updateScale;
                    piece.onload = function () {
                        updateScale();
                        setPiecePosition(0, 0);
                        setRotation(0);
                        setState('ready');
                        dispatch('slide-captcha:ready', data);
                    };

                    background.src = data.background_url;
                    piece.src = data.piece_url;
                })
                .catch(function (error) {
                    clearValidation();
                    setState('error');
                    dispatch('slide-captcha:error', error);
                });
        }

        piece.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('resize', updateScale);

        if (reload) {
            reload.addEventListener('click', function (event) {
                event.preventDefault();
                loadChallenge();
            });
        }

        if (rotateLeft) {
            rotateLeft.addEventListener('click', function (event) {
                event.preventDefault();

                if (! state.challengeId || ! state.rotationEnabled || root.classList.contains('is-loading') || root.classList.contains('is-success')) {
                    return;
                }

                if (state.startedAt === 0) {
                    state.startedAt = Date.now();
                }

                setRotation(state.rotation - state.rotationStep);
                recordMovement();
            });
        }

        if (rotateRight) {
            rotateRight.addEventListener('click', function (event) {
                event.preventDefault();

                if (! state.challengeId || ! state.rotationEnabled || root.classList.contains('is-loading') || root.classList.contains('is-success')) {
                    return;
                }

                if (state.startedAt === 0) {
                    state.startedAt = Date.now();
                }

                setRotation(state.rotation + state.rotationStep);
                recordMovement();
            });
        }

        if (checkButton) {
            checkButton.addEventListener('click', function (event) {
                event.preventDefault();

                if (! state.challengeId || root.classList.contains('is-loading') || root.classList.contains('is-success')) {
                    return;
                }

                if (state.startedAt === 0) {
                    state.startedAt = Date.now();
                }

                recordMovement();
                verifyCurrentPosition();
            });
        }

        loadChallenge();
    }

    onReady(function () {
        var captchas = document.querySelectorAll(rootSelector);

        for (var index = 0; index < captchas.length; index++) {
            init(captchas[index]);
        }
    });
}());
