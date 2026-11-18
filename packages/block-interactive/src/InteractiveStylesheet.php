<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive;

/**
 * The look of the interactive blocks, written so each one already works with no script.
 *
 * Slides snap in a row a visitor can scroll; accordions are the browser's own disclosure widget; the modal panel is
 * the browser's own popover. The script only adds what these cannot do alone. It loads nothing else.
 *
 * @since 0.1.0
 */
class InteractiveStylesheet
{
    public const string CSS = <<<'CSS'
        .b-visually-hidden { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden;
            clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
        .b-accordion { display: grid; gap: .5rem; }
        .b-accordion-item { border: 1px solid #d5d9e0; border-radius: .5rem; }
        .b-accordion-item > summary { padding: .75rem 1rem; font-weight: 600; cursor: pointer; }
        .b-accordion-item > summary:focus-visible { outline: 3px solid var(--color-focus, #f5a623);
            outline-offset: 2px; }
        .b-accordion-panel { padding: 0 1rem 1rem; }
        .b-tabs { display: grid; gap: 1rem; }
        .b-tab-label { margin: 0 0 .5rem; }
        .b-tablist { display: flex; flex-wrap: wrap; gap: .25rem; border-bottom: 1px solid #d5d9e0; }
        .b-tablist button { padding: .5rem 1rem; border: 0; border-bottom: 3px solid transparent; font: inherit;
            background: none; cursor: pointer; }
        .b-tablist button[aria-selected="true"] { border-bottom-color: var(--color-primary, #2456d6);
            font-weight: 600; }
        .b-tablist button:focus-visible { outline: 3px solid var(--color-focus, #f5a623); outline-offset: 2px; }
        .b-slider { position: relative; }
        .b-slides { display: flex; gap: 1rem; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth;
            overscroll-behavior-x: contain; }
        .b-slide { flex: 0 0 100%; scroll-snap-align: start; }
        .b-slider-controls { display: flex; gap: .5rem; margin-top: .5rem; }
        .b-slider-controls button, .b-modal-open, .b-modal-close { padding: .4rem .9rem; border: 1px solid #c3c9d2;
            border-radius: .4rem; font: inherit; background: #fff; cursor: pointer; }
        .b-slider-controls button:focus-visible, .b-modal-open:focus-visible, .b-modal-close:focus-visible {
            outline: 3px solid var(--color-focus, #f5a623); outline-offset: 2px; }
        .b-countdown-label { margin: 0; font-size: .875em; }
        .b-countdown-time { margin: .25rem 0 0; font-size: 1.25em; font-weight: 600; }
        .b-countdown-left { display: block; font-variant-numeric: tabular-nums; }
        .b-progress { display: grid; grid-template-columns: 1fr auto; gap: .25rem .75rem; align-items: center; }
        .b-progress progress { grid-column: 1 / -1; width: 100%; height: .75rem;
            accent-color: var(--color-primary, #2456d6); }
        .b-counter { margin: 0; font-size: 2.5em; font-weight: 700; font-variant-numeric: tabular-nums; }
        .b-modal-panel { width: min(40rem, calc(100% - 2rem)); padding: 1.5rem; border: 0; border-radius: .75rem;
            box-shadow: 0 1rem 3rem rgb(0 0 0 / .25); }
        .b-modal-panel::backdrop { background: rgb(0 0 0 / .4); }
        .b-modal-panel > h2 { margin-top: 0; }
        .b-prompt { margin: 0; padding: 1rem; border: 1px dashed #c3c9d2; border-radius: .5rem; color: #5b6472; }
        @media (prefers-reduced-motion: reduce) { .b-slides { scroll-behavior: auto; } }
        CSS;
}
