/**
 * Adds what the interactive blocks cannot do with markup alone: tabs, slider controls, a live countdown and a
 * counting animation.
 *
 * Everything works without it. It never writes markup from the page's text: it moves the elements the page drew,
 * sets text with textContent and reads numbers from data- attributes, leaving anything it cannot read as drawn.
 * Each element is set up once, however often the script loads or blocks are added.
 */
import { clamp, countAt, formatNumber, nextTab, remaining, slideLabel, tabLabel } from './logic.js';

const READY = 'data-b-ready';

function reducedMotion() {
    return typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function make(tag, attributes = {}, text = '') {
    const element = document.createElement(tag);

    for (const [name, value] of Object.entries(attributes)) {
        element.setAttribute(name, value);
    }

    element.textContent = text;

    return element;
}

/**
 * Turns a set of headed sections into tabs, following the WAI-ARIA tabs pattern. Only the set's own tabs are taken,
 * so a set inside a tab keeps its own.
 */
function setUpTabs(set) {
    const tabs = [...set.querySelectorAll(':scope > [data-b-tab]')];

    if (tabs.length === 0) {
        return;
    }

    const list = make('div', { role: 'tablist', class: 'b-tablist' });
    const buttons = tabs.map((tab, index) => {
        const label = tab.querySelector(':scope > .b-tab-label');
        const button = make('button', {
            type: 'button',
            role: 'tab',
            id: `${tab.id}-tab`,
            'aria-controls': tab.id,
        }, tabLabel(label?.textContent, index));

        label?.classList.add('b-visually-hidden');
        tab.setAttribute('role', 'tabpanel');
        tab.setAttribute('aria-labelledby', button.id);
        tab.setAttribute('tabindex', '0');
        list.append(button);

        return button;
    });

    const select = (chosen, focus) => {
        buttons.forEach((button, index) => {
            const on = index === chosen;
            button.setAttribute('aria-selected', String(on));
            button.setAttribute('tabindex', on ? '0' : '-1');
            tabs[index].hidden = !on;
        });

        if (focus) {
            buttons[chosen].focus();
        }
    };

    list.addEventListener('click', (event) => {
        const index = buttons.indexOf(event.target.closest('[role="tab"]'));

        if (index >= 0) {
            select(index, false);
        }
    });
    list.addEventListener('keydown', (event) => {
        const index = nextTab(buttons.indexOf(document.activeElement), buttons.length, event.key);

        if (index !== null) {
            event.preventDefault();
            select(index, true);
        }
    });

    set.prepend(list);
    select(0, false);
}

/**
 * Gives a slider Previous and Next buttons, names its slides, and, when asked, moves on by itself with a Pause
 * button, stopping while a visitor hovers or focuses it, and never for visitors who ask for less motion.
 */
function setUpSlider(slider) {
    const track = slider.querySelector(':scope > .b-slides');
    const slides = track ? [...track.querySelectorAll(':scope > .b-slide')] : [];

    if (!track || slides.length === 0) {
        return;
    }

    slides.forEach((slide, index) => {
        if (!slide.hasAttribute('aria-label')) {
            slide.setAttribute('aria-label', slideLabel(index, slides.length));
        }
    });

    const behavior = reducedMotion() ? 'auto' : 'smooth';
    const step = (direction) => {
        const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
        const atStart = track.scrollLeft <= 2;

        if (direction > 0 && atEnd) {
            track.scrollTo({ left: 0, behavior });
        } else if (direction < 0 && atStart) {
            track.scrollTo({ left: track.scrollWidth, behavior });
        } else {
            track.scrollBy({ left: direction * track.clientWidth, behavior });
        }
    };
    const controls = make('div', { class: 'b-slider-controls' });
    const previous = make('button', { type: 'button' }, 'Previous');
    const next = make('button', { type: 'button' }, 'Next');
    previous.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));
    controls.append(previous, next);

    if (slider.getAttribute('data-autoplay') === '1' && !reducedMotion()) {
        const seconds = clamp(slider.getAttribute('data-interval'), 3, 30, 6);
        const pause = make('button', { type: 'button', 'aria-pressed': 'false' }, 'Pause');
        let stopped = false;
        let held = false;
        let timer = null;
        const run = () => {
            clearInterval(timer);
            timer = stopped || held ? null : setInterval(() => step(1), seconds * 1000);
        };

        pause.addEventListener('click', () => {
            stopped = !stopped;
            pause.textContent = stopped ? 'Play' : 'Pause';
            pause.setAttribute('aria-pressed', String(stopped));
            run();
        });
        slider.addEventListener('mouseenter', () => { held = true; run(); });
        slider.addEventListener('mouseleave', () => { held = false; run(); });
        slider.addEventListener('focusin', () => { held = true; run(); });
        slider.addEventListener('focusout', (event) => {
            if (!slider.contains(event.relatedTarget)) {
                held = false;
                run();
            }
        });
        controls.append(pause);
        run();
    }

    slider.append(controls);
}

/**
 * Shows the time left beside a countdown's date, once a second, and its closing words when the moment passes.
 */
function setUpCountdown(countdown) {
    const target = Date.parse(countdown.getAttribute('data-target') ?? '');
    const time = countdown.querySelector('.b-countdown-time');
    const after = countdown.querySelector('.b-countdown-after');

    if (!Number.isFinite(target) || !time) {
        return;
    }

    const left = make('span', { class: 'b-countdown-left' });
    time.append(left);

    const tick = () => {
        const parts = remaining(target, Date.now());

        if (parts.ended) {
            clearInterval(timer);
            time.hidden = true;

            if (after) {
                after.hidden = false;
            }

            return;
        }

        left.textContent = `${parts.days} days, ${parts.hours} hours, ${parts.minutes} minutes, ${parts.seconds} seconds`;
    };
    const timer = setInterval(tick, 1000);
    tick();
}

/**
 * Counts a counter up to its value the first time it comes into view; for visitors who ask for less motion it stays
 * at its value.
 */
function setUpCounter(counter) {
    const number = counter.querySelector('.b-counter-number');

    if (!number || reducedMotion() || typeof IntersectionObserver !== 'function') {
        return;
    }

    const from = clamp(counter.getAttribute('data-from'), -1e15, 1e15, 0);
    const to = clamp(counter.getAttribute('data-to'), -1e15, 1e15, 0);
    const duration = clamp(counter.getAttribute('data-duration'), 0, 10_000, 2000);
    const decimals = clamp(counter.getAttribute('data-decimals'), 0, 3, 0);
    const prefix = counter.getAttribute('data-prefix') ?? '';
    const suffix = counter.getAttribute('data-suffix') ?? '';

    if (duration === 0) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) {
            return;
        }

        observer.disconnect();
        const start = performance.now();
        const frame = (now) => {
            number.textContent = formatNumber(countAt(from, to, now - start, duration), decimals, prefix, suffix);

            if (now - start < duration) {
                requestAnimationFrame(frame);
            }
        };
        requestAnimationFrame(frame);
    });
    observer.observe(counter);
}

const SET_UP = [
    ['[data-b-tabs]', setUpTabs],
    ['[data-b-slider]', setUpSlider],
    ['[data-b-countdown]', setUpCountdown],
    ['[data-b-counter]', setUpCounter],
];

function setUpAll(root) {
    for (const [selector, setUp] of SET_UP) {
        for (const element of root.querySelectorAll(`${selector}:not([${READY}])`)) {
            element.setAttribute(READY, '');

            try {
                setUp(element);
            } catch {
                // One block that cannot be set up stays as drawn; the others still are.
            }
        }
    }
}

if (typeof window !== 'undefined' && !window.__bInteractive) {
    window.__bInteractive = true;

    const start = () => {
        setUpAll(document);
        new MutationObserver(() => setUpAll(document)).observe(document.body, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}
