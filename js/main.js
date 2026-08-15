// BrainOverflow - Mouse following white glow effect
// Soft ambient white light that follows the cursor on desktop devices
(function () {
    'use strict';

    // Disable on touch / mobile devices
    var isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || window.matchMedia('(pointer: coarse)').matches;
    if (isTouchDevice) return;

    var glow = document.getElementById('cursorGlow') || document.getElementById('mouse-glow');
    if (!glow) return;

    var mouseX = -500;
    var mouseY = -500;
    var glowX = -500;
    var glowY = -500;
    var isActive = false;

    // Track mouse position on window
    window.addEventListener('mousemove', function (e) {
        mouseX = e.clientX;
        mouseY = e.clientY;

        if (!isActive) {
            isActive = true;
            glow.classList.add('active');
            animate();
        }
    });

    // Hide glow when mouse leaves the viewport
    document.addEventListener('mouseleave', function () {
        isActive = false;
        glow.classList.remove('active');
    });

    document.addEventListener('mouseenter', function (e) {
        mouseX = e.clientX;
        mouseY = e.clientY;
        isActive = true;
        glow.classList.add('active');
        animate();
    });

    // Smooth animation loop using linear interpolation (lerp)
    function animate() {
        if (!isActive) return;

        glowX += (mouseX - glowX) * 0.18;
        glowY += (mouseY - glowY) * 0.18;

        glow.style.left = glowX + 'px';
        glow.style.top = glowY + 'px';

        requestAnimationFrame(animate);
    }
})();
