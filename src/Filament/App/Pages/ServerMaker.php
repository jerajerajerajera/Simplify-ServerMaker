<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\App\Pages;

use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker as AdminServerMaker;

/** The same wizard in the normal panel, for people who may make servers within their allowance. */
class ServerMaker extends AdminServerMaker
{
    public static function getNavigationGroup(): ?string
    {
        return null;
    }
}
