<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\CustomerPortal\PortalCapability;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Platform\{HelpTopic, User};
use App\Services\CustomerPortal\PortalVisibility;
use App\Services\Help\HelpTopicResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Hilfecenter im Kundenportal (MVP-959): nur Themen `customer-portal.*`, und
 * davon nur die zu freigegebenen Bereichen — ein gesperrter Bereich hat auch
 * keine Hilfe.
 */
class HelpController extends Controller {
    public const PREFIX = 'customer-portal.';

    /** Thema → Portalbereich; Themen ohne Eintrag gelten immer. */
    public const CAPABILITIES = [
        'diary' => PortalCapability::Diary,
        'time' => PortalCapability::TimeEntries,
        'invoices' => PortalCapability::Invoices,
        'billing' => PortalCapability::Invoices,
        'documents' => PortalCapability::Documents,
        'assets' => PortalCapability::Assets,
        'issues' => PortalCapability::OpenIssues,
        'tickets' => PortalCapability::Tickets,
        'claims' => PortalCapability::Claims,
        'rentals' => PortalCapability::Rentals,
        'queries' => PortalCapability::Queries,
        'appointments' => PortalCapability::Appointments,
        'subscriptions' => PortalCapability::Subscriptions,
        'agreements' => PortalCapability::Agreements,
        'intakes' => PortalCapability::Intakes,
    ];

    public function __construct(
        private readonly HelpTopicResolver $resolver,
        private readonly PortalVisibility $visibility,
    ) {}

    public function index(): View {
        $locale = app()->getLocale();
        $topics = HelpTopic::query()->where('topic', 'like', self::PREFIX . '%')
            ->get(['topic'])
            ->pluck('topic')
            ->unique()
            ->filter(fn (string $topic): bool => $this->allowed($topic))
            ->map(fn (string $topic): ?HelpTopic => $this->resolver->find($topic, null, $locale))
            ->filter()
            ->sortBy(static fn (HelpTopic $row): string => ($row->topic === self::PREFIX . 'overview' ? '0' : '1') . $row->title)
            ->values();

        return view('customer.help.index', ['topics' => $topics]);
    }

    public function show(string $topic): View {
        abort_unless(str_starts_with($topic, self::PREFIX) && $this->allowed($topic), 404);
        $row = $this->resolver->find($topic, null, app()->getLocale());
        abort_if($row === null, 404);

        return view('customer.help.show', [
            'row' => $row,
            'related' => array_values(array_filter($this->resolver->relatedFor($row), fn (array $related): bool => str_starts_with($related['topic'], self::PREFIX) && $this->allowed($related['topic']))),
        ]);
    }

    public function allowed(string $topic): bool {
        $capability = self::CAPABILITIES[substr($topic, strlen(self::PREFIX))] ?? null;
        if ($capability === null) {
            return true;
        }
        $user = Auth::guard('customer')->user();
        $customer = $user instanceof User ? $user->customer : null;

        return $customer instanceof Customer && $this->visibility->allows($customer, $capability);
    }
}
