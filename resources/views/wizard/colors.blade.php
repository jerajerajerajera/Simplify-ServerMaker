{{-- Server step: the server's colour in Simplify, as round swatches like Simplify's own colour menu. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="smk-colors" role="radiogroup" aria-label="{{ $getLabel() }}">
        @foreach ($getOptions() as $value => $label)
            <label class="smk-color" style="--smk-color: {{ $value }}" title="{{ $label }}" wire:key="{{ $getStatePath() }}-{{ $value }}">
                <input type="radio" name="{{ $getStatePath() }}" value="{{ $value }}" aria-label="{{ $label }}"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" />
            </label>
        @endforeach
    </div>
</x-dynamic-component>
