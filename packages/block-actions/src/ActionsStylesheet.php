<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions;

/**
 * The look of the actions blocks: button styles with their hover and focus states, pricing plans, ratings and
 * social links.
 *
 * Colours and spacing come from the design tokens' custom properties, with fallbacks, so a site's tokens restyle
 * every button at once. It loads nothing else: no imports, no images.
 *
 * @since 0.1.0
 */
class ActionsStylesheet
{
    public const string CSS = <<<'CSS'
        .b-button { display: inline-flex; align-items: center; justify-content: center; gap: .4em;
            padding: .6em 1.2em; border: 2px solid transparent; border-radius: .5em; font: inherit; font-weight: 600;
            line-height: 1.2; text-decoration: none; cursor: pointer; transition: background-color .15s, color .15s; }
        .b-button--small { padding: .4em .8em; font-size: .875em; }
        .b-button--large { padding: .8em 1.6em; font-size: 1.125em; }
        .b-button--primary { color: #fff; background: var(--color-primary, #2456d6); }
        .b-button--primary:hover { background: var(--color-primary-dark, #1a3fa3); }
        .b-button--secondary { color: var(--color-primary, #2456d6); background: var(--color-primary-wash, #e8eefc); }
        .b-button--secondary:hover { background: var(--color-primary-wash-dark, #d3defa); }
        .b-button--outline { color: var(--color-primary, #2456d6); border-color: currentColor;
            background: transparent; }
        .b-button--outline:hover { background: var(--color-primary-wash, #e8eefc); }
        .b-button--plain { padding-inline: 0; color: var(--color-primary, #2456d6); background: transparent;
            text-decoration: underline; text-underline-offset: .2em; }
        .b-button:focus-visible, .b-social-link:focus-visible { outline: 3px solid var(--color-focus, #f5a623);
            outline-offset: 2px; }
        .b-button[aria-disabled="true"] { opacity: .55; cursor: default; }
        .b-note { display: block; margin-top: .25em; font-size: .75em; font-weight: 400; color: #5b6472; }
        .b-buttons { display: flex; flex-wrap: wrap; gap: var(--space-sm, .75rem); }
        .b-buttons--center { justify-content: center; }
        .b-buttons--end { justify-content: flex-end; }
        .b-buttons--between { justify-content: space-between; }
        .b-cta { display: grid; gap: var(--space-sm, .75rem); padding: var(--space-md, 1.5rem);
            border-radius: .75em; background: var(--color-primary-wash, #e8eefc); }
        .b-cta > * { margin: 0; }
        .b-testimonial { margin: 0; }
        .b-testimonial blockquote { margin: 0 0 .75em; font-size: 1.125em; }
        .b-testimonial figcaption { display: flex; flex-wrap: wrap; align-items: center; gap: .25em .6em; }
        .b-testimonial img { width: 3em; height: 3em; border-radius: 50%; object-fit: cover; }
        .b-testimonial-name { font-weight: 600; }
        .b-testimonial-role { color: #5b6472; }
        .b-pricing { gap: var(--space-md, 1.5rem); align-items: stretch; }
        .b-plan { display: flex; flex-direction: column; gap: .75em; padding: var(--space-md, 1.5rem);
            border: 1px solid #d5d9e0; border-radius: .75em; }
        .b-plan > * { margin: 0; }
        .b-plan--highlighted { border: 2px solid var(--color-primary, #2456d6); }
        .b-plan-badge { align-self: flex-start; padding: .2em .6em; border-radius: 999px; font-size: .75em;
            font-weight: 600; color: #fff; background: var(--color-primary, #2456d6); }
        .b-plan-price span:first-child { font-size: 2em; font-weight: 700; }
        .b-plan-features { flex: 1; padding-left: 1.2em; }
        .b-rating { display: inline-flex; align-items: center; gap: .5em; margin: 0; }
        .b-rating-stars { display: inline-flex; color: var(--color-rating, #e0a100); }
        .b-star { width: 1.1em; height: 1.1em; }
        .b-rating-text { font-size: .875em; }
        .b-social { display: flex; flex-wrap: wrap; gap: .5em; margin: 0; padding: 0; list-style: none; }
        .b-social-link { display: inline-block; padding: .35em .8em; border: 1px solid currentColor;
            border-radius: 999px; color: inherit; text-decoration: none; }
        .b-social-link:hover { color: var(--color-primary, #2456d6); }
        .b-social--small .b-social-link { font-size: .8125em; }
        .b-social--large .b-social-link { font-size: 1.125em; }
        @media (prefers-reduced-motion: reduce) { .b-button { transition: none; } }
        CSS;
}
