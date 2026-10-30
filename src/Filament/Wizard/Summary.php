<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use App\Models\Allocation;
use Simplify\ServerMaker\Creation\EggData;
use Simplify\ServerMaker\Creation\FirstSettings;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Creation\Subdomain;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\Java;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Modpacks\Sources;

/** The rows of the Review step. */
final class Summary
{
    /** @return array<string, string> label => value */
    public static function rows(ServerMaker $page): array
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.'.$key, $replace);
        $data = $page->data;
        $minecraft = ($data['game'] ?? null) === 'minecraft';
        $type = (string) ($data['type'] ?? '');
        $egg = $page->egg();

        $rows = [$t('review.name') => trim((string) ($data['name'] ?? '')) ?: '—'];
        if ($minecraft) {
            $software = Minecraft::software($type, $data['software'] ?? null);
            $rows[$t('review.game')] = 'Minecraft · '.$t('minecraft.types.'.$type.'.label')
                .($software ? ' · '.Minecraft::SOFTWARE[$type][$software] : '');
            if ($type === 'modpack') {
                $rows[$t('modpack.pack')] = Sources::pack($data['modpack'] ?? null)?->title ?? '—';
                $rows[$t('modpack.version')] = Sources::versions($data['modpack'] ?? null)[(string) ($data['modpack_version'] ?? '')] ?? '—';
            }
            if (Minecraft::usesVersion($type)) {
                $rows[$t('minecraft.version')] = (string) ($data['version'] ?? '—');
            }
            if (($java = $page->java()) !== null) {
                $rows[$t('review.java')] = 'Java '.$java;
            }
            if (FirstSettings::hasProperties('minecraft', $type)) {
                $rows[$t('first.max_players')] = (string) ($data['max_players'] ?? FirstSettings::DEFAULTS['max_players']);
                $rows[$t('first.gamemode')] = $t('first.gamemodes.'.($data['gamemode'] ?? FirstSettings::DEFAULTS['gamemode']));
                $rows[$t('first.difficulty')] = $t('first.difficulties.'.($data['difficulty'] ?? FirstSettings::DEFAULTS['difficulty']));
            }
        } else {
            $rows[$t('review.game')] = (string) ($egg?->name ?? '—');
        }
        $allocation = Allocation::query()->find((int) ($data['allocation_id'] ?? 0));
        $rows[$t('review.address')] = $allocation?->address ?? '—';
        $subdomain = trim((string) ($data['subdomain'] ?? ''));
        if ($subdomain !== '' && Subdomain::available()) {
            $rows[$t('server.subdomain')] = $subdomain.'.'.Subdomain::domain();
        }
        $rows[$t('resources.ram')] = $t('resources.gb', ['amount' => Resources::amount($data, 'ram')]);
        $rows[$t('resources.cpu')] = Resources::amount($data, 'cpu').'%';
        $rows[$t('resources.disk')] = $t('resources.gb', ['amount' => Resources::gb(Resources::amount($data, 'disk'))]);
        $rows[$t('review.egg')] = (string) ($egg?->name ?? '—');
        $rows[$t('review.start')] = $t('review.start_manual');

        return $rows;
    }

    public static function image(ServerMaker $page): ?string
    {
        $egg = $page->egg();
        if (! $egg) {
            return null;
        }
        $data = EggData::from($egg);
        $java = $page->java();

        return $java !== null ? Java::image($data->images, $java) : $data->firstImage();
    }
}
