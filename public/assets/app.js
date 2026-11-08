if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}

// Swipe left / right follows the next / previous link of the day and week view (docs/decisions.md D-28).
let touchStart = null;

document.addEventListener('touchstart', (event) => {
    const touch = event.touches[0];
    touchStart = event.touches.length === 1 ? {x: touch.clientX, y: touch.clientY} : null;
}, {passive: true});

document.addEventListener('touchend', (event) => {
    if (touchStart === null) {
        return;
    }
    const dx = event.changedTouches[0].clientX - touchStart.x;
    const dy = event.changedTouches[0].clientY - touchStart.y;
    touchStart = null;
    if (Math.abs(dx) > 60 && Math.abs(dx) > 2 * Math.abs(dy)) {
        document.querySelector(dx < 0 ? 'a[rel="next"]' : 'a[rel="prev"]')?.click();
    }
}, {passive: true});

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
