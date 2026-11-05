<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Livewire;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;

/**
 * The Server Maker's settings as one form: in Simplify settings › Add-ons (add-on API 8, whose Save calls save()) and
 * on the Server Maker's own settings page. It checks its user itself and saves only on Save.
 *
 * @property Schema $form
 */
final class MakerSettingsForm extends Component implements HasForms
{
    use InteractsWithForms;

    /** The settings page's Save, sent to this form. */
    public const SAVE = 'simplify-servermaker-settings-save';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(MakerSettings::canAccess(), 403);
        $this->form->fill([...MakerSettings::current(), 'curseforge_key' => '', 'curseforge_forget' => false]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn () => ! MakerSettings::mayChange())
            ->components(MakerSettings::formComponents());
    }

    #[On(self::SAVE)]
    public function save(): void
    {
        abort_unless(MakerSettings::mayChange(), 403);
        MakerSettings::save($this->form->getState());

        // The key is never sent back to the browser.
        $this->data['curseforge_key'] = '';
        $this->data['curseforge_forget'] = false;
        Notification::make()->title(__('simplify-servermaker::maker.settings.saved'))->success()->send();
    }

    public function render(): View
    {
        return view('simplify-servermaker::livewire.maker-settings-form');
    }
}
