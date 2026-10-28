{{-- The Server Maker's own styles: cards and chips on top of Filament's colours, nothing else on the page changes. --}}
<style>
    .smk {
        --smk-line: var(--gray-200);
        --smk-muted: var(--gray-500);
        --smk-surface: var(--color-white, #fff);
        --smk-accent: var(--primary-600);
        --smk-tint: color-mix(in oklab, var(--primary-500) 8%, transparent);
    }
    .dark .smk {
        --smk-line: color-mix(in oklab, var(--color-white, #fff) 10%, transparent);
        --smk-muted: var(--gray-400);
        --smk-surface: color-mix(in oklab, var(--color-white, #fff) 3%, transparent);
        --smk-accent: var(--primary-400);
    }
    .smk-picker { display: grid; gap: 1.5rem; }
    .smk-group { display: grid; gap: .75rem; }
    .smk-group h3 { font-size: .875rem; font-weight: 600; }
    .smk-group-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    .smk-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
    @media (max-width: 900px) { .smk-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .smk-card {
        display: flex; align-items: flex-start; gap: .75rem; min-width: 0; padding: .875rem 1rem;
        border: 1px solid var(--smk-line); border-radius: .5rem; background: var(--smk-surface);
        text-align: start; cursor: pointer; transition: border-color .15s, background-color .15s;
    }
    .smk-card:hover { border-color: var(--smk-accent); }
    .smk-card:focus-visible { outline: 2px solid var(--smk-accent); outline-offset: 2px; }
    .smk-card.is-picked { border-color: var(--smk-accent); background: var(--smk-tint); box-shadow: inset 0 0 0 1px var(--smk-accent); }
    .smk-card:disabled { opacity: .6; cursor: wait; }
    .smk-card-icon { flex: none; width: 1.5rem; height: 1.5rem; color: var(--smk-accent); }
    .smk-card-text { display: grid; gap: .125rem; min-width: 0; }
    .smk-card-text strong { overflow: hidden; font-size: .875rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .smk-card-text small {
        display: -webkit-box; overflow: hidden; color: var(--smk-muted); font-size: .75rem; line-height: 1.4;
        -webkit-box-orient: vertical; -webkit-line-clamp: 2;
    }
    .smk-card-image { flex: none; width: 2.5rem; height: 2.5rem; border-radius: .375rem; object-fit: cover; }
    .smk-card-meta { color: var(--smk-muted); font-size: .75rem; }
    .smk-card-meta a { color: var(--smk-accent); }
    .smk-card:has(input:focus-visible) { outline: 2px solid var(--smk-accent); outline-offset: 2px; }
    .smk-sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
    .smk-search {
        display: flex; align-items: center; gap: .5rem; width: min(100%, 20rem); padding: .375rem .75rem;
        border: 1px solid var(--smk-line); border-radius: .5rem; background: var(--smk-surface);
    }
    .smk-search svg { flex: none; width: 1rem; height: 1rem; color: var(--smk-muted); }
    .smk-search input { width: 100%; min-width: 0; padding: 0; border: 0; background: transparent; font-size: .875rem; outline: none; box-shadow: none; }
    .smk-empty { color: var(--smk-muted); font-size: .875rem; }
    .smk-empty a, .smk-notice a { color: var(--smk-accent); text-decoration: underline; }
    .smk-more { justify-self: start; color: var(--smk-accent); font-size: .875rem; font-weight: 600; }
    .smk-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
    .smk-chip { position: relative; cursor: pointer; }
    .smk-chip input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .smk-chip span {
        display: block; padding: .375rem .875rem; border: 1px solid var(--smk-line); border-radius: .5rem;
        background: var(--smk-surface); font-size: .875rem; font-weight: 500;
    }
    .smk-chip:hover span { border-color: var(--smk-accent); }
    .smk-chip input:checked + span { border-color: var(--smk-accent); background: var(--smk-tint); color: var(--smk-accent); }
    .smk-chip input:focus-visible + span { outline: 2px solid var(--smk-accent); outline-offset: 2px; }
    .smk-notice {
        display: flex; gap: .75rem; padding: .875rem 1rem; border: 1px solid var(--warning-400, #f59e0b);
        border-radius: .5rem; background: color-mix(in oklab, var(--warning-400, #f59e0b) 10%, transparent); font-size: .875rem;
    }
    .smk-notice-icon { flex: none; width: 1.25rem; height: 1.25rem; color: var(--warning-600, #d97706); }
    .smk-notice p { margin-top: .25rem; color: var(--smk-muted); }
    .smk-review { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: 1rem 2rem; margin: 0; }
    .smk-review dt { color: var(--smk-muted); font-size: .75rem; }
    .smk-review dd { margin: .125rem 0 0; font-size: .875rem; font-weight: 500; overflow-wrap: anywhere; }
    /* Back and Next stay in reach at the bottom of the screen, whatever the length of a step. */
    .smk .fi-sc-wizard-footer {
        position: sticky; bottom: 0; z-index: 10; padding-block: .75rem;
        border-top: 1px solid var(--smk-line); background: var(--smk-bar, var(--color-white, #fff));
    }
    .dark .smk .fi-sc-wizard-footer { --smk-bar: var(--gray-900); }
    @media (max-width: 600px) {
        .smk .fi-sc-wizard-footer { flex-wrap: nowrap; padding-bottom: calc(.75rem + env(safe-area-inset-bottom)); }
        .smk .fi-sc-wizard-footer > * { flex: 1 1 0; }
        .smk .fi-sc-wizard-footer .fi-btn { justify-content: center; width: 100%; min-height: 2.75rem; }
        .smk-cards { grid-template-columns: minmax(0, 1fr); }
        .smk-search { width: 100%; }
    }
    .smk-card-image { flex: none; width: 2.5rem; height: 2.5rem; border-radius: .375rem; object-fit: cover; }
    .smk-card-meta { color: var(--smk-muted); font-size: .75rem; }
    .smk-card-meta a { color: var(--smk-accent); }
    .smk-card:has(input:focus-visible) { outline: 2px solid var(--smk-accent); outline-offset: 2px; }
    .smk-sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
