{{-- Review → Edit settings: hidden with CSS while closed, so the fields stay mounted and keep their values. --}}
<div class="smk-advanced" @if (! $this->editingAdvanced) hidden @endif>
    {{ $getChildSchema() }}
</div>
