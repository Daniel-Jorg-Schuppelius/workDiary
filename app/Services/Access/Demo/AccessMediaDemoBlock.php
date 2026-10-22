<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediaDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Access\Demo;

use App\Enums\Access\{AccessMediumStatus, AccessMediumType};
use App\Models\Access\AccessMedium;
use App\Models\Platform\Organization;
use App\Services\Access\AccessMediumService;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/**
 * Zutrittsmedien-Vorführung (MVP-1288): Transponder im Lager, ausgegebene
 * Karte an eine Person und ein Schlüsseltransponder bei einem Dienstleister.
 */
final class AccessMediaDemoBlock implements DemoBlock {
    public function __construct(private readonly AccessMediumService $media) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;
        $actor = $context->actor;
        $holder = $context->users->skip(1)->first() ?? $actor;

        $make = fn (AccessMediumType $type, string $number, string $label): AccessMedium => AccessMedium::query()->create([
            'organization_id' => $organization->id,
            'type' => $type->value,
            'number_hash' => AccessMedium::hashNumber($number . '-' . $organization->id),
            'number_suffix' => mb_substr($number, -4),
            'label' => $label,
            'system_name' => (string) __('Schließanlage Hauptgebäude (Demo)'),
            'status' => AccessMediumStatus::InStock->value,
            'created_by' => $actor->id,
        ]);

        $make(AccessMediumType::Transponder, 'DEMO-TR-1001', (string) __('Ersatztransponder Werkstatt'));
        $card = $make(AccessMediumType::Card, 'DEMO-KA-2002', (string) __('Mitarbeiterkarte'));
        $vendor = $make(AccessMediumType::Transponder, 'DEMO-TR-3003', (string) __('Transponder Wartungsfirma'));

        $this->media->issue($card, ['holder_user_id' => $holder->id], $actor);
        $this->media->issue($vendor, [
            'holder_name' => 'Max Wartung',
            'holder_company' => (string) __('Muster-Klimatechnik GmbH (Demo)'),
            'expected_return_at' => now()->addDays(14)->toDateString(),
        ], $actor);

        return ['access_media' => 3];
    }

    public function purge(Organization $organization): void {
        AccessMedium::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
