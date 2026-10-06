<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckRentalGeofence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Rental;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Rental\{RentalCaseAssetStatus, RentalCaseStatus};
use App\Events\Asset\AssetPositionRecorded;
use App\Listeners\ModuleListener;
use App\Models\Asset\AssetPosition;
use App\Models\Location\CustomerGeofence;
use App\Models\Platform\Organization;
use App\Models\Rental\RentalCase;
use App\Services\Notification\NotificationDispatcher;
use App\Settings\SettingsRegistry;
use CommonToolkit\Helper\Geo\GeoHelper;

/**
 * Soll-Ort eines verliehenen Geräts (MVP-975): der Standort des Verleihs
 * (Radius aus `rental.geofence_radius_m`), sonst die aktiven Geofences des
 * Kunden. Liegt die Position außerhalb, meldet der Listener die Abweichung —
 * einmal beim Verlassen, nicht bei jeder weiteren Position draußen.
 */
final class CheckRentalGeofence extends ModuleListener {
    public function __construct(
        private readonly NotificationDispatcher $notifier,
        private readonly SettingsRegistry $settings,
    ) {}

    protected function module(): string {
        return 'rental';
    }

    public function handle(AssetPositionRecorded $event): void {
        $position = $event->position;
        $organization = Organization::query()->find($position->organization_id);
        if ($organization === null || ! $this->shouldHandle($organization)) {
            return;
        }

        $case = RentalCase::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [RentalCaseStatus::HandedOver->value, RentalCaseStatus::Overdue->value])
            ->where('starts_at', '<=', $position->recorded_at)
            ->whereHas('caseAssets', fn ($q) => $q->where('asset_id', $position->asset_id)->where('status', RentalCaseAssetStatus::HandedOver))
            ->orderByDesc('starts_at')
            ->first();
        if ($case === null) {
            return;
        }

        $nearest = null;
        foreach ($this->areas($case, $organization) as $area) {
            $distance = GeoHelper::haversineMeters((float) $position->lat, (float) $position->lng, $area['lat'], $area['lng']);
            $deviation = (int) max(0, round($distance - $area['radius']));
            if ($nearest === null || $deviation < $nearest['deviation']) {
                $nearest = ['label' => $area['label'], 'deviation' => $deviation];
            }
        }
        if ($nearest === null) {
            return;
        }

        $position->forceFill(['expected_label' => mb_substr($case->number . ' · ' . $nearest['label'], 0, 200), 'deviation_m' => $nearest['deviation']])->save();
        if ($nearest['deviation'] === 0 || $this->alreadyOutside($position)) {
            return;
        }

        $this->notifier->notify(NotificationEvent::RentalGeofenceDeviation, $case, $case->responsible, [
            'title' => (string) __('Gerät außerhalb des Einsatzorts: :number', ['number' => $case->number]),
            'title_key' => 'Gerät außerhalb des Einsatzorts: :number',
            'title_params' => ['number' => $case->number],
            'message' => (string) __(':distance m außerhalb von „:place“.', ['distance' => $nearest['deviation'], 'place' => $nearest['label']]),
            'message_key' => ':distance m außerhalb von „:place“.',
            'message_params' => ['distance' => $nearest['deviation'], 'place' => $nearest['label']],
            'url' => route('rental.show', $case),
        ]);
    }

    /** @return list<array{label: string, lat: float, lng: float, radius: int}> */
    private function areas(RentalCase $case, Organization $organization): array {
        $site = $case->site;
        if ($site !== null && $site->geo_lat !== null && $site->geo_lng !== null) {
            $radius = (int) $this->settings->effective('rental.geofence_radius_m', $organization)->value;

            return [['label' => (string) $site->name, 'lat' => (float) $site->geo_lat, 'lng' => (float) $site->geo_lng, 'radius' => max(1, $radius)]];
        }

        $fences = CustomerGeofence::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $case->customer_id)
            ->where('is_active', true)
            ->get();
        if ($case->project_id !== null && $fences->contains('project_id', $case->project_id)) {
            $fences = $fences->where('project_id', $case->project_id);
        }

        return array_values($fences->map(static fn (CustomerGeofence $fence): array => [
            'label' => $fence->label, 'lat' => (float) $fence->center_lat, 'lng' => (float) $fence->center_lng, 'radius' => $fence->radius_m,
        ])->all());
    }

    /** Die vorige Position lag schon draußen: bereits gemeldet. */
    private function alreadyOutside(AssetPosition $position): bool {
        $previous = AssetPosition::query()->withoutGlobalScopes()
            ->where('asset_id', $position->asset_id)
            ->where('recorded_at', '<', $position->recorded_at)
            ->orderByDesc('recorded_at')
            ->first();

        return $previous !== null && (int) $previous->deviation_m > 0;
    }
}
