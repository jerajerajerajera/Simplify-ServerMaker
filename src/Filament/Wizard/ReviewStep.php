<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;

/** Step 5: everything at a glance, with the technical settings folded away for administrators. */
final class ReviewStep
{
    public static function make(ServerMaker $page): Step
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.'.$key);

        return Step::make('review')
            ->label($t('steps.review'))
            ->schema([
                View::make('simplify-servermaker::wizard.review')
                    ->viewData(fn () => ['rows' => Summary::rows($page)]),
                Section::make($t('review.advanced'))
                    ->description($t('review.advanced_help'))
                    ->visible(fn () => $page->isAdmin())
                    ->collapsible()
                    ->collapsed()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('image')
                            ->label($t('review.image'))
                            ->placeholder(fn () => Summary::image($page) ?? '')
                            ->maxLength(255),
                        TextInput::make('backups')
                            ->label($t('review.backups'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        Textarea::make('startup')
                            ->label($t('review.startup'))
                            ->placeholder(fn () => $page->egg()?->startup_commands ? (string) collect($page->egg()->startup_commands)->first() : '')
                            ->rows(2)
                            ->columnSpanFull(),
                        KeyValue::make('environment')
                            ->label($t('review.environment'))
                            ->helperText($t('review.environment_help'))
                            ->keyLabel($t('review.variable'))
                            ->valueLabel($t('review.value'))
                            ->columnSpanFull(),
                        Toggle::make('skip_scripts')
                            ->label($t('review.skip_scripts'))
                            ->helperText($t('review.skip_scripts_help')),
                    ]),
            ]);
    }
}
