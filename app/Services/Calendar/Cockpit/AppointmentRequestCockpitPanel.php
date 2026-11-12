<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentRequestCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Cockpit;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\User\Permission;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Gebuchte und angefragte Termine (Feature 087, Calendly) am Lead und am
 * Kunden (MVP-1617) — Rechte wie der Termin-Eingang, ersetzte Anfragen entfallen.
 */
final class AppointmentRequestCockpitPanel implements CockpitPanel {
    private const int LIMIT = 10;

    private const array COLUMN = [Lead::class => 'lead_id', Customer::class => 'customer_id'];

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 15;
    }

    public function supports(Model $subject): bool {
        return isset(self::COLUMN[$subject::class]);
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $this->features->routeEnabled('appointments.index') || ! Gate::forUser($viewer)->allows(Permission::DispatchViewAny->value)) {
            return null;
        }
        $query = AppointmentRequest::query()
            ->where(self::COLUMN[$subject::class], $subject->getKey())
            ->where('status', '!=', AppointmentRequestStatus::Superseded->value);
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-appointments',
            'title' => __('cockpit.appointments.title'),
            'icon' => 'event_available',
            'count' => $count,
            'rows' => array_map(static fn (AppointmentRequest $request): array => [
                'title' => (string) ($request->service_label ?: __('cockpit.appointments.untitled')),
                'url' => route('appointments.index', in_array($request->status, [AppointmentRequestStatus::Requested, AppointmentRequestStatus::Proposed], true) ? [] : ['tab' => 'decided']),
                'subtitle' => $request->location,
                'badge' => $request->status->label(),
                'tone' => $request->status->tone(),
                'date' => $request->start_at !== null ? CarbonFmt::fdatetime($request->start_at) : null,
            ], $query->orderByDesc('start_at')->orderByDesc('id')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
