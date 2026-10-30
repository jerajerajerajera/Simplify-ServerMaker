{{-- The Server Maker's own styles: cards and chips on top of Filament's colours, nothing else on the page changes. --}}
<style>
    /* The Server Maker as it looked in Simplify 0.7.0: one card, the steps and the draft in one bar at the bottom.
       Simplify's colours (--ps-*) when it is installed, Pelican's otherwise. */
    .smk {
        --smk-line: var(--ps-line, var(--gray-200));
        --smk-muted: var(--ps-muted, var(--gray-500));
        --smk-ink: var(--ps-ink, var(--gray-950));
        --smk-surface: var(--ps-surface, var(--color-white, #fff));
        --smk-raised: var(--ps-raised, var(--gray-100));
        --smk-accent: var(--ps-accent, var(--primary-600));
        --smk-on-accent: var(--ps-on-accent, #fff);
        --smk-danger: var(--ps-danger, var(--danger-600, #dc2626));
        --smk-ok: var(--ps-ok, var(--success-600, #16a34a));
        --smk-radius: var(--ps-radius, 10px);
        --smk-radius-lg: var(--ps-radius-lg, 14px);
        --smk-radius-xl: var(--ps-radius-xl, 18px);
        --smk-bar: 64px;
        --smk-ctl: 40px;
        --smk-inner: 32px;
        --smk-gap: 8px;
        --smk-left: calc(16px + 104px + var(--smk-gap));
        --smk-right: calc(16px + 140px + var(--smk-gap));
        position: relative;
        color: var(--smk-ink);
    }
    .dark .smk {
        --smk-line: var(--ps-line, color-mix(in oklab, var(--color-white, #fff) 10%, transparent));
        --smk-muted: var(--ps-muted, var(--gray-400));
        --smk-ink: var(--ps-ink, var(--gray-50));
        --smk-surface: var(--ps-surface, var(--gray-900));
        --smk-raised: var(--ps-raised, var(--gray-800));
        --smk-accent: var(--ps-accent, var(--primary-400));
    }
    .smk .smk-sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    .smk .smk-tile { display: grid; flex: none; place-items: center; width: 36px; height: 36px; border-radius: 9px; background: var(--smk-raised); color: var(--smk-muted); }
    .smk .smk-tile svg { width: 19px; height: 19px; }

    /* Filament's numbered step header makes way for the steps in the bar at the bottom. */
    .smk .fi-sc-wizard-header { display: none !important; }
    .smk .fi-sc-wizard { overflow: clip; border-radius: var(--smk-radius-lg); background: var(--smk-surface); }

    /* Game step: Minecraft on top, with its server types; picking one moves on. */
    .smk .smk-picker { display: grid; gap: 18px; }
    .smk .smk-minecraft { display: grid; gap: 14px; padding: 18px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius-xl); background: linear-gradient(120deg, color-mix(in srgb, #3f9b4b 14%, var(--smk-surface)), var(--smk-surface) 60%); }
    .smk .smk-minecraft.is-picked { border-color: var(--smk-accent); }
    .smk a.smk-link { color: var(--smk-accent); text-decoration: underline; }
    .smk .smk-minecraft-head { display: flex; align-items: center; gap: 14px; }
    .smk .smk-minecraft-head h3 { margin: 0; font-size: 20px; font-weight: 700; }
    .smk .smk-minecraft-head p { margin: 2px 0 0; color: var(--smk-muted); font-size: 13px; }
    .smk .smk-minecraft-icon { display: grid; flex: none; place-items: center; width: 56px; height: 56px; border-radius: 14px; background: #3f9b4b; color: #fff; }
    .smk .smk-minecraft-icon svg { width: 34px; height: 34px; }
    .smk .smk-minecraft-types { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
    .smk .smk-minecraft-type { display: grid; align-content: start; gap: 4px; min-height: 92px; padding: 14px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius-lg); background: color-mix(in srgb, var(--smk-ink) 4%, var(--smk-surface)); color: var(--smk-ink); text-align: start; cursor: pointer; transition: border-color .15s, background-color .15s, transform .15s; }
    .smk .smk-minecraft-type:hover:not(:disabled) { border-color: var(--smk-accent); background: color-mix(in srgb, var(--smk-ink) 8%, var(--smk-surface)); transform: translateY(-1px); }
    .smk .smk-minecraft-type[aria-pressed="true"] { border-color: var(--smk-accent); background: color-mix(in srgb, var(--smk-accent) 9%, var(--smk-surface)); box-shadow: 0 0 0 1px var(--smk-accent); }
    .smk .smk-minecraft-type:disabled { cursor: progress; }
    .smk .smk-minecraft-type strong { font-size: 15px; font-weight: 650; }
    .smk .smk-minecraft-type small { color: var(--smk-muted); font-size: 12px; line-height: 1.45; }

    /* Every other game: a search and a grid of games with their initials. */
    .smk .smk-games { display: grid; gap: 12px; }
    .smk .smk-games-heading { margin: 0; color: var(--smk-muted); font-size: 13px; font-weight: 650; letter-spacing: .05em; text-transform: uppercase; }
    .smk .smk-search { position: relative; display: block; }
    .smk .smk-search svg { position: absolute; top: 50%; inset-inline-start: 12px; width: 18px; height: 18px; color: var(--smk-muted); transform: translateY(-50%); pointer-events: none; }
    .smk .smk-search input { width: 100%; height: 42px; padding: 0 12px 0 38px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius); background: var(--smk-surface); color: var(--smk-ink); font-size: 14px; font-weight: 500; box-shadow: none; }
    .smk .smk-search input:focus { border-color: var(--smk-accent); outline: 2px solid color-mix(in srgb, var(--smk-accent) 45%, transparent); }
    .smk .smk-games-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px; }
    .smk .smk-game { display: grid; grid-template-columns: 42px minmax(0, 1fr); align-items: center; gap: 4px 12px; min-height: 74px; padding: 12px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius-lg); background: color-mix(in srgb, var(--smk-ink) 4%, var(--smk-surface)); color: var(--smk-ink); text-align: start; cursor: pointer; transition: border-color .15s, background-color .15s, transform .15s; }
    .smk .smk-game:hover { border-color: color-mix(in srgb, var(--smk-accent) 55%, var(--smk-line)); background: color-mix(in srgb, var(--smk-ink) 8%, var(--smk-surface)); transform: translateY(-1px); }
    .smk .smk-game[aria-checked="true"] { border-color: var(--smk-accent); background: color-mix(in srgb, var(--smk-accent) 9%, var(--smk-surface)); box-shadow: 0 0 0 1px var(--smk-accent); }
    .smk .smk-game-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 11px; background: hsl(var(--hue) 55% 92%); color: hsl(var(--hue) 45% 32%); font-size: 15px; font-weight: 800; letter-spacing: .02em; }
    .dark .smk-game-icon { background: hsl(var(--hue) 35% 22%); color: hsl(var(--hue) 70% 80%); }
    .smk .smk-game-text { display: grid; gap: 3px; min-width: 0; }
    .smk .smk-game-text strong { overflow: hidden; font-size: 14px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
    .smk .smk-game-text small { display: -webkit-box; overflow: hidden; color: var(--smk-muted); font-size: 12px; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
    .smk .smk-games { position: relative; }
    .smk .smk-games-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .smk .smk-games-bar .smk-search { flex: 1 1 260px; }
    .smk .smk-chips { display: flex; gap: 4px; padding: 3px; border-radius: var(--smk-radius); background: color-mix(in srgb, var(--smk-ink) 5%, var(--smk-surface)); }
    .smk .smk-chips button { min-height: 34px; padding: 0 12px; border: 0; border-radius: calc(var(--smk-radius) - 2px); background: transparent; color: var(--smk-muted); font-size: 13px; font-weight: 600; cursor: pointer; }
    .smk .smk-chips button[aria-pressed="true"] { background: var(--smk-surface); color: var(--smk-ink); box-shadow: 0 1px 2px rgb(0 0 0 / .12); }
    .smk .smk-game { position: relative; }
    .smk .smk-games-grid:has(.smk-game-badge) .smk-game-text { padding-inline-end: 64px; }
    .smk .smk-game-badge { position: absolute; top: 10px; inset-inline-end: 10px; padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 650; line-height: 1.5; }
    .smk .smk-game-badge.is-installed { background: color-mix(in srgb, var(--smk-ok) 16%, transparent); color: var(--smk-ok); }
    .smk .smk-game-badge.is-available { background: color-mix(in srgb, var(--smk-accent) 14%, transparent); color: var(--smk-accent); }
    .smk .smk-games-note { margin: 0; padding: 10px 12px; border-radius: var(--smk-radius); background: color-mix(in srgb, var(--smk-accent) 8%, var(--smk-surface)); color: var(--smk-ink); font-size: 13px; }
    .smk .smk-install { position: absolute; inset: -8px; z-index: 5; display: grid; align-content: center; justify-items: center; gap: 12px; padding: 24px; border-radius: var(--smk-radius-lg); background: color-mix(in srgb, var(--smk-surface) 94%, transparent); backdrop-filter: blur(3px); text-align: center; }
    .smk .smk-install strong { font-size: 18px; }
    .smk .smk-install ol { display: grid; gap: 8px; margin: 4px 0; padding: 0; list-style: none; text-align: start; }
    .smk .smk-install li { position: relative; padding-inline-start: 26px; color: var(--smk-muted); font-size: 14px; }
    .smk .smk-install li::before { content: ""; position: absolute; top: 3px; inset-inline-start: 0; width: 14px; height: 14px; border: 2px solid var(--smk-line); border-radius: 50%; }
    .smk .smk-install li.is-now { color: var(--smk-ink); font-weight: 600; }
    .smk .smk-install li.is-now::before { border-color: var(--smk-accent); border-inline-end-color: transparent; animation: smk-spin .8s linear infinite; }
    .smk .smk-install li.is-done::before { border-color: var(--smk-ok); background: var(--smk-ok); }
    .smk .smk-install small { color: var(--smk-muted); }
    .smk .smk-install-spinner { width: 40px; height: 40px; border: 3px solid color-mix(in srgb, var(--smk-accent) 25%, transparent); border-top-color: var(--smk-accent); border-radius: 50%; animation: smk-spin .9s linear infinite; }
    @keyframes smk-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .smk .smk-install-spinner, .smk .smk-install li.is-now::before { animation-duration: 3s; } }
    .smk .smk-empty { color: var(--smk-muted); font-size: 13px; }
    .smk .smk-empty a, .smk .smk-notice a { color: var(--smk-accent); text-decoration: underline; }
    .smk .smk-more { justify-self: center; min-height: 36px; padding: 0 16px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius); background: var(--smk-surface); color: var(--smk-ink); font-size: 13px; font-weight: 600; }

    /* Minecraft step: the server types as cards with an icon, the software as chips. */
    .smk .smk-types { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .smk .smk-type, .smk .smk-amount { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 14px 16px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius-lg); background: color-mix(in srgb, var(--smk-ink) 4%, var(--smk-surface)); color: var(--smk-ink); text-align: start; cursor: pointer; transition: border-color .15s, background-color .15s; }
    .smk .smk-type:hover:not(:disabled), .smk .smk-amount:hover { border-color: var(--smk-accent); }
    .smk :is(.smk-type, .smk-amount).is-picked, .smk .smk-amount:has(input:checked) { border-color: var(--smk-accent); background: color-mix(in srgb, var(--smk-accent) 7%, var(--smk-surface)); box-shadow: 0 0 0 1px var(--smk-accent); }
    .smk :is(.smk-type, .smk-amount).is-picked .smk-tile, .smk .smk-amount:has(input:checked) .smk-tile { color: var(--smk-ink); }
    .smk .smk-amount:has(input:focus-visible), .smk .smk-type:focus-visible { outline: 2px solid var(--smk-accent); outline-offset: 2px; }
    .smk .smk-type:disabled { cursor: progress; }
    .smk .smk-type-text { display: grid; gap: 2px; min-width: 0; }
    .smk .smk-type-text strong { font-size: 15px; font-weight: 650; }
    .smk .smk-type-text small { color: var(--smk-muted); font-size: 12.5px; line-height: 1.4; }
    .smk .smk-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .smk .smk-chip { position: relative; cursor: pointer; }
    .smk .smk-chip input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .smk .smk-chip span { display: grid; place-items: center; min-height: var(--smk-ctl); padding: 0 16px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius); background: var(--smk-surface); font-size: 14px; font-weight: 600; transition: border-color .15s, background-color .15s; }
    .smk .smk-chip:hover span { border-color: var(--smk-accent); }
    .smk .smk-chip input:checked + span { border-color: var(--smk-accent); background: color-mix(in srgb, var(--smk-accent) 7%, var(--smk-surface)); box-shadow: 0 0 0 1px var(--smk-accent); }
    .smk .smk-chip input:focus-visible + span { outline: 2px solid var(--smk-accent); outline-offset: 2px; }

    /* Resources: RAM, CPU and disk in one grid of cards of the same size, a small icon next to the amount. */
    .smk .smk-amounts { display: grid; grid-template-columns: repeat(auto-fill, minmax(128px, 1fr)); gap: 8px; }
    .smk .smk-amount { position: relative; gap: 8px; height: 44px; padding: 0 12px; border-radius: var(--smk-radius); }
    .smk .smk-amount strong { overflow: hidden; font-size: 14px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .smk .smk-amount-icon { flex: none; width: 16px; height: 16px; color: var(--smk-muted); }
    .smk .smk-amount.is-picked .smk-amount-icon, .smk .smk-amount:has(input:checked) .smk-amount-icon { color: var(--smk-accent); }

    /* Modpacks. */
    .smk .smk-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .smk .smk-card { display: flex; align-items: flex-start; gap: 12px; min-width: 0; padding: 14px 16px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius-lg); background: var(--smk-surface); text-align: start; cursor: pointer; transition: border-color .15s, background-color .15s; }
    .smk .smk-card:hover { border-color: var(--smk-accent); }
    .smk .smk-card.is-picked, .smk .smk-card:has(input:checked) { border-color: var(--smk-accent); background: color-mix(in srgb, var(--smk-accent) 7%, var(--smk-surface)); box-shadow: 0 0 0 1px var(--smk-accent); }
    .smk .smk-card:has(input:focus-visible) { outline: 2px solid var(--smk-accent); outline-offset: 2px; }
    .smk .smk-card-icon { flex: none; width: 1.5rem; height: 1.5rem; color: var(--smk-muted); }
    .smk .smk-card-image { flex: none; width: 2.5rem; height: 2.5rem; border-radius: .5rem; object-fit: cover; }
    .smk .smk-card-text { display: grid; gap: 2px; min-width: 0; }
    .smk .smk-card-text strong { overflow: hidden; font-size: 14px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
    .smk .smk-card-text small { display: -webkit-box; overflow: hidden; color: var(--smk-muted); font-size: 12px; line-height: 1.4; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
    .smk .smk-card-meta { color: var(--smk-muted); font-size: 12px; }
    .smk .smk-card-meta a { color: var(--smk-accent); }
    .smk .smk-notice { display: flex; gap: .75rem; padding: .875rem 1rem; border: 1px solid var(--warning-400, #f59e0b); border-radius: var(--smk-radius); background: color-mix(in oklab, var(--warning-400, #f59e0b) 10%, transparent); font-size: .875rem; }
    .smk .smk-notice-icon { flex: none; width: 1.25rem; height: 1.25rem; color: var(--warning-600, #d97706); }
    .smk .smk-notice p { margin-top: .25rem; color: var(--smk-muted); }

    /* Review: the server overview. */
    .smk .smk-overview header { padding-bottom: 16px; border-bottom: 1px solid var(--smk-line); }
    .smk .smk-overview h3 { font-size: 15px; font-weight: 650; }
    .smk .smk-overview header p { margin-top: 6px; color: var(--smk-muted); font-size: 13px; }
    .smk .smk-review { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 40px; margin: 0; padding-top: 18px; }
    .smk .smk-review dt { color: var(--smk-muted); font-size: 13px; }
    .smk .smk-overview footer { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--smk-line); }
    .smk .smk-advanced { margin-top: 4px; }
    .smk .smk-advanced[hidden] { display: none !important; }
    .smk .smk-review dd { margin: 4px 0 0; font-size: 15px; font-weight: 500; overflow-wrap: anywhere; }

    /* The bar at the bottom: Back and Next are the wizard's own footer; the steps and the draft lie on top of it. */
    .smk .fi-sc-wizard-footer { position: sticky; bottom: 0; z-index: 10; display: flex; flex-wrap: nowrap; align-items: center; gap: var(--smk-gap); height: var(--smk-bar); margin: 0 !important; padding: 0 16px !important; border-top: 1px solid var(--smk-line); background: var(--smk-surface); }
    .smk .fi-sc-wizard-footer > :is(:nth-child(3), :nth-child(4)) { margin-inline-start: auto; }
    .smk .fi-sc-wizard-footer .fi-btn { box-sizing: border-box; min-width: 104px; height: var(--smk-ctl); justify-content: center; border-radius: var(--smk-radius); font-size: 14px; }
    .smk .fi-sc-wizard-footer .fi-btn.smk-create { min-width: 140px; }
    .smk .fi-sc-wizard-footer .fi-btn:is(.smk-next, .smk-back) { border: 1px solid var(--smk-line); background: var(--smk-surface); color: var(--smk-ink); box-shadow: none; }
    .smk .fi-sc-wizard-footer .fi-btn:is(.smk-next, .smk-back):hover:not(:disabled) { border-color: var(--smk-accent); background: var(--smk-raised); }
    .smk .fi-sc-wizard-footer .fi-btn.smk-create:not(:disabled) { border: 1px solid var(--smk-accent); background: var(--smk-accent); color: var(--smk-on-accent); }
    .smk .smk-navbar { position: sticky; bottom: 0; z-index: 11; box-sizing: border-box; display: flex; align-items: center; gap: var(--smk-gap); height: var(--smk-bar); margin-top: calc(-1 * var(--smk-bar)); padding: 0 var(--smk-right) 0 var(--smk-left); pointer-events: none; }
    .smk .smk-navbar > * { pointer-events: auto; }
    .smk .smk-steps, .smk .smk-draft { box-sizing: border-box; display: flex; align-items: center; height: var(--smk-ctl); border: 1px solid var(--smk-line); border-radius: var(--smk-radius); background: var(--smk-surface); }
    .smk .smk-steps { position: relative; flex: 0 1 auto; gap: 2px; min-width: 0; margin: 0 auto; padding: 0 3px; overflow: hidden; }
    .smk .smk-step { display: flex; flex: none; align-items: center; gap: 7px; height: var(--smk-inner); padding: 0 10px 0 4px; border-radius: calc(var(--smk-radius) - 3px); color: var(--smk-muted); font-size: 12px; font-weight: 500; white-space: nowrap; }
    .smk .smk-step > span { display: grid; flex: none; place-items: center; width: 24px; height: 24px; border-radius: calc(var(--smk-radius) - 5px); background: var(--smk-raised); font-size: 11px; font-weight: 700; }
    .smk .smk-step b { font: inherit; }
    .smk .smk-step.is-done > span { background: color-mix(in srgb, var(--smk-accent) 20%, var(--smk-surface)); color: var(--smk-ink); }
    .smk .smk-step.is-current { background: var(--smk-raised); color: var(--smk-ink); font-weight: 650; }
    .smk .smk-step.is-current > span { background: var(--smk-accent); color: var(--smk-on-accent); }
    .smk .smk-step:disabled { cursor: default; opacity: .5; }
    .smk .smk-step:focus-visible { outline: 2px solid var(--smk-accent); outline-offset: -2px; }
    .smk .smk-steps.is-compact .smk-step:not(.is-current) { padding: 0 4px; }
    .smk .smk-steps.is-compact .smk-step:not(.is-current) b { display: none; }
    .smk .smk-steps-phone, .smk .smk-steps-menu { display: none; }
    .smk .smk-draft { flex: none; gap: 2px; padding: 0 3px 0 12px; }
    .smk .smk-draft-status { margin-inline-end: 2px; font-size: 13px; font-weight: 600; white-space: nowrap; }
    .smk .smk-draft-icon { width: 20px; height: 20px; margin: 0 6px; color: var(--smk-muted); }
    .smk .smk-discard, .smk .smk-discard-confirm button { display: inline-flex; align-items: center; gap: 6px; height: var(--smk-inner); padding: 0 10px; border-radius: calc(var(--smk-radius) - 3px); color: var(--smk-ink); font-size: 13px; font-weight: 600; }
    .smk .smk-discard svg, .smk .smk-discard-confirm svg { width: 15px; height: 15px; }
    .smk .smk-discard:hover { background: var(--smk-raised); color: var(--smk-danger); }
    .smk .smk-discard-confirm { display: inline-flex; gap: 2px; }
    .smk .smk-discard-confirm button { justify-content: center; width: var(--smk-inner); padding: 0; }
    .smk .smk-discard-confirm button:hover { background: var(--smk-raised); }
    .smk .smk-discard-confirm button.is-danger { color: var(--smk-danger); }

    /* The wizard is one box that fits the window (--smk-fit, measured below): only the step scrolls, never the page. */
    html.smk-fit .smk .fi-sc-wizard { display: flex; flex-direction: column; height: var(--smk-fit); }
    html.smk-fit .smk .fi-sc-wizard-footer { flex: none; }
    html.smk-fit .smk .fi-sc-wizard-step { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }

    @media (max-width: 900px) {
        .smk .smk-types, .smk .smk-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 600px) {
        .smk { --smk-gap: 6px; --smk-bar: 60px; --smk-left: calc(10px + 72px + var(--smk-gap)); --smk-right: calc(10px + 118px + var(--smk-gap)); }
        .smk .fi-sc-wizard-footer { padding: 0 10px env(safe-area-inset-bottom) !important; }
        .smk .fi-sc-wizard-footer .fi-btn { min-width: 72px; padding: 0 12px; }
        .smk .fi-sc-wizard-footer .fi-btn.smk-create { min-width: 118px; }
        .smk .smk-minecraft { padding: 14px; }
        .smk .smk-minecraft-types { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .smk .smk-minecraft-type { min-height: 0; }
        .smk .smk-types, .smk .smk-cards, .smk .smk-review { grid-template-columns: minmax(0, 1fr); }
        .smk .smk-amounts { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .smk .smk-steps { flex: 1 1 auto; padding: 0; overflow: visible; }
        .smk .smk-steps > .smk-step { display: none; }
        .smk .smk-steps-phone { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; width: 100%; height: 100%; min-width: 0; padding: 0 4px; border-radius: var(--smk-radius); }
        .smk .smk-steps-phone strong { max-width: 100%; overflow: hidden; font-size: 13px; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
        .smk .smk-steps-phone strong::after { content: " ▾"; color: var(--smk-muted); font-size: 10px; }
        .smk .smk-steps-phone small { color: var(--smk-muted); font-size: 11px; line-height: 1.2; }
        .smk .smk-steps-menu { position: absolute; bottom: calc(100% + 14px); left: 50%; display: grid; gap: 2px; width: min(260px, 80vw); padding: 6px; border: 1px solid var(--smk-line); border-radius: var(--smk-radius-lg); background: var(--smk-surface); box-shadow: 0 16px 40px -12px rgb(0 0 0 / .35); transform: translateX(-50%); }
        .smk .smk-steps-menu[x-cloak] { display: none; }
        .smk .smk-steps-menu button { display: flex; align-items: center; gap: 10px; min-height: 44px; padding: 6px 10px 6px 6px; border-radius: var(--smk-radius); font-size: 14px; text-align: start; }
        .smk .smk-steps-menu button > span { display: grid; flex: none; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: var(--smk-raised); font-size: 12px; font-weight: 700; }
        .smk .smk-steps-menu button.is-current { background: var(--smk-raised); font-weight: 650; }
        .smk .smk-steps-menu button.is-current > span { background: var(--smk-accent); color: var(--smk-on-accent); }
        .smk .smk-steps-menu button:disabled { opacity: .45; }
        .smk .smk-draft { padding: 0 3px; }
        .smk .smk-draft-status, .smk .smk-discard > span { display: none; }
        .smk .smk-draft-icon { margin: 0 4px; }
    }
    /* The header button (Settings) as Simplify icon buttons: a quiet bordered square, the icon turns the
       accent colour on hover. Simplify's colours when it is installed, Pelican's otherwise. */
    .fi-page:has(.smk) .fi-header-actions-ctn .fi-icon-btn { display: inline-grid; place-items: center; width: var(--s-h, 36px); height: var(--s-h, 36px); margin: 0; border: 1px solid var(--s-line, var(--gray-200)); border-radius: var(--s-radius-sm, 6px); background: var(--s-surface, #fff); color: var(--s-ink, var(--gray-950)); transition: background .15s, border-color .15s, color .15s; }
    .dark .fi-page:has(.smk) .fi-header-actions-ctn .fi-icon-btn { border-color: var(--s-line, var(--gray-700)); background: var(--s-surface, var(--gray-900)); color: var(--s-ink, var(--gray-50)); }
    .fi-page:has(.smk) .fi-header-actions-ctn .fi-icon-btn .fi-icon { width: 18px; height: 18px; color: inherit; }
    .fi-page:has(.smk) .fi-header-actions-ctn .fi-icon-btn:hover { border-color: var(--s-accent, var(--primary-600)); background: var(--s-raised, var(--gray-100)); color: var(--s-accent, var(--primary-600)); }
    .dark .fi-page:has(.smk) .fi-header-actions-ctn .fi-icon-btn:hover { border-color: var(--s-accent, var(--primary-500)); background: var(--s-raised, var(--gray-800)); color: var(--s-accent, var(--primary-500)); }
</style>
<script>
    // Height left under the wizard's top edge, so the page itself never scrolls; a very low window keeps the page scroll.
    (() => {
        const fit = () => {
            const wizard = document.querySelector('.smk .fi-sc-wizard');
            const root = document.documentElement;
            if (!wizard) return root.classList.remove('smk-fit');
            const height = Math.floor(innerHeight - (wizard.getBoundingClientRect().top + scrollY) - 16);
            root.classList.toggle('smk-fit', height >= 320);
            root.style.setProperty('--smk-fit', height + 'px');
            // Whatever the page still has below the wizard (its bottom padding) comes off too.
            const extra = root.scrollHeight - innerHeight;
            if (extra > 0 && height - extra >= 320) root.style.setProperty('--smk-fit', (height - extra) + 'px');
        };
        addEventListener('resize', fit);
        document.addEventListener('livewire:navigated', fit);
        requestAnimationFrame(fit);
        // Announcements and Simplify's top bar may arrive a moment later and push the wizard down.
        setTimeout(fit, 400);
        setTimeout(fit, 1500);
    })();
</script>
