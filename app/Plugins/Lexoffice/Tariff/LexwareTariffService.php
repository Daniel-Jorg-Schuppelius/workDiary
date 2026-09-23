<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwareTariffService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Tariff;

use App\Enums\Lexoffice\{LexofficeHandoverStatus, LexwareFeature, LexwarePlan};
use App\Models\InvoiceSchedule;
use App\Models\Platform\{Organization, User};
use App\Models\Plugins\Lexoffice\LexofficeInvoiceHandover;
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;

/**
 * Tarifprofil lesen und schreiben (Feature 158, MVP-831). Ablage in den
 * Org-Einstellungen `lexware.*` über die Settings-Registry — keine eigene
 * Tabelle. Ein Tarifwechsel ändert nur Empfehlungen und Übergabewege;
 * Belege, laufende lokale Serien und Nummernkreise bleiben unberührt.
 */
class LexwareTariffService {
    public const CHANNEL_MANUAL = 'manual';

    public const CHANNEL_API = 'api';

    public function profile(): LexwareTariffProfile {
        $features = [];
        foreach ((array) Setting::get('lexware.local_features', []) as $value) {
            $feature = LexwareFeature::tryFrom((string) $value);
            if ($feature !== null && ! in_array($feature, $features, true)) {
                $features[] = $feature;
            }
        }

        return new LexwareTariffProfile(
            plan: LexwarePlan::tryFrom((string) Setting::get('lexware.plan', LexwarePlan::Unknown->value)) ?? LexwarePlan::Unknown,
            source: (string) Setting::get('lexware.plan_source', 'user'),
            confirmedOn: $this->date(Setting::get('lexware.plan_confirmed_on')),
            trialEndsOn: $this->date(Setting::get('lexware.trial_ends_on')),
            trialSuccessorPlan: LexwarePlan::tryFrom((string) Setting::get('lexware.trial_successor_plan', LexwarePlan::Unknown->value)) ?? LexwarePlan::Unknown,
            handoverChannel: (string) Setting::get('lexware.handover_channel', self::CHANNEL_MANUAL),
            localFeatures: $features,
        );
    }

    /**
     * @param array{plan: string, plan_source: string, plan_confirmed_on: ?string, trial_ends_on: ?string, trial_successor_plan: string, handover_channel: string, local_features: list<string>} $data
     */
    public function save(Organization $organization, User $actor, array $data): LexwareTariffProfile {
        $before = $this->profile();
        $features = array_values(array_unique(array_filter(
            array_map(static fn (string $v): ?string => LexwareFeature::tryFrom($v)?->value, $data['local_features']),
        )));

        Setting::set('lexware.plan', $data['plan'], SettingScope::Organization, $organization, $actor->id);
        Setting::set('lexware.plan_source', $data['plan_source'], SettingScope::Organization, $organization, $actor->id);
        Setting::set('lexware.plan_confirmed_on', $data['plan_confirmed_on'] ?? null, SettingScope::Organization, $organization, $actor->id);
        Setting::set('lexware.trial_ends_on', $data['trial_ends_on'] ?? null, SettingScope::Organization, $organization, $actor->id);
        Setting::set('lexware.trial_successor_plan', $data['trial_successor_plan'], SettingScope::Organization, $organization, $actor->id);
        Setting::set('lexware.handover_channel', $data['handover_channel'], SettingScope::Organization, $organization, $actor->id);
        Setting::set('lexware.local_features', $features, SettingScope::Organization, $organization, $actor->id);

        $organization->audit('lexware.tariffChanged', [
            'plan' => ['from' => $before->plan->value, 'to' => $data['plan']],
            'handover_channel' => ['from' => $before->handoverChannel, 'to' => $data['handover_channel']],
            'local_features' => $features,
            'by' => $actor->id,
        ]);

        return $this->profile();
    }

    /**
     * Vorschau eines Tarifwechsels: was bleibt (alles Lokale), was sich
     * ändert (Empfehlungen, Übergabewege). Zählt aktive lokale Serien und
     * offene Übergaben — nichts davon wird durch den Wechsel abgeschaltet.
     *
     * @return array{active_schedules: int, open_handovers: int, api_allowed: bool}
     */
    public function changePreview(Organization $organization, LexwarePlan $target): array {
        return [
            'active_schedules' => InvoiceSchedule::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->count(),
            'open_handovers' => LexofficeInvoiceHandover::query()
                ->where('organization_id', $organization->id)
                ->whereNotIn('status', [LexofficeHandoverStatus::Confirmed->value, LexofficeHandoverStatus::Transferred->value])
                ->count(),
            'api_allowed' => (new LexwarePlanMatrix)->allowsOwnApiKey($target),
        ];
    }

    private function date(mixed $value): ?CarbonImmutable {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
