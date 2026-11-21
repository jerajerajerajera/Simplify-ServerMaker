<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Console\Commands;

use Illuminate\Console\Command;
use Simplify\ServerMaker\Creation\ApplyFirstSettings;

/** Scheduled every minute: writes the first settings of the new servers whose installation finished. */
final class WriteFirstSettings extends Command
{
    protected $signature = 'simplify-servermaker:first-settings';

    protected $description = 'Write the first settings of new servers once they are installed.';

    public function handle(): int
    {
        $written = ApplyFirstSettings::writeDue();
        if ($written > 0) {
            $this->info("First settings written, servers: $written");
        }

        return self::SUCCESS;
    }
}
