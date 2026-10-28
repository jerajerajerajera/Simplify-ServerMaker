{{-- A short list of names as a row of chips, for the software of a Minecraft type. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="smk-chips" role="radiogroup" aria-label="{{ $getLabel() }}">
        @foreach ($getOptions() as $value => $label)
            <label class="smk-chip" wire:key="{{ $getStatePath() }}-{{ $value }}">
                <input type="radio" name="{{ $getStatePath() }}" value="{{ $value }}"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" />
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
