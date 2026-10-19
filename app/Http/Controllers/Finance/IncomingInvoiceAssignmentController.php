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
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceKindHints, IncomingInvoiceMatcher, IncomingInvoiceRules};
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
        private readonly IncomingEInvoiceService $invoices,
        private readonly IncomingInvoiceRules $rules,
        private readonly IncomingInvoiceKindHints $kindHints,
    ) {}

    public function assignForm(IncomingEInvoice $incoming): View {
        $this->authorizeBilling();
        $summary = (array) $incoming->summary;
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $address = (array) ($summary[$isPurchase ? 'seller_address' : 'buyer_address'] ?? []);

        return view('finance.incoming-invoices._assign_dialog', [
            'incoming' => $incoming,
            'partyName' => $incoming->counterpartyName(),
            'otherOpen' => $incoming->openOfSameParty()->count(),
            // Belegart-Hinweis (MVP-1216): Mahnung, Lieferschein, Versandmarke … — Vorschlag „Keine Rechnung“.
            'kindHintLabel' => $this->kindHints->label($incoming->kindHint()),
            'proposal' => $this->kindHints->proposal($incoming),
            'reminderTargets' => $incoming->reminderTargets(),
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
            'apply_to_open' => ['nullable', 'boolean'],
            'exclude_action' => ['nullable', Rule::in(['', 'reject', 'discard'])],
            'note' => ['nullable', 'required_if:mode,not_invoice', 'string', 'max:500'],
        ]);
        if ($incoming->transferred_at !== null) {
            return back()->with('error', __('Der Eingang ist bereits an die Buchhaltung übergeben — die Zuordnung ist dort zu ändern.'));
        }

        $applyToOpen = (bool) ($data['apply_to_open'] ?? false);
        if ($data['mode'] === 'not_invoice') {
            return $this->rejectAsNoInvoice($incoming, (string) $data['note'], $actor, (string) ($data['exclude_action'] ?? ''), $applyToOpen);
        }

        $direction = DocumentDirection::from((string) $data['direction']);
        $rememberSender = (bool) ($data['remember_sender'] ?? false);
        $party = null;
        try {
            DB::transaction(function () use ($incoming, $direction, $data, $actor, $rememberSender, &$party): void {
                if ($incoming->direction !== $direction) {
                    // Richtung korrigiert: die bisherige Partei passt nicht mehr.
                    $incoming->forceFill(['direction' => $direction, 'supplier_id' => null, 'customer_id' => null])->save();
                    $incoming->audit('incoming_einvoice.direction_corrected', ['direction' => $direction->value]);
                }
                $party = $this->partyFor($direction, $data);
                $this->matcher->assign($incoming, $party, IncomingInvoiceMatchKind::Manual, $actor);
                if ($rememberSender) {
                    $this->matcher->rememberSender($incoming, $party, $actor);
                }
            });
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        // Ausdrücklich gewünscht: die anderen offenen Eingänge mit demselben Gegenparteinamen (MVP-1208).
        $followers = 0;
        if ($applyToOpen && $party !== null) {
            foreach ($incoming->openOfSameParty() as $other) {
                try {
                    $this->matcher->assign($other, $party, IncomingInvoiceMatchKind::Manual, $actor);
                    $followers++;
                } catch (RuntimeException|\InvalidArgumentException) {
                    // z. B. Sammelkontakt bei Reverse Charge — bleibt offen.
                }
            }
        }
        // Neue Partei oder Absenderregel: ältere offene Eingänge derselben Gegenpartei treffen jetzt.
        if ($data['mode'] === 'new' || $rememberSender) {
            $followers += $this->matcher->rematchOpen((int) $incoming->organization_id, $incoming)['assigned'];
        }

        return redirect()->toList('finance.incoming-invoices.index')->with('success', $followers > 0
            ? trans_choice('Eingang zugeordnet, :count weiterer offener Eingang derselben Partei automatisch zugeordnet.|Eingang zugeordnet, :count weitere offene Eingänge derselben Partei automatisch zugeordnet.', $followers, ['count' => $followers])
            : __('Eingang zugeordnet.'));
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
        // Lernen aus der Korrektur (Phase 151): Werte im Wortgitter → Zonen der Lieferantenvorlage.
        $learned = app(\App\Services\Invoicing\Recognition\TemplateLearner::class)->learnFromValues($incoming, $data, $actor);
        $message = (string) __('Werte erfasst.');
        if ($learned['fields'] !== []) {
            $message .= ' ' . __('Vorlage „:name“ hat gelernt: :fields.', [
                'name' => (string) $learned['template']?->name,
                'fields' => implode(', ', array_map(\App\Services\Invoicing\Recognition\ZoneFields::label(...), $learned['fields'])),
            ]);
        }

        return redirect()->toList('finance.incoming-invoices.index')->with('success', $message);
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
        /** @var array<string, int> $skipped Grund → Anzahl; ohne Grund wüsste niemand, warum nichts passiert ist. */
        $skipped = [];
        foreach (IncomingEInvoice::query()->whereKey($ids)->get() as $incoming) {
            try {
                $direction = $incoming->direction === DocumentDirection::Outgoing ? DocumentDirection::Outgoing : DocumentDirection::Incoming;
                $party = $this->bulkParty((string) $data['party'], $direction);
                if ($party === null) {
                    $reason = (string) __('Eingangsbelege gehören zu Lieferanten, Ausgangsbelege zu Kunden.');
                } elseif ($incoming->transferred_at !== null) {
                    $reason = (string) __('Der Eingang ist bereits an die Buchhaltung übergeben — die Zuordnung ist dort zu ändern.');
                } else {
                    $this->matcher->assign($incoming, $party, IncomingInvoiceMatchKind::Manual, $actor);
                    $assigned++;

                    continue;
                }
            } catch (RuntimeException|\InvalidArgumentException $e) {
                $reason = ErrorText::for($e);
            }
            $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
        }

        $message = (string) __(':assigned Eingänge zugeordnet, :skipped übersprungen.', ['assigned' => $assigned, 'skipped' => array_sum($skipped)]);
        if ($skipped !== []) {
            arsort($skipped);
            $message .= ' ' . __('Gründe: :reasons', ['reasons' => implode(' ', array_map(
                static fn (string $reason, int $count): string => $count . ' × ' . $reason,
                array_keys($skipped),
                $skipped,
            ))]);
        }

        return redirect()->toList('finance.incoming-invoices.index')->with($skipped !== [] ? 'warning' : 'success', $message);
    }

    /** Erkennung ausgewählter Klärfälle wiederholen (Sammelaktion, MVP-1207). */
    public function bulkReanalyze(Request $request): RedirectResponse {
        $this->authorizeBilling();
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['string', 'max:64'],
        ]);
        $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Sqid::decode(IncomingEInvoice::class, $sqid), $data['ids'])));
        $recognized = 0;
        $unchanged = 0;
        $skipped = 0;
        foreach (IncomingEInvoice::query()->whereKey($ids)->get() as $incoming) {
            if (! IncomingEInvoiceService::isReanalyzable($incoming)) {
                $skipped++;
            } elseif ($this->invoices->reanalyze($incoming)) {
                $recognized++;
            } else {
                $unchanged++;
            }
        }

        return redirect()->toList('finance.incoming-invoices.index')
            ->with($recognized > 0 && $unchanged === 0 && $skipped === 0 ? 'success' : 'warning', __(':recognized Eingänge erkannt, :unchanged weiterhin Klärfall, :skipped übersprungen.', [
                'recognized' => $recognized, 'unchanged' => $unchanged, 'skipped' => $skipped,
            ]));
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

    /**
     * „Keine Rechnung“ (MVP-1208/1211): ablehnen — beim Ausschluss „gar nicht
     * aufnehmen“ danach verwerfen —, auf Wunsch die Gegenpartei als Regel merken
     * und die anderen offenen Eingänge desselben Namens gleich mit behandeln.
     *
     * `$excludeAction`: leer (nichts merken), `reject` oder `discard`.
     */
    private function rejectAsNoInvoice(IncomingEInvoice $incoming, string $note, \App\Models\Platform\User $actor, string $excludeAction, bool $applyToOpen): RedirectResponse {
        if (! $incoming->status->canTransitionTo(IncomingEInvoiceStatus::Rejected)) {
            return back()->with('error', __('Übergang :from → :to ist nicht zulässig.', ['from' => $incoming->status->value, 'to' => IncomingEInvoiceStatus::Rejected->value]));
        }
        $siblings = $applyToOpen ? $incoming->openOfSameParty() : new \Illuminate\Database\Eloquent\Collection();
        $rule = $excludeAction !== '' ? $this->rules->rememberExclusion($incoming, $actor, $excludeAction, $note) : null;
        $discard = $excludeAction === 'discard';
        $party = (string) $incoming->counterpartyName();
        $others = 0;
        try {
            foreach ([$incoming, ...$siblings->all()] as $other) {
                if (! $other->status->canTransitionTo(IncomingEInvoiceStatus::Rejected)) {
                    continue;
                }
                $this->rules->reject($other, $note, $actor, $rule !== null ? ['rule_id' => $rule->id] : []);
                if ($discard) {
                    $this->invoices->discard($other, $note, $actor);
                }
                if ($other !== $incoming) {
                    $others++;
                }
            }
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        $message = (string) ($discard ? __('Eingang verworfen.') : __('Als „keine Rechnung“ abgelegt.'));
        if ($others > 0) {
            $message .= ' ' . trans_choice($discard
                ? ':count weiterer offener Eingang von „:party“ ebenfalls verworfen.|:count weitere offene Eingänge von „:party“ ebenfalls verworfen.'
                : ':count weiterer offener Eingang von „:party“ ebenfalls abgelegt.|:count weitere offene Eingänge von „:party“ ebenfalls abgelegt.', $others, ['count' => $others, 'party' => $party]);
        }
        if ($rule !== null) {
            $message .= ' ' . ($discard
                ? __('Künftige Eingänge von „:party“ werden automatisch verworfen.', ['party' => $party])
                : __('Künftige Eingänge von „:party“ werden automatisch als keine Rechnung abgelegt.', ['party' => $party]));
        }

        return redirect()->toList('finance.incoming-invoices.index')->with('success', $message);
    }

    private function authorizeBilling(): \App\Models\Platform\User {
        $user = $this->authUser();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }
}
