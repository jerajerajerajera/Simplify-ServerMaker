{{-- Review step: the server overview, two columns of what will be made. --}}
<section class="smk-overview" aria-labelledby="smk-overview">
    <header>
        <h3 id="smk-overview">{{ __('simplify-servermaker::maker.review.overview') }}</h3>
        <p>{{ __('simplify-servermaker::maker.review.overview_help') }}</p>
    </header>
    <dl class="smk-review">
        @foreach ($rows as $label => $value)
            <div>
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    @if ($this->isAdmin())
        <footer>
            <button type="button" class="smk-btn" wire:click="toggleAdvanced">
                <x-filament::icon icon="tabler-adjustments" />
                {{ __('simplify-servermaker::maker.review.'.($this->editingAdvanced ? 'done_editing' : 'edit_settings')) }}
            </button>
        </footer>
    @endif
</section>
