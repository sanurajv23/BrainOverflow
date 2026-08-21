// BrainOverflow - Theme toggle
(function () {
    'use strict';

    var storageKey = 'theme';
    var root = document.documentElement;

    function getSavedTheme() {
        try {
            return localStorage.getItem(storageKey);
        } catch (error) {
            return null;
        }
    }

    function saveTheme(theme) {
        try {
            localStorage.setItem(storageKey, theme);
        } catch (error) {
            // Theme still applies for the current page if storage is unavailable.
        }
    }

    function applyTheme(theme) {
        var normalizedTheme = theme === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', normalizedTheme);

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            var isDark = normalizedTheme === 'dark';
            button.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');
            button.setAttribute('title', isDark ? 'Switch to light theme' : 'Switch to dark theme');
            button.textContent = isDark ? '☀' : '☾';
        });
    }

    applyTheme(getSavedTheme() || 'light');

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-theme-toggle]');

        if (!toggle) {
            return;
        }

        var nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
        saveTheme(nextTheme);
    });
})();

// BrainOverflow - Soft mouse glow trail
(function () {
    'use strict';

    var hasFineHoverPointer = window.matchMedia('(any-pointer: fine) and (any-hover: hover)').matches;
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!hasFineHoverPointer || prefersReducedMotion) {
        return;
    }

    var layer = document.getElementById('cursorGlow');

    if (!layer) {
        layer = document.createElement('div');
        layer.id = 'cursorGlow';
        layer.className = 'cursor-glow';
        document.body.appendChild(layer);
    }

    var trailCount = 32;
    var glows = [];
    var pointer = { x: 0, y: 0, lastX: 0, lastY: 0, moveX: 0, moveY: 0, speed: 0, active: false, initialized: false, lastMoveTime: 0 };
    var nextGlow = 0;
    var lastEmit = 0;
    var lastEmitX = 0;
    var lastEmitY = 0;
    var rafId = 0;

    for (var i = 0; i < trailCount; i += 1) {
        var glow = document.createElement('span');
        glow.className = 'mouse-glow';
        layer.appendChild(glow);
        glows.push({
            el: glow,
            x: -999,
            y: -999,
            age: 1,
            life: 1,
            strength: 0,
            size: 1
        });
    }

    window.addEventListener('mousemove', function (event) {
        var dx = pointer.initialized ? event.clientX - pointer.lastX : 0;
        var dy = pointer.initialized ? event.clientY - pointer.lastY : 0;

        pointer.x = event.clientX;
        pointer.y = event.clientY;
        pointer.moveX = dx;
        pointer.moveY = dy;
        pointer.speed = Math.min(Math.sqrt(dx * dx + dy * dy), 90);
        pointer.lastX = event.clientX;
        pointer.lastY = event.clientY;
        pointer.active = true;
        pointer.lastMoveTime = event.timeStamp;

        if (!pointer.initialized) {
            lastEmitX = event.clientX;
            lastEmitY = event.clientY;
        }

        pointer.initialized = true;

        if (!rafId) {
            rafId = requestAnimationFrame(animate);
        }
    });

    document.addEventListener('mouseleave', function () {
        pointer.active = false;
    });

    function emitGlow(x, y, strength, size) {
        var glow = glows[nextGlow];

        glow.x = x;
        glow.y = y;
        glow.age = 0;
        glow.life = 760;
        glow.strength = strength;
        glow.size = size;

        nextGlow = (nextGlow + 1) % glows.length;
    }

    function emitTrail(time) {
        if (!pointer.active || time - pointer.lastMoveTime > 90) {
            return;
        }

        var sinceLast = Math.sqrt(Math.pow(pointer.x - lastEmitX, 2) + Math.pow(pointer.y - lastEmitY, 2));
        var shouldEmitByDistance = sinceLast >= 6;
        var shouldEmitByTime = time - lastEmit >= 30 && pointer.speed > 0.5;

        if (!shouldEmitByDistance && !shouldEmitByTime) {
            return;
        }

        var steps = Math.min(Math.max(Math.floor(sinceLast / 8), 1), 5);
        var brightness = 0.5 + (pointer.speed / 90) * 0.13;
        var size = 0.22 + (pointer.speed / 90) * 0.06;

        for (var step = 1; step <= steps; step += 1) {
            var progress = step / steps;
            var x = lastEmitX + (pointer.x - lastEmitX) * progress;
            var y = lastEmitY + (pointer.y - lastEmitY) * progress;

            emitGlow(x, y, brightness, size);
        }

        lastEmitX = pointer.x;
        lastEmitY = pointer.y;
        lastEmit = time;
    }

    function animate(time) {
        var visible = false;

        emitTrail(time);

        glows.forEach(function (glow) {
            glow.age += 16.7;

            var progress = Math.min(glow.age / glow.life, 1);
            var fade = Math.pow(1 - progress, 1.9);
            var opacity = glow.strength * fade;
            var scale = glow.size + progress * 0.92;

            if (opacity > 0.003) {
                visible = true;
            }

            glow.el.style.opacity = opacity.toFixed(3);
            glow.el.style.transform = 'translate3d(' + (glow.x - 22) + 'px, ' + (glow.y - 22) + 'px, 0) scale(' + scale.toFixed(3) + ')';
        });

        if (visible || pointer.active) {
            rafId = requestAnimationFrame(animate);
        } else {
            rafId = 0;
        }
    }
})();
