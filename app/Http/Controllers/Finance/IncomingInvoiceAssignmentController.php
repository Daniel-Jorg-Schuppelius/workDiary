<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceAssignmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, IncomingInvoiceRecognition};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Supplier\Supplier;
use App\Rules\Iban;
use App\Services\Integration\Profiles\{CustomerMatchProfile, SupplierMatchProfile};
use App\Services\Invoicing\EInvoice\IncomingInvoiceMatcher;
use App\Services\Stammdaten\CollectiveContacts;
use App\Support\{ErrorText, Sqid};
use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\BankHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Manuelle Zuordnung im Rechnungseingang (Feature 163, MVP-1110): bestehende
 * Partei, Sammelkontakt, Neuanlage aus den Belegdaten oder „keine Rechnung“;
 * Werte erfassen für Klärfälle; Sammelaktion für mehrere Eingänge.
 */
class IncomingInvoiceAssignmentController extends Controller {
    use ResolvesCurrentOrganization;

    private const MODES = ['existing', 'collective', 'new', 'not_invoice'];

    public function __construct(
        private readonly IncomingInvoiceMatcher $matcher,
        private readonly CollectiveContacts $collective,
    ) {}

    public function assignForm(IncomingEInvoice $incoming): View {
        $this->authorizeBilling();
        $summary = (array) $incoming->summary;
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $address = (array) ($summary[$isPurchase ? 'seller_address' : 'buyer_address'] ?? []);

        return view('finance.incoming-invoices._assign_dialog', [
            'incoming' => $incoming,
            'suppliers' => Supplier::query()->withoutCollective()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'vat_id']),
            'customers' => Customer::query()->withoutCollective()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'vat_id']),
            'suggestions' => (array) ($summary['suggestions'] ?? []),
            'collectiveAllowed' => ! CollectiveContacts::requiresNamedContact($incoming),
            'prefill' => [
                'name' => $isPurchase ? $incoming->seller_name : $incoming->buyer_name,
                'vat_id' => $isPurchase ? $incoming->seller_vat_id : $incoming->buyer_vat_id,
                'email' => $isPurchase ? ($summary['seller_email'] ?? $incoming->sender_email) : null,
                'iban' => $isPurchase ? $incoming->creditor_iban : null,
                'street' => $address['street'] ?? null,
                'zip' => $address['zip'] ?? null,
                'city' => $address['city'] ?? null,
                'country' => $address['country'] ?? null,
            ],
        ]);
    }

    public function assign(Request $request, IncomingEInvoice $incoming): RedirectResponse {
        $actor = $this->authorizeBilling();
        if ($request->filled('new_country')) {
            $request->merge(['new_country' => strtoupper(trim((string) $request->input('new_country')))]);
        }
        $data = $request->validate([
            'direction' => ['required', Rule::enum(DocumentDirection::class)->only([DocumentDirection::Incoming, DocumentDirection::Outgoing])],
            'mode' => ['required', Rule::in(self::MODES)],
            'party' => ['nullable', 'required_if:mode,existing', 'string', 'max:64'],
            'new_name' => ['nullable', 'required_if:mode,new', 'string', 'max:255'],
            'new_vat_id' => ['nullable', 'string', 'max:32'],
            'new_email' => ['nullable', 'email', 'max:255'],
            'new_iban' => ['nullable', 'string', 'max:64', new Iban],
            'new_street' => ['nullable', 'string', 'max:255'],
            'new_zip' => ['nullable', 'string', 'max:20'],
            'new_city' => ['nullable', 'string', 'max:255'],
            'new_country' => ['nullable', Rule::enum(CountryCode::class)],
            'remember_sender' => ['nullable', 'boolean'],
            'note' => ['nullable', 'required_if:mode,not_invoice', 'string', 'max:500'],
        ]);
        if ($incoming->transferred_at !== null) {
            return back()->with('error', __('Der Eingang ist bereits an die Buchhaltung übergeben — die Zuordnung ist dort zu ändern.'));
        }

        if ($data['mode'] === 'not_invoice') {
            return $this->rejectAsNoInvoice($incoming, (string) $data['note'], $actor);
        }

        $direction = DocumentDirection::from((string) $data['direction']);
        try {
            DB::transaction(function () use ($incoming, $direction, $data, $actor): void {
                if ($incoming->direction !== $direction) {
                    // Richtung korrigiert: die bisherige Partei passt nicht mehr.
                    $incoming->forceFill(['direction' => $direction, 'supplier_id' => null, 'customer_id' => null])->save();
                    $incoming->audit('incoming_einvoice.direction_corrected', ['direction' => $direction->value]);
                }
                $party = $this->partyFor($direction, $data);
                $this->matcher->assign($incoming, $party, IncomingInvoiceMatchKind::Manual, $actor);
                if ((bool) ($data['remember_sender'] ?? false)) {
                    $this->matcher->rememberSender($incoming, $party, $actor);
                }
            });
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->toList('finance.incoming-invoices.index')->with('success', __('Eingang zugeordnet.'));
    }

    public function valuesForm(IncomingEInvoice $incoming): View {
        $this->authorizeBilling();
        abort_if($incoming->recognition === IncomingInvoiceRecognition::Structured, 404);

        return view('finance.incoming-invoices._values_dialog', ['incoming' => $incoming]);
    }

    /**
     * Werte eines Klärfalls bzw. einer Erkennung aus PDF oder Bild erfassen.
     * Eine E-Rechnung bleibt, wie sie ist — ihre XML ist das Original.
     */
    public function values(Request $request, IncomingEInvoice $incoming): RedirectResponse {
        $actor = $this->authorizeBilling();
        abort_if($incoming->recognition === IncomingInvoiceRecognition::Structured, 404);
        if ($incoming->transferred_at !== null) {
            return back()->with('error', __('Der Eingang ist bereits an die Buchhaltung übergeben — die Zuordnung ist dort zu ändern.'));
        }
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:64'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3'],
            'amount_net' => ['nullable', 'numeric'],
            'amount_tax' => ['nullable', 'numeric'],
            'amount_gross' => ['required', 'numeric'],
            'party_name' => ['nullable', 'string', 'max:191'],
            'party_vat_id' => ['nullable', 'string', 'max:32'],
        ]);
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $summary = (array) $incoming->summary;
        $summary['manual'] = [...$data, 'user_id' => $actor->id, 'at' => now()->toIso8601String()];

        $incoming->forceFill([
            'invoice_number' => $data['invoice_number'],
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'] ?? null,
            'currency' => strtoupper((string) $data['currency']),
            'amount_net' => isset($data['amount_net']) ? (string) $data['amount_net'] : null,
            'amount_tax' => isset($data['amount_tax']) ? (string) $data['amount_tax'] : null,
            'amount_gross' => (string) $data['amount_gross'],
            $isPurchase ? 'seller_name' : 'buyer_name' => $data['party_name'] ?? null,
            $isPurchase ? 'seller_vat_id' : 'buyer_vat_id' => $data['party_vat_id'] ?? null,
            'summary' => $summary,
        ])->save();
        $incoming->audit('incoming_einvoice.values_captured', ['number' => $data['invoice_number'], 'gross' => (string) $data['amount_gross']]);
        // Mit erfasster USt-IdNr. kann der Abgleich jetzt eindeutig sein.
        $this->matcher->autoAssign($incoming);

        return redirect()->toList('finance.incoming-invoices.index')->with('success', __('Werte erfasst.'));
    }

    /** Mehrere Eingänge derselben Partei zuordnen (Sammelaktion). */
    public function bulkAssign(Request $request): RedirectResponse {
        $actor = $this->authorizeBilling();
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['string', 'max:64'],
            'party' => ['required', 'string', 'max:80'],
        ]);
        $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Sqid::decode(IncomingEInvoice::class, $sqid), $data['ids'])));
        $assigned = 0;
        $skipped = 0;
        foreach (IncomingEInvoice::query()->whereKey($ids)->get() as $incoming) {
            try {
                $direction = $incoming->direction === DocumentDirection::Outgoing ? DocumentDirection::Outgoing : DocumentDirection::Incoming;
                $party = $this->bulkParty((string) $data['party'], $direction);
                if ($party === null || $incoming->transferred_at !== null) {
                    $skipped++;

                    continue;
                }
                $this->matcher->assign($incoming, $party, IncomingInvoiceMatchKind::Manual, $actor);
                $assigned++;
            } catch (RuntimeException|\InvalidArgumentException) {
                $skipped++;
            }
        }

        return redirect()->toList('finance.incoming-invoices.index')
            ->with($skipped > 0 ? 'warning' : 'success', __(':assigned Eingänge zugeordnet, :skipped übersprungen.', ['assigned' => $assigned, 'skipped' => $skipped]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function partyFor(DocumentDirection $direction, array $data): Supplier|Customer {
        $isPurchase = $direction === DocumentDirection::Incoming;
        $organization = $this->currentOrganization();

        return match ($data['mode']) {
            'collective' => $isPurchase ? $this->collective->supplier($organization) : $this->collective->customer($organization),
            'new' => $this->createParty($isPurchase, $data),
            default => $this->bulkParty((string) $data['party'], $direction)
                ?? throw new RuntimeException((string) __('Eingangsbelege gehören zu Lieferanten, Ausgangsbelege zu Kunden.')),
        };
    }

    /** Partei aus dem Formular; nur nicht archivierte Stammsätze der Organisation, nie ein Sammelkontakt. */
    private function existingParty(bool $isPurchase, string $sqid): Supplier|Customer {
        $class = $isPurchase ? Supplier::class : Customer::class;
        $party = $class::query()->withoutCollective()->find(Sqid::decode($class, $sqid));
        if ($party === null) {
            throw new RuntimeException((string) __('validation.exists', ['attribute' => __('validation.attributes.party')]));
        }

        return $party;
    }

    /** @param  array<string, mixed>  $data */
    private function createParty(bool $isPurchase, array $data): Supplier|Customer {
        $mapped = array_filter([
            'name' => $data['new_name'],
            'company' => $data['new_name'],
            'vat_id' => $data['new_vat_id'] ?? null,
            'email' => $data['new_email'] ?? null,
            'address_street' => $data['new_street'] ?? null,
            'address_zip' => $data['new_zip'] ?? null,
            'address_city' => $data['new_city'] ?? null,
            'country' => $data['new_country'] ?? null,
            'bank_iban' => isset($data['new_iban']) ? BankHelper::normalizeIBAN((string) $data['new_iban']) : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $profile = $isPurchase ? app(SupplierMatchProfile::class) : app(CustomerMatchProfile::class);
        $party = $profile->create($this->currentOrganization(), $mapped);
        if (! $party instanceof Supplier && ! $party instanceof Customer) {
            throw new RuntimeException('Neuanlage lieferte keine Partei.');
        }

        return $party;
    }

    /**
     * Partei aus `supplier:<sqid>`, `customer:<sqid>` oder `collective`. Die Art
     * steht mit im Wert: Sqids verschiedener Modelle können sich überschneiden.
     */
    private function bulkParty(string $value, DocumentDirection $direction): Supplier|Customer|null {
        $organization = $this->currentOrganization();
        if ($value === 'collective') {
            return $direction === DocumentDirection::Outgoing ? $this->collective->customer($organization) : $this->collective->supplier($organization);
        }
        [$kind, $sqid] = array_pad(explode(':', $value, 2), 2, '');
        if (($kind === 'supplier') !== ($direction === DocumentDirection::Incoming) || ! in_array($kind, ['supplier', 'customer'], true)) {
            return null;
        }

        return $this->existingParty($kind === 'supplier', $sqid);
    }

    private function rejectAsNoInvoice(IncomingEInvoice $incoming, string $note, \App\Models\Platform\User $actor): RedirectResponse {
        if (! $incoming->status->canTransitionTo(IncomingEInvoiceStatus::Rejected)) {
            return back()->with('error', __('Übergang :from → :to ist nicht zulässig.', ['from' => $incoming->status->value, 'to' => IncomingEInvoiceStatus::Rejected->value]));
        }
        $incoming->update([
            'status' => IncomingEInvoiceStatus::Rejected,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
        $incoming->audit('incoming_einvoice.decided', ['to' => IncomingEInvoiceStatus::Rejected->value, 'reason' => 'not_invoice']);

        return redirect()->toList('finance.incoming-invoices.index')->with('success', __('Als „keine Rechnung“ abgelegt.'));
    }

    private function authorizeBilling(): \App\Models\Platform\User {
        $user = $this->authUser();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }
}
