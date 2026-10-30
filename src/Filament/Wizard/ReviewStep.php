<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use App\Models\Egg;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;
use Simplify\ServerMaker\Creation\FirstSettings;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\Java;

/** Step 5: the server overview; administrators open the technical settings with Edit settings under it (as in 0.7.0). */
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
                // Optional and never ticked for the user: without it the server installs and asks for the EULA when started.
                Checkbox::make('eula')
                    ->label($t('first.eula'))
                    ->helperText(new HtmlString(e($t('first.eula_help')).' <a href="https://www.minecraft.net/eula" target="_blank" rel="noopener noreferrer" class="smk-link">'.e($t('first.eula_read')).'</a>'))
                    ->visible(fn () => FirstSettings::needsEula($page->data['game'] ?? null, $page->data['type'] ?? null)),
                // Hidden with CSS, not removed, so the fields keep their values while closed.
                View::make('simplify-servermaker::wizard.advanced')
                    ->visible(fn () => $page->isAdmin())
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('egg_id')
                                ->label($t('review.egg'))
                                ->helperText($t('review.egg_help'))
                                ->options(fn () => Egg::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->native(false)
                                ->required(),
                            Select::make('java')
                                ->label($t('review.java_version'))
                                ->helperText($t('review.java_help'))
                                ->options(fn () => ['auto' => $t('review.java_auto')] + array_combine(
                                    array_map('strval', Java::MAJORS),
                                    array_map(fn (int $major) => 'Java '.$major, Java::MAJORS),
                                ))
                                ->native(false)
                                ->visible(fn () => ($page->data['game'] ?? null) === 'minecraft' && ($page->data['type'] ?? null) !== 'bedrock'),
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
                    ]),
            ]);
    }
}
