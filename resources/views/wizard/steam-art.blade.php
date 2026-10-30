{{-- Server step: the Steam art that is copied once after the server is made. The images load from Steam's CDN in the
     browser; when Steam cannot be reached the preview stays hidden, and the copy afterwards simply skips them. --}}
<div class="smk-steam" x-data="{ ready: false, failed: false }" x-show="ready && ! failed" x-cloak>
    <img class="smk-steam-banner" src="{{ $art['header'] }}" alt="{{ __('simplify-servermaker::maker.server.steam_banner', ['name' => $art['name']]) }}" loading="lazy" referrerpolicy="no-referrer" x-on:load="ready = true" x-on:error="failed = true">
    <span class="smk-steam-icon"><img src="{{ $art['capsule'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.src = @js($art['header'])"></span>
</div>
