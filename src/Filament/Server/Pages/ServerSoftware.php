<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Server\Pages;

use App\Filament\Server\Pages\Console;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Simplify\ServerMaker\ServerSettings\SoftwareSection;
use Simplify\ServerMaker\ServerSettings\ToolkitSections;

/**
 * Server software as a page of its own, without Simplify Toolkit; with Toolkit it is a section of its server settings.
 *
 * @property Schema $form
 */
final class ServerSoftware extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-packages';

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'software';

    protected string $view = 'simplify-servermaker::pages.software';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server && ! ToolkitSections::available() && SoftwareSection::canUse($server);
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-servermaker::maker.software.title');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill(SoftwareSection::fill($this->server()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([SoftwareSection::make($this->server())]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('install')
                ->label(__('simplify-servermaker::maker.software.install'))
                ->disabled(fn () => ! hash_equals($this->server()->name, (string) ($this->data['confirm'] ?? '')))
                ->action(fn () => $this->install()),
        ];
    }

    public function install(): void
    {
        abort_unless(self::canAccess(), 403);
        $server = $this->server();
        if (SoftwareSection::install($server, $this->form->getState())) {
            $this->redirect(Console::getUrl(tenant: $server), navigate: true);
        }
    }
}
