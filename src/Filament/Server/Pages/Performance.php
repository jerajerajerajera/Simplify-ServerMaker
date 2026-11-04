<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Server\Pages;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Simplify\ServerMaker\ServerSettings\PerformanceSection;
use Simplify\ServerMaker\ServerSettings\ToolkitSections;

/**
 * Performance as a page of its own, without Simplify Toolkit; with Toolkit it is a section of its server settings.
 *
 * @property Schema $form
 */
final class Performance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-cpu';

    protected static ?int $navigationSort = 14;

    protected static ?string $slug = 'performance';

    protected string $view = 'simplify-servermaker::pages.performance';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getTenant() instanceof Server && user() !== null && ! ToolkitSections::available();
    }

    public static function canChange(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server && PerformanceSection::canChange($server);
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-servermaker::maker.performance.title');
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
        $this->form->fill(PerformanceSection::fill($this->server()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([PerformanceSection::make($this->server())]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('simplify-servermaker::maker.settings.save'))
                ->icon('tabler-device-floppy')
                ->visible(fn () => self::canChange())
                ->action(fn () => $this->save())
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canChange(), 403);
        PerformanceSection::save($this->server(), $this->form->getState());
        Notification::make()->title(__('simplify-servermaker::maker.performance.saved'))->success()->send();
    }
}
