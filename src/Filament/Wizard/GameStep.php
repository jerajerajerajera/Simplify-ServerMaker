<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Wizard\Step;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\OtherGames;

/** Step 1: Minecraft with its server types on top, every other game below. A pick calls pickGame(). */
final class GameStep
{
    public static function make(ServerMaker $page): Step
    {
        return Step::make('game')
            ->label(__('simplify-server-maker::maker.steps.game'))
            ->schema([
                ViewField::make('game')
                    ->hiddenLabel()
                    ->view('simplify-server-maker::wizard.game-picker')
                    ->viewData(fn () => [
                        'types' => array_values(array_filter(Minecraft::TYPES, fn (string $type) => $type !== 'modpack')),
                        'games' => OtherGames::cards(),
                    ])
                    ->required()
                    ->in(['minecraft', 'other'])
                    ->validationMessages([
                        'required' => __('simplify-server-maker::maker.game.required'),
                        'in' => __('simplify-server-maker::maker.game.required'),
                    ]),
                Hidden::make('type'),
                Hidden::make('software'),
                Hidden::make('egg_id'),
            ]);
    }
}
