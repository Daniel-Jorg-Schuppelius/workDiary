<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Demo;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Platform\Organization;
use App\Models\Sales\BookableService;
use App\Services\Appointments\AppointmentRequestService;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use Illuminate\Support\Carbon;

/**
 * Terminanfragen zur Vorführung (MVP-1288): eine buchbare Leistung, eine
 * offene und eine bestätigte Anfrage des Hauptkunden.
 */
final class AppointmentDemoBlock implements DemoBlock {
    public function __construct(private readonly AppointmentRequestService $appointments) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->mainCustomer !== null;
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;
        $customer = $context->mainCustomer;
        if ($customer === null) {
            return [];
        }

        $service = BookableService::query()->create([
            'organization_id' => $organization->id,
            'title' => (string) __('Vor-Ort-Termin (Demo)'),
            'description' => (string) __('Technikerbesuch beim Kunden, eine Stunde.'),
            'duration_minutes' => 60,
            'lead_time_hours' => 24,
            'cancel_hours' => 24,
            'buffer_minutes' => 15,
            'active' => true,
            'created_by' => $context->actor->id,
        ]);

        $requests = [];
        foreach ([3, 6] as $i => $days) {
            $start = Carbon::now()->addDays($days)->setTime(10, 0);
            $requests[] = AppointmentRequest::query()->create([
                'organization_id' => $organization->id,
                'source' => AppointmentRequest::SOURCE_PORTAL,
                'source_uri' => 'demo:' . $organization->id . ':' . ($i + 1),
                'status' => AppointmentRequestStatus::Requested,
                'customer_id' => $customer->id,
                'bookable_service_id' => $service->id,
                'start_at' => $start->copy()->utc(),
                'end_at' => $start->copy()->addHour()->utc(),
                'invitee_name' => $customer->name,
                // Ohne Adresse: die Bestätigung verschickt in der Demo keine Mail.
                'invitee_email' => null,
                'service_label' => $service->title,
            ]);
        }
        // Ein Ersatzvorschlag statt einer Bestätigung: die legte einen Auftrag an.
        $this->appointments->proposeAlternative($requests[1], $context->actor, Carbon::now()->addDays(7)->setTime(14, 0)->toImmutable());

        return ['appointment_requests' => count($requests)];
    }

    public function purge(Organization $organization): void {
        AppointmentRequest::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        BookableService::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
