{{-- One row: icon, what is connected, the add-on it is for, and its buttons (Disconnect in the ⋯ menu). --}}
<div class="smk-connection">
    <span class="smk-connection-icon"><x-filament::icon icon="tabler-brand-steam" /></span>
    <div class="smk-connection-text">
        <strong>{{ $title }}</strong>
        <span>{{ $line }}</span>
        @if ($auto)
            <span>{{ $auto }}</span>
        @endif
    </div>
    <span class="smk-connection-addon">{{ $addon }}</span>
    {{ $this->connectAction }}
    @if ($connected)
        <x-filament-actions::group :actions="[$this->disconnectAction]" icon="tabler-dots" color="gray" />
    @endif

    <x-filament-actions::modals />
    <style>
        .smk-connection { display: flex; align-items: center; gap: 16px; padding: 16px 18px; border: 1px solid var(--gray-200); border-radius: 12px; }
        .dark .smk-connection { border-color: var(--gray-800); }
        .smk-connection-icon { display: grid; place-items: center; flex: none; width: 40px; height: 40px; border-radius: 10px; background: var(--gray-100); }
        .dark .smk-connection-icon { background: var(--gray-800); }
        .smk-connection-icon svg { width: 22px; height: 22px; }
        .smk-connection-text { display: flex; flex: 1; min-width: 0; flex-direction: column; gap: 3px; font-size: 13px; color: var(--gray-500); }
        .smk-connection-text strong { font-size: 15px; color: var(--gray-950); }
        .dark .smk-connection-text strong { color: #fff; }
        .smk-connection-addon { font-size: 12px; color: var(--gray-500); }
        @media (max-width: 600px) { .smk-connection { flex-wrap: wrap; } .smk-connection-text { flex-basis: calc(100% - 56px); } .smk-connection-addon { flex: 1; } }
    </style>
</div>
