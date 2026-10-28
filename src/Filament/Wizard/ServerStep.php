<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Creation\Ports;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;

/** Step 3: the name, and the node and port when there is something to choose. */
final class ServerStep
{
    public static function make(ServerMaker $page): Step
    {
        $t = fn (string $key, array $replace = []) => __('simplify-server-maker::maker.'.$key, $replace);
        $several = fn () => Ports::nodes()->count() > 1;

        return Step::make('server')
            ->label($t('steps.server'))
            ->schema([
                TextInput::make('name')
                    ->label($t('server.name'))
                    ->helperText($t('server.name_help'))
                    ->placeholder(fn () => $page->randomName())
                    ->required()
                    ->maxLength(191)
                    ->suffixAction(
                        Action::make('random_name')
                            ->icon('tabler-dice-5')
                            ->tooltip($t('server.random'))
                            ->action(fn (Set $set) => $set('name', $page->randomName())),
                    ),
                Select::make('node_id')
                    ->label($t('server.node'))
                    ->options(fn () => Ports::nodes()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible($several)
                    ->dehydratedWhenHidden()
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $set('allocation_id', Ports::first((int) $state))),
                Select::make('allocation_id')
                    ->label($t('server.port'))
                    ->helperText($t('server.port_help'))
                    ->options(fn (Get $get) => Ports::options((int) $get('node_id')))
                    ->visible(fn (Get $get) => Ports::first((int) $get('node_id')) !== null)
                    ->dehydratedWhenHidden()
                    ->searchable()
                    ->native(false),
                View::make('simplify-server-maker::wizard.no-port')
                    ->viewData(fn (Get $get) => self::noPort((int) $get('node_id')))
                    ->visible(fn (Get $get) => Ports::first((int) $get('node_id')) === null),
            ])
            ->afterValidation(function () use ($page, $t): void {
                $nodeId = (int) ($page->data['node_id'] ?? 0);
                if (! Ports::nodes()->whereKey($nodeId)->exists()) {
                    throw ValidationException::withMessages(['data.node_id' => $t('server.node_denied')]);
                }
                $allocation = (int) ($page->data['allocation_id'] ?? 0);
                if (! Ports::free($nodeId)->whereKey($allocation)->exists()) {
                    $page->data['allocation_id'] = Ports::first($nodeId);
                }
                if ($page->data['allocation_id'] === null) {
                    throw ValidationException::withMessages(['data.allocation_id' => $t('server.no_port_short')]);
                }
            });
    }

    /** @return array{node: string, url: ?string} */
    private static function noPort(int $nodeId): array
    {
        $node = Ports::nodes()->find($nodeId);
        $url = $node && user()?->can('update', $node)
            ? rescue(fn () => route('filament.admin.resources.nodes.edit', ['record' => $node, 'tab' => 'allocations::data::tab']), null, false)
            : null;

        return ['node' => (string) ($node?->name ?? ''), 'url' => $url];
    }
}
