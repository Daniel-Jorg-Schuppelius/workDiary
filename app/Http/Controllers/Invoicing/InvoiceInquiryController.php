<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Invoicing;

use App\Enums\Invoicing\InvoiceInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\InvoiceInquiry;
use App\Models\Platform\User;
use App\Plugins\PluginManager;
use App\Services\Invoicing\Inquiries\InvoiceInquiryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Arbeitsliste und Bearbeitung der Rechnungsnachfragen (Feature 166, MVP-1202). */
class InvoiceInquiryController extends Controller {
    private const STATUS_FILTERS = ['open', 'answered', 'all'];

    public function index(Request $request, PluginManager $plugins): View {
        Gate::authorize('viewAny', InvoiceInquiry::class);
        $user = $this->currentUser();

        $status = $request->string('status')->toString();
        $status = in_array($status, self::STATUS_FILTERS, true) ? $status : 'open';

        $inquiries = InvoiceInquiry::query()
            ->where('organization_id', $user->organization_id)
            ->with(['invoice:id,number,external_number', 'customer:id,name', 'communicationNote:id'])
            ->when($status !== 'all', static fn (Builder $q) => $q->where('status', $status))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $counts = InvoiceInquiry::query()
            ->where('organization_id', $user->organization_id)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        // Anzeigename des Programms je Beleg (Lexoffice, sevDesk, …).
        $sourceLabels = [];
        foreach ($inquiries->getCollection()->pluck('external_source')->filter()->unique() as $source) {
            $sourceLabels[(string) $source] = $plugins->find((string) $source)?->name() ?? ucfirst((string) $source);
        }

        return view('invoices.inquiries.index', [
            'inquiries' => $inquiries,
            'status' => $status,
            'counts' => [
                'open' => $counts[InvoiceInquiryStatus::Open->value] ?? 0,
                'answered' => $counts[InvoiceInquiryStatus::Answered->value] ?? 0,
            ],
            'sourceLabels' => $sourceLabels,
        ]);
    }

    public function answer(InvoiceInquiry $inquiry, InvoiceInquiryService $service): RedirectResponse {
        Gate::authorize('update', $inquiry);
        if (! $inquiry->isOpen()) {
            return back()->with('error', __('invoicing.inquiries.flash.already_answered'));
        }
        $service->answer($inquiry, $this->currentUser());

        return back()->with('status', __('invoicing.inquiries.flash.answered', ['number' => $inquiry->invoice_number]));
    }

    public function reopen(InvoiceInquiry $inquiry, InvoiceInquiryService $service): RedirectResponse {
        Gate::authorize('update', $inquiry);
        if ($inquiry->isOpen()) {
            return back()->with('error', __('invoicing.inquiries.flash.already_open'));
        }
        $service->reopen($inquiry, $this->currentUser());

        return back()->with('status', __('invoicing.inquiries.flash.reopened', ['number' => $inquiry->invoice_number]));
    }

    private function currentUser(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
