<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Cockpit;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Ungelöste Tickets des Kunden (Feature 010); vertrauliche nur, wo die Ticket-Policy sie zeigt. */
final class ServiceTicketCockpitPanel implements CockpitPanel {
    private const LIMIT = 10;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 10;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $this->features->routeEnabled('service-tickets.show') || ! $gate->allows('viewAny', ServiceTicket::class)) {
            return null;
        }
        $unresolved = array_map(
            static fn (ServiceTicketStatus $s): string => $s->value,
            array_values(array_filter(ServiceTicketStatus::cases(), static fn (ServiceTicketStatus $s): bool => ! $s->isResolved())),
        );
        $tickets = ServiceTicket::query()
            ->where('customer_id', $subject->getKey())
            ->whereIn('status', $unresolved)
            ->orderBy('resolution_due_at')
            ->get()
            ->filter(fn (ServiceTicket $t): bool => $gate->allows('view', $t))
            ->values();
        if ($tickets->isEmpty()) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-tickets',
            'title' => __('cockpit.tickets.title'),
            'icon' => 'confirmation_number',
            'count' => $tickets->count(),
            'rows' => array_map(static fn (ServiceTicket $t): array => [
                'title' => $t->ticket_no . ' · ' . $t->title,
                'url' => route('service-tickets.show', $t),
                'subtitle' => null,
                'badge' => $t->status->label(),
                'tone' => 'info',
                'date' => $t->resolution_due_at !== null ? CarbonFmt::fdate($t->resolution_due_at) : null,
            ], $tickets->take(self::LIMIT)->all()),
        ]);
    }
}
