{{-- The server's limits now, as three numbers; 0 is unlimited in Pelican. --}}
@php
    $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.' . $key, $replace);
    $gb = fn (int $mb) => $mb > 0 ? $t('resources.gb', ['amount' => \Simplify\ServerMaker\Creation\Resources::gb($mb)]) : $t('performance.unlimited');
@endphp
<dl class="smk-limits">
    <div><dt>{{ $t('resources.cpu') }}</dt><dd>{{ (int) $server->cpu > 0 ? $server->cpu . ' %' : $t('performance.unlimited') }}</dd></div>
    <div><dt>{{ $t('resources.ram') }}</dt><dd>{{ $gb((int) $server->memory) }}</dd></div>
    <div><dt>{{ $t('resources.disk') }}</dt><dd>{{ $gb((int) $server->disk) }}</dd></div>
</dl>
