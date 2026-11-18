<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site;

/**
 * The look of the site blocks, with no script: submenus open on hover and when a keyboard user moves into them.
 *
 * A closed submenu is moved out of view rather than removed, so Tab still reaches its links and opens it.
 *
 * On narrow screens, and in vertical menus, submenus sit in place under their link rather than floating. It loads
 * nothing else.
 *
 * @since 0.1.0
 */
class SiteStylesheet
{
    public const string CSS = <<<'CSS'
        .b-nav ul { margin: 0; padding: 0; list-style: none; }
        .b-nav > ul { display: flex; flex-wrap: wrap; gap: .25rem 1rem; }
        .b-nav--vertical > ul { flex-direction: column; }
        .b-nav-item { position: relative; }
        .b-nav-item > a, .b-nav-text { display: inline-block; padding: .4rem .2rem; color: inherit; }
        .b-nav-item > a[aria-current="page"] { font-weight: 700; text-decoration-thickness: 2px; }
        .b-nav-item > a:focus-visible { outline: 3px solid var(--color-focus, #f5a623); outline-offset: 2px; }
        .b-nav-sub { position: absolute; top: 100%; left: -9999px; z-index: 10; min-width: 12rem;
            padding: .4rem .6rem; border: 1px solid #d5d9e0; border-radius: .5rem; background: #fff; }
        .b-nav-item:hover > .b-nav-sub, .b-nav-item:focus-within > .b-nav-sub { left: 0; }
        .b-nav-sub .b-nav-item:hover > .b-nav-sub, .b-nav-sub .b-nav-item:focus-within > .b-nav-sub {
            top: 0; left: 100%; }
        .b-nav--vertical .b-nav-sub { position: static; border: 0; padding: 0 0 0 1rem; }
        @media (max-width: 40em) {
            .b-nav > ul { flex-direction: column; }
            .b-nav-sub, .b-nav-sub .b-nav-sub { position: static; border: 0; padding: 0 0 0 1rem; }
        }
        .b-toc { padding: 1rem; border-left: 3px solid var(--color-primary, #2456d6); }
        .b-toc > h2 { margin: 0 0 .5rem; font-size: 1rem; }
        .b-toc ol { margin: 0; padding: 0; list-style: none; }
        .b-toc-l1 { padding-left: 0; } .b-toc-l2 { padding-left: 0; } .b-toc-l3 { padding-left: 1rem; }
        .b-toc-l4 { padding-left: 2rem; } .b-toc-l5 { padding-left: 3rem; } .b-toc-l6 { padding-left: 4rem; }
        .b-crumbs ol { display: flex; flex-wrap: wrap; margin: 0; padding: 0; list-style: none; font-size: .875em; }
        .b-crumbs li + li::before { content: "/"; padding: 0 .5em; color: #8a93a0; }
        .b-crumbs [aria-current="page"] { font-weight: 600; }
        .b-search { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .b-search input { flex: 1 1 12rem; padding: .5rem .7rem; border: 1px solid #c3c9d2; border-radius: .4rem;
            font: inherit; }
        .b-search button { padding: .5rem 1rem; border: 0; border-radius: .4rem; color: #fff; font: inherit;
            background: var(--color-primary, #2456d6); cursor: pointer; }
        .b-search input:focus-visible, .b-search button:focus-visible, .b-toc a:focus-visible,
        .b-crumbs a:focus-visible { outline: 3px solid var(--color-focus, #f5a623); outline-offset: 2px; }
        .b-prompt { margin: 0; padding: 1rem; border: 1px dashed #c3c9d2; border-radius: .5rem; color: #5b6472; }
        .b-note { display: block; font-size: .75em; color: #5b6472; }
        CSS;
}
