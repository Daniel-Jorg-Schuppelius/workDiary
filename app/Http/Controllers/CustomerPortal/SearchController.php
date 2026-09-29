<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\CustomerPortal\PortalCapability;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Document\Document;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\CustomerPortal\PortalVisibility;
use App\Support\Tz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Suche im Kundenportal (Feature 153/012, MVP-1019): nur Bereiche, die der
 * Kunde freigegeben bekommen hat, mit derselben Sichtbarkeit wie die Listen.
 */
class SearchController extends Controller {
    private const LIMIT = 10;

    public function __construct(private readonly PortalVisibility $visibility) {}

    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $customer = $user->customer;
        abort_unless($customer instanceof Customer, 403);
        $q = trim((string) $request->query('q', ''));
        $groups = [];

        if (mb_strlen($q) >= 2) {
            if ($this->visibility->allows($customer, PortalCapability::Invoices)) {
                $groups['invoices'] = Invoice::query()->where('customer_id', $customer->id)->where('status', '!=', Invoice::STATUS_DRAFT)
                    ->whereLikeEscaped('number', $q)->orderByDesc('issued_on')->limit(self::LIMIT)->get()
                    ->map(fn (Invoice $invoice): array => ['label' => (string) $invoice->number, 'meta' => $invoice->issued_on?->format('d.m.Y'), 'url' => route('customer.invoices.index')])->all();
            }
            if ($this->visibility->allows($customer, PortalCapability::Diary)) {
                $groups['diary'] = DiaryEntry::query()->where('customer_id', $customer->id)->whereLikeEscaped('title', $q)
                    ->orderByDesc('start_at')->limit(self::LIMIT)->get()
                    ->map(fn (DiaryEntry $entry): array => ['label' => (string) $entry->title, 'meta' => $entry->start_at?->setTimezone(Tz::current())->format('d.m.Y'), 'url' => route('customer.diary.show', $entry)])->all();
            }
            if ($this->visibility->allows($customer, PortalCapability::Documents)) {
                $groups['documents'] = Document::query()->visibleToCustomer((int) $user->organization_id, (int) $customer->id)->whereLikeEscaped('title', $q)
                    ->latest('customer_released_at')->limit(self::LIMIT)->get()
                    ->map(fn (Document $document): array => ['label' => (string) $document->title, 'meta' => $document->customer_released_at?->setTimezone(Tz::current())->format('d.m.Y'), 'url' => route('customer.documents.index')])->all();
            }
            if ($this->visibility->allows($customer, PortalCapability::Tickets)) {
                $groups['tickets'] = ServiceTicket::query()->where('customer_id', $customer->id)
                    ->where(fn ($query) => $query->whereLikeEscaped('title', $q)->orWhereLikeEscaped('ticket_no', $q))
                    ->orderByDesc('reported_at')->limit(self::LIMIT)->get()
                    ->map(fn (ServiceTicket $ticket): array => ['label' => $ticket->ticket_no . ' · ' . $ticket->title, 'meta' => $ticket->reported_at?->setTimezone(Tz::current())->format('d.m.Y'), 'url' => route('customer.tickets.show', $ticket)])->all();
            }
        }

        return view('customer.search.index', ['q' => $q, 'groups' => $groups]);
    }
}
