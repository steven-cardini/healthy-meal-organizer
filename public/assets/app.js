if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}

// Swipe left / right drags the content and follows the next / previous link of the day and week view (docs/decisions.md D-28).
let swipe = null;

visualViewport?.addEventListener('resize', () => {
    document.documentElement.classList.toggle('zoomed', visualViewport.scale > 1);
});

document.addEventListener('touchstart', (event) => {
    const touch = event.touches[0];
    const main = document.querySelector('main');
    swipe = event.touches.length === 1 && !document.documentElement.classList.contains('zoomed') && main.querySelector('a[rel="next"]')
        ? {main, x: touch.clientX, y: touch.clientY, dx: 0, horizontal: null}
        : null;
}, {passive: true});

document.addEventListener('touchmove', (event) => {
    if (swipe === null) {
        return;
    }
    const dx = event.touches[0].clientX - swipe.x;
    const dy = event.touches[0].clientY - swipe.y;
    if (swipe.horizontal === null) {
        if (Math.hypot(dx, dy) < 10) {
            return;
        }
        swipe.horizontal = Math.abs(dx) > Math.abs(dy);
        if (!swipe.horizontal) {
            swipe = null;
            return;
        }
        swipe.time = event.timeStamp;
        swipe.main.style.transition = 'none';
    }
    swipe.dx = dx;
    swipe.main.style.transform = `translateX(${dx}px)`;
    swipe.main.style.opacity = String(1 - Math.min(Math.abs(dx) / innerWidth, 1) / 2);
}, {passive: true});

function endSwipe(event) {
    if (swipe === null) {
        return;
    }
    const {main, dx, horizontal, time} = swipe;
    swipe = null;
    if (!horizontal) {
        return;
    }
    const isFlick = Math.abs(dx) > 30 && Math.abs(dx) / (event.timeStamp - time) > 0.5;
    const link = main.querySelector(dx < 0 ? 'a[rel="next"]' : 'a[rel="prev"]');
    main.style.transition = 'transform 0.2s ease-out, opacity 0.2s ease-out';
    if (event.type === 'touchend' && link && (Math.abs(dx) > innerWidth / 4 || isFlick)) {
        main.style.transform = `translateX(${Math.sign(dx) * innerWidth}px)`;
        main.style.opacity = '0';
        link.click();
    } else {
        main.style.transform = '';
        main.style.opacity = '';
        main.addEventListener('transitionend', () => swipe === null && main.removeAttribute('style'), {once: true});
    }
}

document.addEventListener('touchend', endSwipe, {passive: true});
document.addEventListener('touchcancel', endSwipe, {passive: true});

// Back / forward cache: show the content again after a swipe away from this page.
window.addEventListener('pageshow', () => document.querySelector('main').removeAttribute('style'));

// Slide direction of the page transition (app.css). Not supported or storage blocked: the page just fades.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[rel="prev"], a[rel="next"]');
    try {
        if (link) {
            sessionStorage.setItem('slide', link.rel);
        }
    } catch {
    }
});

window.addEventListener('pagereveal', (event) => {
    try {
        const slide = sessionStorage.getItem('slide');
        sessionStorage.removeItem('slide');
        if (slide && event.viewTransition) {
            event.viewTransition.types.add(slide);
        }
    } catch {
    }
});
