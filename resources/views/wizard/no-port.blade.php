{{-- Server step: the chosen node has no free port (allocation), so a server cannot be made there yet. --}}
<div class="smk-notice" role="alert">
    <x-tabler-plug-x class="smk-notice-icon" aria-hidden="true" />
    <div>
        <strong>{{ __('simplify-servermaker::maker.server.no_port', ['node' => $node]) }}</strong>
        <p>
            @if ($url)
                {{ __('simplify-servermaker::maker.server.no_port_admin') }}
                <a href="{{ $url }}">{{ __('simplify-servermaker::maker.server.add_port') }}</a>
            @else
                {{ __('simplify-servermaker::maker.server.no_port_user') }}
            @endif
        </p>
    </div>
</div>
