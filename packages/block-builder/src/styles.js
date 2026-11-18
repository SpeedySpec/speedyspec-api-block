/**
 * The builder's own appearance. Everything is scoped under the element, so it changes nothing on the host page.
 *
 * One colour, the teal, means "selected" or "active" and is used for nothing else. The page being edited is the
 * largest and brightest thing on screen; the panels around it stay quiet.
 */
export const STYLE = `
block-builder {
    --bb-ink: #16181d;
    --bb-text: #262b36;
    --bb-soft: #5b6475;
    --bb-line: #d5d9e0;
    --bb-panel: #ffffff;
    --bb-stage: #e4e7ec;
    --bb-wash: #f3f5f8;
    --bb-pick: #0f7b6c;
    --bb-pick-wash: #e3f3f0;
    --bb-bad: #b4281e;
    --bb-bad-wash: #fcebe9;
    display: grid;
    grid-template: 3rem minmax(0, 1fr) 2rem / auto minmax(0, 1fr) 21rem;
    height: 100%;
    min-height: 34rem;
    color: var(--bb-text);
    background: var(--bb-stage);
    font: 13px/1.45 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
}
block-builder *, block-builder *::before, block-builder *::after { box-sizing: border-box; }
block-builder button, block-builder input, block-builder select, block-builder textarea { font: inherit; color: inherit; }
block-builder button { cursor: pointer; }
block-builder button:disabled { cursor: default; opacity: .4; }
block-builder :focus-visible { outline: 2px solid var(--bb-pick); outline-offset: 1px; }
block-builder svg { width: 1rem; height: 1rem; flex: none; fill: none; stroke: currentColor; stroke-width: 1.6;
    stroke-linecap: round; stroke-linejoin: round; }

/* Top bar */
block-builder .bb-top { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;
    gap: 1rem; padding: 0 .75rem; color: #e9ebf0; background: var(--bb-ink); }
block-builder .bb-top-left, block-builder .bb-top-right { display: flex; align-items: center; gap: .5rem; min-width: 0; }
block-builder .bb-top-right { justify-content: flex-end; }
block-builder .bb-name { margin-right: .5rem; overflow: hidden; font-size: 15px; font-weight: 600; text-overflow: ellipsis;
    white-space: nowrap; }
block-builder .bb-topbtn { display: inline-flex; align-items: center; gap: .4rem; height: 2rem; padding: 0 .7rem;
    border: 1px solid #3a3f4b; border-radius: 6px; color: #e9ebf0; background: transparent; }
block-builder .bb-topbtn:hover { background: #262a33; }
block-builder .bb-topicon { width: 2rem; padding: 0; justify-content: center; }
block-builder .bb-topbtn:disabled { border-color: #2c3038; }
block-builder .bb-topbtn[aria-pressed="true"] { border-color: var(--bb-pick); color: #fff; background: var(--bb-pick); }
block-builder .bb-seg { display: inline-flex; padding: 2px; border-radius: 7px; background: #262a33; }
block-builder .bb-seg button { height: 1.75rem; padding: 0 .7rem; border: 0; border-radius: 5px; color: #b9bfcb;
    background: transparent; }
block-builder .bb-seg button[aria-pressed="true"] { color: var(--bb-ink); background: #fff; }
block-builder .bb-status { color: #b9bfcb; white-space: nowrap; }
block-builder .bb-status[data-kind="bad"] { color: #ffb4ab; }
block-builder .bb-problems { height: 1.75rem; padding: 0 .6rem; border: 0; border-radius: 999px; color: #fff;
    background: var(--bb-bad); }

/* Drawer */
block-builder .bb-drawer { grid-row: 2; width: 19rem; overflow: auto; border-right: 1px solid var(--bb-line);
    background: var(--bb-panel); }
block-builder .bb-drawer[hidden] { display: none; }
block-builder .bb-drawer-head { position: sticky; top: 0; z-index: 1; padding: .75rem .75rem .6rem;
    border-bottom: 1px solid var(--bb-line); background: var(--bb-panel); }
block-builder .bb-drawer-body { padding: .6rem .75rem 1rem; }
block-builder h2 { margin: 0 0 .5rem; font-size: 15px; font-weight: 600; }
block-builder h3 { margin: 1rem 0 .4rem; color: var(--bb-soft); font-size: 12px; font-weight: 600; }
block-builder .bb-where { display: flex; gap: .3rem; margin-bottom: .5rem; }
block-builder .bb-where button { flex: 1; padding: .3rem .4rem; border: 1px solid var(--bb-line); border-radius: 6px;
    background: var(--bb-panel); }
block-builder .bb-where button[aria-pressed="true"] { border-color: var(--bb-pick); color: var(--bb-pick);
    background: var(--bb-pick-wash); font-weight: 600; }
block-builder .bb-where-only { margin: 0 0 .5rem; color: var(--bb-soft); }
block-builder .bb-section { padding: .25rem 0 .75rem; border-bottom: 1px solid var(--bb-line); }
block-builder .bb-section:last-child { border-bottom: 0; }
block-builder h3.bb-section-title { margin: .6rem 0 .1rem; color: var(--bb-text); font-size: 13px; }
block-builder .bb-section-text { margin: 0 0 .5rem; color: var(--bb-soft); font-size: 12px; }
block-builder h4 { margin: .6rem 0 .35rem; color: var(--bb-soft); font-size: 12px; font-weight: 500; }
block-builder .bb-tiles { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; }
block-builder .bb-tile { display: block; min-height: 4.2rem; padding: .5rem .55rem; border: 1px solid var(--bb-line);
    border-radius: 8px; background: var(--bb-panel); text-align: left; }
block-builder .bb-tile:hover:not(:disabled) { border-color: var(--bb-pick); background: var(--bb-pick-wash); }
block-builder .bb-tile strong { display: block; font-weight: 600; }
block-builder .bb-tile span { display: -webkit-box; overflow: hidden; color: var(--bb-soft); font-size: 12px;
    -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
block-builder .bb-row { display: flex; align-items: center; gap: .4rem; width: 100%; min-height: 1.9rem;
    padding: .2rem .4rem; border: 0; border-radius: 6px; background: transparent; text-align: left; }
block-builder .bb-row:hover { background: var(--bb-wash); }
block-builder .bb-row[aria-current="true"] { color: var(--bb-pick); background: var(--bb-pick-wash); font-weight: 600; }
block-builder .bb-row b { flex: none; font-weight: inherit; }
block-builder .bb-row small { flex: 1; min-width: 0; overflow: hidden; color: var(--bb-soft); font-size: 12px;
    font-weight: 400; text-overflow: ellipsis; white-space: nowrap; }
block-builder .bb-row[draggable], block-builder .bb-tile[draggable] { cursor: grab; }
block-builder .bb-row[data-bb-drop="before"] { box-shadow: inset 0 2px 0 var(--bb-pick); }
block-builder .bb-row[data-bb-drop="after"] { box-shadow: inset 0 -2px 0 var(--bb-pick); }
block-builder .bb-row[data-bb-drop="inside"] { background: var(--bb-pick-wash); box-shadow: inset 0 0 0 2px var(--bb-pick); }
block-builder .bb-dot { flex: none; width: .5rem; height: .5rem; border-radius: 50%; background: var(--bb-bad); }

/* Stage */
block-builder .bb-stage { grid-row: 2; position: relative; overflow: auto; padding: 2rem 1.5rem 4rem;
    background-color: var(--bb-stage); background-image: radial-gradient(#c9ced8 1px, transparent 1px);
    background-size: 16px 16px; }
block-builder .bb-page { position: relative; margin: 0 auto; background: #fff;
    box-shadow: 0 1px 2px rgb(22 24 29 / .12), 0 12px 32px -12px rgb(22 24 29 / .28); }
block-builder .bb-page iframe { display: block; width: 100%; min-height: 18rem; border: 0; }
block-builder .bb-bar { position: absolute; z-index: 2; display: flex; align-items: center; height: 2rem;
    border-radius: 6px; color: #fff; background: var(--bb-pick); box-shadow: 0 4px 12px -4px rgb(22 24 29 / .5); }
block-builder .bb-bar[hidden] { display: none; }
block-builder .bb-bar strong { padding: 0 .6rem; font-weight: 600; white-space: nowrap; }
block-builder .bb-bar button { display: grid; place-items: center; width: 2rem; height: 2rem; border: 0;
    border-left: 1px solid rgb(255 255 255 / .25); color: #fff; background: transparent; }
block-builder .bb-bar button:hover:not(:disabled) { background: rgb(0 0 0 / .18); }
block-builder .bb-bar button:last-child { border-radius: 0 6px 6px 0; }
block-builder .bb-format { position: absolute; z-index: 3; display: flex; border-radius: 6px; color: #fff;
    background: var(--bb-ink); box-shadow: 0 4px 12px -4px rgb(22 24 29 / .5); }
block-builder .bb-format[hidden] { display: none; }
block-builder .bb-format button { min-width: 2rem; height: 2rem; padding: 0 .5rem; border: 0; color: #fff;
    background: transparent; font-weight: 600; }
block-builder .bb-format button:hover { background: #262a33; }
block-builder .bb-blank { padding: 3rem 1.5rem; text-align: center; }
block-builder .bb-blank p { margin: 0 0 .75rem; color: var(--bb-soft); font-size: 15px; }
block-builder .bb-primary { height: 2.1rem; padding: 0 .9rem; border: 0; border-radius: 6px; color: #fff;
    background: var(--bb-pick); font-weight: 600; }
block-builder .bb-toast { position: sticky; bottom: 0; left: 0; z-index: 3; width: min(32rem, 100%); margin: 1rem auto 0;
    padding: .6rem .8rem; border: 1px solid #efc1bb; border-radius: 8px; color: #7d1a12; background: var(--bb-bad-wash);
    box-shadow: 0 8px 24px -10px rgb(22 24 29 / .4); }
block-builder .bb-toast ul { margin: 0; padding-left: 1.1rem; }
block-builder .bb-toast p { margin: 0; }

/* Inspector */
block-builder .bb-side { grid-row: 2; display: flex; flex-direction: column; min-height: 0;
    border-left: 1px solid var(--bb-line); background: var(--bb-panel); }
block-builder .bb-side-head { padding: .75rem .9rem 0; }
block-builder .bb-side-head p { margin: 0 0 .6rem; color: var(--bb-soft); }
block-builder .bb-tabs { display: flex; gap: 1rem; padding: 0 .9rem; border-bottom: 1px solid var(--bb-line); }
block-builder .bb-tabs button { padding: .45rem 0; border: 0; border-bottom: 2px solid transparent; color: var(--bb-soft);
    background: transparent; }
block-builder .bb-tabs button[aria-selected="true"] { border-color: var(--bb-pick); color: var(--bb-text);
    font-weight: 600; }
block-builder .bb-side-body { flex: 1; overflow: auto; padding: .25rem .9rem 1.5rem; }
block-builder .bb-none { margin: 1rem 0; color: var(--bb-soft); }
block-builder details.bb-group { border-bottom: 1px solid var(--bb-line); }
block-builder details.bb-group > summary { display: flex; align-items: center; justify-content: space-between;
    padding: .65rem 0; font-weight: 600; list-style: none; cursor: pointer; }
block-builder details.bb-group > summary::-webkit-details-marker { display: none; }
block-builder details.bb-group > summary::after { content: ""; width: .45rem; height: .45rem;
    border: solid var(--bb-soft); border-width: 0 1.5px 1.5px 0; transform: rotate(45deg); }
block-builder details.bb-group[open] > summary::after { transform: rotate(-135deg); }
block-builder details.bb-group > div { padding-bottom: .5rem; }
block-builder .bb-field { display: block; margin: 0 0 .7rem; }
block-builder .bb-label { display: flex; align-items: center; gap: .35rem; margin-bottom: .25rem; font-weight: 500; }
block-builder .bb-label i { width: .4rem; height: .4rem; border-radius: 50%; background: var(--bb-pick); }
block-builder .bb-help { display: block; margin-top: .2rem; color: var(--bb-soft); font-size: 12px; }
block-builder .bb-wrong { display: block; margin-top: .2rem; color: var(--bb-bad); font-size: 12px; }
block-builder .bb-input, block-builder select.bb-input, block-builder textarea.bb-input { width: 100%; min-height: 2rem;
    padding: .3rem .5rem; border: 1px solid var(--bb-line); border-radius: 6px; background: var(--bb-panel); }
block-builder textarea.bb-input { min-height: 4rem; resize: vertical; }
block-builder textarea.bb-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
block-builder .bb-input[aria-invalid="true"] { border-color: var(--bb-bad); }
block-builder .bb-check { display: flex; align-items: center; gap: .5rem; }
block-builder .bb-check input { width: 1rem; height: 1rem; accent-color: var(--bb-pick); }
block-builder .bb-choice { display: flex; padding: 2px; border-radius: 7px; background: var(--bb-wash); }
block-builder .bb-choice button { flex: 1; min-height: 1.75rem; padding: 0 .4rem; border: 0; border-radius: 5px;
    color: var(--bb-soft); background: transparent; }
block-builder .bb-choice button[aria-pressed="true"] { color: var(--bb-text); background: var(--bb-panel);
    box-shadow: 0 1px 2px rgb(22 24 29 / .2); font-weight: 600; }
block-builder .bb-widths { margin: .7rem 0 .2rem; }
block-builder .bb-colour { display: flex; gap: .4rem; }
block-builder .bb-colour input[type="color"] { flex: none; width: 2rem; height: 2rem; padding: 2px;
    border: 1px solid var(--bb-line); border-radius: 6px; background: var(--bb-panel); }
block-builder .bb-chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .3rem; }
block-builder .bb-chips button { display: inline-flex; align-items: center; gap: .3rem; padding: .1rem .45rem;
    border: 1px solid var(--bb-line); border-radius: 999px; background: var(--bb-panel); font-size: 12px; }
block-builder .bb-chips button[aria-pressed="true"] { border-color: var(--bb-pick); background: var(--bb-pick-wash); }
block-builder .bb-chips i { width: .7rem; height: .7rem; border: 1px solid rgb(0 0 0 / .15); border-radius: 50%; }
block-builder .bb-rules { margin: .6rem 0; padding: .6rem; border: 1px solid var(--bb-line); border-radius: 8px;
    background: var(--bb-wash); }
block-builder .bb-rules > p { margin: 0 0 .5rem; font-weight: 600; }
block-builder .bb-rule { margin-bottom: .5rem; padding: .5rem; border: 1px solid var(--bb-line); border-radius: 6px;
    background: var(--bb-panel); }
block-builder .bb-rule-top { display: flex; gap: .4rem; margin-bottom: .4rem; }
block-builder .bb-or { margin: .2rem 0; color: var(--bb-soft); text-align: center; }
block-builder .bb-link { padding: .2rem 0; border: 0; color: var(--bb-pick); background: transparent; font-weight: 600; }
block-builder .bb-icon { display: grid; place-items: center; flex: none; width: 2rem; height: 2rem;
    border: 1px solid var(--bb-line); border-radius: 6px; color: var(--bb-soft); background: var(--bb-panel); }
block-builder .bb-icon:hover { border-color: var(--bb-bad); color: var(--bb-bad); }

/* Trail */
block-builder .bb-trail { grid-column: 1 / -1; display: flex; align-items: center; gap: .25rem; padding: 0 .75rem;
    overflow: hidden; border-top: 1px solid var(--bb-line); background: var(--bb-panel); white-space: nowrap; }
block-builder .bb-trail button { padding: .1rem .3rem; border: 0; border-radius: 4px; color: var(--bb-soft);
    background: transparent; }
block-builder .bb-trail button:hover { color: var(--bb-text); background: var(--bb-wash); }
block-builder .bb-trail button:last-of-type { color: var(--bb-text); font-weight: 600; }
block-builder .bb-trail span { color: var(--bb-line); }

@media (max-width: 60rem) {
    block-builder { grid-template-columns: auto minmax(0, 1fr); grid-template-rows: 3rem minmax(0, 1fr) auto 2rem; }
    block-builder .bb-side { grid-row: 3; grid-column: 1 / -1; max-height: 40vh; border-top: 1px solid var(--bb-line);
        border-left: 0; }
    block-builder .bb-drawer { width: 15rem; }
    block-builder .bb-trail { grid-row: 4; }
}
@media (prefers-reduced-motion: no-preference) {
    block-builder .bb-row, block-builder .bb-tile, block-builder .bb-topbtn { transition: background-color .12s; }
}
`;

/**
 * What the builder adds to the page being edited, inside its frame, so blocks can be pointed at and selected.
 *
 * Nothing here changes how the page will look to a visitor: these rules exist only in the editor's copy.
 */
export const CANVAS_STYLE = `
body { margin: 0; padding: 1.25rem; font: 16px/1.5 system-ui, sans-serif; }
[data-block-id] { cursor: pointer; }
[data-block-id]:empty:not([data-block-setting]):not(hr) { min-height: 3rem; background: repeating-linear-gradient(-45deg, #f3f5f8 0 8px, #e9ecf1 8px 16px); }
[data-bb-over] { outline: 1px dashed #0f7b6c; outline-offset: -1px; }
[data-bb-picked] { outline: 2px solid #0f7b6c; outline-offset: -2px; }
[data-bb-typing] { outline: 2px solid #0f7b6c; outline-offset: 2px; cursor: text; }
[data-block-setting]:empty::before { content: 'Type here'; color: #8a93a3; }
[data-bb-drop="before"] { box-shadow: 0 -4px 0 #0f7b6c; }
[data-bb-drop="after"] { box-shadow: 0 4px 0 #0f7b6c; }
[data-bb-drop="before-x"] { box-shadow: -4px 0 0 #0f7b6c; }
[data-bb-drop="after-x"] { box-shadow: 4px 0 0 #0f7b6c; }
[data-bb-drop="inside"] { outline: 2px dashed #0f7b6c; outline-offset: -2px; background-color: #e3f3f0; }
.block-placeholder { min-height: 3rem; border: 1px dashed #b4281e; background: #fcebe9; }
`;
