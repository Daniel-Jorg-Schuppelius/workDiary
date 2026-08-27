<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Enums\User\Permission;
use App\Models\{ServiceTicket, User};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Offene Tickets, die dem Nutzer zugewiesen sind — dringendste zuerst. */
class ServiceTicketsWidget extends Widget {
    public function key(): string {
        return 'service-tickets';
    }

    public function label(): string {
        return (string) __('Meine Tickets');
    }

    public function icon(): string {
        return 'confirmation_number';
    }

    public function defaultOrder(): int {
        return 73;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.service_tickets.description');
    }

    public function requiredModule(): ?string {
        return 'module.helpdesk';
    }

    public function availableFor(User $user): bool {
        return parent::availableFor($user)
            && Gate::forUser($user)->allows(Permission::ServiceTicketView->value);
    }

    public function render(User $user): View|string {
        $openStatuses = array_map(
            static fn (ServiceTicketStatus $s): string => $s->value,
            array_values(array_filter(
                ServiceTicketStatus::cases(),
                static fn (ServiceTicketStatus $s): bool => ! $s->isResolved(),
            )),
        );

        $query = ServiceTicket::query()
            ->where('assigned_to_user_id', $user->id)
            ->whereIn('status', $openStatuses);

        return view('dashboard.widgets.service-tickets', [
            'openCount' => (clone $query)->count(),
            'tickets' => $query->orderByDesc('reported_at')->limit(5)->get(),
        ]);
    }
}
