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
    if (event.type === 'touchend' && link && (Math.abs(dx) > innerWidth / 4 || isFlick)) {
        // The content stays where the finger let go; the page transition (app.css) slides it on from there.
        try {
            sessionStorage.setItem('swipe', JSON.stringify({type: link.rel, x: dx, y: scrollY}));
        } catch {
        }
        link.click();
    } else {
        main.style.transition = 'transform 0.2s ease-out';
        main.style.transform = '';
        main.addEventListener('transitionend', () => swipe === null && main.removeAttribute('style'), {once: true});
    }
}

document.addEventListener('touchend', endSwipe, {passive: true});
document.addEventListener('touchcancel', endSwipe, {passive: true});

// Back / forward cache: show the content again after a swipe away from this page.
window.addEventListener('pageshow', () => document.querySelector('main').removeAttribute('style'));

// Only a swipe slides to the new page; every other navigation (buttons, tab bar) shows it at once.
window.addEventListener('pagereveal', (event) => {
    let swiped = null;
    try {
        swiped = JSON.parse(sessionStorage.getItem('swipe'));
        sessionStorage.removeItem('swipe');
    } catch {
    }
    if (!event.viewTransition) {
        return;
    }
    if (swiped) {
        event.viewTransition.types.add(swiped.type);
        document.documentElement.style.setProperty('--swipe-x', `${swiped.x}px`);
        document.documentElement.style.setProperty('--swipe-y', `${-swiped.y}px`);
    } else {
        event.viewTransition.skipTransition();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // Meal form: "+ Zutat" adds an empty ingredient row (docs/decisions.md D-47).
    const addIngredient = document.querySelector('.add-ingredient');
    if (addIngredient) {
        addIngredient.hidden = false;
        addIngredient.addEventListener('click', () => {
            const rows = document.querySelector('.ingredient-rows');
            rows.append(document.getElementById('ingredient-row').content.cloneNode(true));
            rows.lastElementChild.querySelector('input').focus();
        });
    }

    // Grocery list: ticks are kept per week while the app is open (D-49).
    const groceryList = document.querySelector('.grocery-list');
    if (groceryList) {
        const key = `ticks-${groceryList.dataset.week}`;
        let ticks = [];
        try {
            ticks = JSON.parse(sessionStorage.getItem(key)) ?? [];
        } catch {
        }
        for (const box of groceryList.querySelectorAll('input[type="checkbox"]')) {
            box.checked = ticks.includes(box.value);
        }
        groceryList.addEventListener('change', () => {
            const checked = [...groceryList.querySelectorAll('input:checked')].map((box) => box.value);
            try {
                sessionStorage.setItem(key, JSON.stringify(checked));
            } catch {
            }
        });
    }
});
