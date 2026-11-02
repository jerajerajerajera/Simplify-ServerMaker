{{-- The server's ports, the main one first. --}}
@php($ports = value($ports))
<div class="smk-field-ports">
    <span class="smk-ports-label">{{ __('simplify-servermaker::maker.performance.ports') }}</span>
    <div class="smk-ports">
        @foreach ($ports as $index => $port)
            <span @class(['smk-port', 'is-main' => $index === 0])>{{ $port->port }}@if ($index === 0) · {{ __('simplify-servermaker::maker.performance.main') }}@endif</span>
        @endforeach
    </div>
</div>
