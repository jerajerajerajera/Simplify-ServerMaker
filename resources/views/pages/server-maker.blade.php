<x-filament-panels::page>
    @include('simplify-servermaker::wizard.styles')
    @php
        $labels = $this->stepLabels();
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.'.$key, $replace);
    @endphp
    {{-- The steps in the bar follow Filament's wizard (its Alpine data on .fi-sc-wizard): read on clicks and every second,
         as Livewire may replace the wizard element. Inline, so it works after wire:navigate too. --}}
    <div class="smk" x-data="{
            step: {{ $this->step }},
            furthest: {{ $this->step }},
            busy: false,
            menu: false,
            confirming: false,
            timer: null,
            wizard() { const el = this.$root.querySelector('.fi-sc-wizard'); return el ? Alpine.$data(el) : null; },
            current() {
                try { const w = this.wizard(); const i = w.getStepIndex(w.step); return Number.isInteger(i) && i >= 0 ? i + 1 : this.step; } catch { return this.step; }
            },
            sync() { this.step = this.current(); this.furthest = Math.max(this.furthest, this.step); },
            goToStep(target) {
                this.sync();
                if (target < 1 || target > this.furthest || this.busy) return;
                const w = this.wizard();
                const steps = w?.getSteps?.() ?? [];
                const chosen = steps[target - 1];
                const value = typeof chosen === 'string' ? chosen : chosen?.value;
                if (!value) return;
                w.step = value;
                this.step = target;
            },
            fitSteps(nav) { nav.classList.remove('is-compact'); if (nav.scrollWidth > nav.clientWidth + 1) nav.classList.add('is-compact'); },
            init() {
                const soon = () => setTimeout(() => this.sync(), 60);
                this.$root.addEventListener('click', soon);
                window.addEventListener('next-wizard-step', soon);
                this.timer = setInterval(() => this.sync(), 1000);
            },
            destroy() { clearInterval(this.timer); },
        }">
        <form wire:submit="create">
            {{ $this->form }}
        </form>
        {{-- One bar at the bottom: Back and Next (the wizard footer underneath), with the steps and the draft in between. --}}
        <div class="smk-navbar">
            <nav class="smk-steps" aria-label="{{ $t('steps.label') }}" style="--smk-steps: {{ count($labels) }}" :style="{ '--smk-step': step }"
                x-init="$nextTick(() => fitSteps($el)); new ResizeObserver(() => fitSteps($el)).observe($el)" x-effect="step; $nextTick(() => fitSteps($el))"
                x-on:keydown.escape="menu = false" x-on:click.outside="menu = false">
                @foreach ($labels as $index => $label)
                    <button type="button" class="smk-step" :class="{ 'is-current': step === {{ $index + 1 }}, 'is-done': furthest > {{ $index + 1 }} }"
                        :disabled="{{ $index + 1 }} > furthest || busy" :aria-current="step === {{ $index + 1 }} ? 'step' : null"
                        x-on:click="goToStep({{ $index + 1 }})" title="{{ $label }}"><span>{{ $index + 1 }}</span><b>{{ $label }}</b></button>
                @endforeach
                {{-- Phones: the current step; tapping it lists every step to jump to. --}}
                <button type="button" class="smk-steps-phone" x-on:click="menu = !menu" :aria-expanded="menu" aria-haspopup="true">
                    <strong x-text="@js($labels)[step - 1]"></strong>
                    <small x-text="@js($t('steps.step_of')).replace(':step', step).replace(':total', {{ count($labels) }})"></small>
                </button>
                <div class="smk-steps-menu" x-show="menu" x-cloak x-transition.opacity role="menu">
                    @foreach ($labels as $index => $label)
                        <button type="button" role="menuitem" :class="{ 'is-current': step === {{ $index + 1 }}, 'is-done': furthest > {{ $index + 1 }} }"
                            :disabled="{{ $index + 1 }} > furthest || busy" x-on:click="menu = false; goToStep({{ $index + 1 }})"><span>{{ $index + 1 }}</span>{{ $label }}</button>
                    @endforeach
                </div>
            </nav>
            @if ($hasDraft)
                <div class="smk-draft" role="group" aria-label="{{ $t('draft.label') }}">
                    <span class="smk-draft-status" role="status">{{ $t('draft.saved') }}</span>
                    <x-tabler-device-floppy class="smk-draft-icon" aria-hidden="true" />
                    <button type="button" class="smk-discard" x-show="!confirming" x-on:click="confirming = true">
                        <x-tabler-trash aria-hidden="true" /><span>{{ $t('draft.discard') }}</span>
                    </button>
                    <span class="smk-discard-confirm" x-show="confirming" x-cloak>
                        <button type="button" title="{{ $t('draft.keep') }}" aria-label="{{ $t('draft.keep') }}" x-on:click="confirming = false"><x-tabler-x aria-hidden="true" /></button>
                        <button type="button" class="is-danger" title="{{ $t('draft.delete') }}" aria-label="{{ $t('draft.delete') }}"
                            wire:click="discardDraft" wire:loading.attr="disabled"><x-tabler-check aria-hidden="true" /></button>
                    </span>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
