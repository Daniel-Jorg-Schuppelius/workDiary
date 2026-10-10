<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicingDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Demo;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use Illuminate\Support\Collection;

/** Faktura-Vorführung: §-19-Belegkette, freie Rechnung, Abrechnungsplan und Rabatt/Skonto. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class InvoicingDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'invoices' => $context->mainCustomer === null ? 0 : $this->seedSmallBusinessInvoicing($context->organization, $context->mainCustomer, $actor),
            'free_invoices' => $context->mainCustomer === null ? 0 : $this->seedFreeInvoice($context->organization, $context->mainCustomer, $actor),
            'billing_basics' => $context->mainCustomer === null ? 0 : $this->seedBillingBasics($context->organization, $context->mainCustomer, $context->users),
            'incoming_invoices' => $this->seedIncomingInvoices($context->organization, $actor),
        ];
    }

    /** Demo-Eingänge samt Dokument; sie hängen am Demo-Benutzer und blockierten sonst dessen Löschen. */
    public function purge(Organization $organization): void {
        $incomings = \App\Models\Invoicing\IncomingEInvoice::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('sha256', array_map(static fn (string $key): ?string => self::demoSha($organization, $key), self::DEMO_INCOMING_KEYS))
            ->get(['id', 'document_id']);
        \App\Models\Invoicing\IncomingEInvoice::query()->withoutGlobalScopes()->whereKey($incomings->pluck('id'))->delete();
        \App\Models\Document\Document::query()->withoutGlobalScopes()->withTrashed()->whereKey($incomings->pluck('document_id'))->forceDelete();
    }

    /** @var list<string> */
    private const DEMO_INCOMING_KEYS = ['demo-incoming-assigned', 'demo-incoming-unrecognized'];

    private static function demoSha(Organization $organization, string $key): ?string {
        return \CommonToolkit\Helper\Data\CryptoHelper::hash($organization->id . ':' . $key);
    }

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * §-19-Demo-Ablauf (Feature 066): dokumentiert die Belegkette einer
     * Kleinunternehmer-Org — Angebot mit Annahme, Überführung in eine
     * Entwurfsrechnung (TaxResolver → 0 %, §-19-Hinweistext) und
     * Ausstellung. Bewusst OHNE die Org global auf §19 zu stellen: der
     * Steuerkontext wird am Demo-Kunden über den Org-Setting-Schalter nur
     * für die Belegerzeugung aktiviert und danach zurückgesetzt.
     */
    private function seedSmallBusinessInvoicing(Organization $organization, Customer $customer, ?User $actor): int {
        if (! $this->moduleActive('module.vertrieb')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        // §-19-Kontext temporär aktivieren (data_get-Konvention, s. TaxResolver).
        $settings = (array) ($organization->settings ?? []);
        $before = $settings['einvoice']['small_business'] ?? null;
        $settings['einvoice']['small_business'] = '1';
        $organization->settings = $settings;
        $organization->save();

        try {
            $quotes = app(\App\Services\Invoicing\QuoteService::class);
            $quote = $quotes->create([
                'customer_id' => $customer->id,
                'valid_until' => \Carbon\Carbon::now()->addDays(30)->toDateString(),
                'terms' => (string) __('Demo-Angebot: Wartung inkl. Anfahrt, Abrechnung nach Aufwand.'),
            ], [
                ['description' => (string) __('Wartungspauschale (Demo)'), 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '480.00'],
                ['description' => (string) __('Erweiterte Dokumentation (Option)'), 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '120.00', 'optional' => true],
            ], $actor);
            $quote = $quotes->approve($quote, $actor);
            ['quote' => $quote] = $quotes->send($quote, $actor);
            $quote = $quotes->accept($quote); // Vollannahme (Optionen bleiben draußen)
            $invoice = $quotes->convertToInvoice($quote, $actor);

            // Ausstellen: friert Parteien ein; §-19-Hinweis steht in den Notes.
            $invoice->freezeParties();
            $invoice->update([
                'status' => InvoiceStatus::Issued,
                'issued_on' => \Carbon\Carbon::now(),
                'due_on' => \Carbon\Carbon::now()->addDays((int) ($invoice->payment_terms_days ?? 14)),
            ]);

            return 1;
        } catch (\Throwable $e) {
            // Demo-Seeder bleibt robust: fehlende Vertriebs-Voraussetzungen
            // (z. B. deaktiviertes Modul) brechen den Gesamt-Seed nicht ab.
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: §19-Fakturakette übersprungen: ' . $e->getMessage());

            return 0;
        } finally {
            $settings = (array) ($organization->settings ?? []);
            if ($before === null) {
                unset($settings['einvoice']['small_business']);
            } else {
                $settings['einvoice']['small_business'] = $before;
            }
            $organization->settings = $settings;
            $organization->save();
        }
    }

    /**
     * Freie Rechnung ohne Zeiten (Feature 160, MVP-859): das Beispiel der
     * Feature-Doku — zwei gefertigte Regale, zusätzliches Material und eine
     * Montagepauschale (700 € netto) — als Entwurf mit Artikel-/Freitextposten.
     */
    private function seedFreeInvoice(Organization $organization, Customer $customer, ?User $actor): int {
        if (! $this->moduleActive('module.vertrieb') || $actor === null) {
            return 0;
        }
        try {
            $invoice = app(\App\Services\Invoicing\InvoiceGenerator::class)->emptyDraft($customer);
            $rows = [
                ['2', 'Stk', '250.00', 'Regal Eiche 180 × 80 cm, Sonderanfertigung laut Auftrag'],
                ['10', 'm', '8.00', 'Zusätzliches Material: Kantenleiste Eiche'],
                ['1', (string) __('invoicing.unit_flat'), '120.00', 'Montage pauschal'],
            ];
            foreach ($rows as $index => [$quantity, $unit, $price, $text]) {
                $invoice->items()->create([
                    'organization_id' => $organization->id,
                    'description' => $text,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'unit_price' => $price,
                    'service_date' => \Carbon\Carbon::now()->toDateString(),
                    'position' => $index + 1,
                ]);
            }
            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return 1;
        } catch (\Throwable $e) {
            // Demo-Seeder bleibt robust (externe Rechnungshoheit, fehlende Steuerdaten).
            return 0;
        }
    }

    /**
     * Rechnungseingang (Feature 163, MVP-1110): ein über die USt-IdNr.
     * zugeordneter Eingang aus dem Postfach und ein Klärfall ohne erkannte Daten.
     */
    private function seedIncomingInvoices(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.vertrieb') || $actor === null) {
            return 0;
        }
        try {
            $supplier = \App\Models\Supplier\Supplier::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'name' => 'Bürobedarf Muster GmbH'],
                ['company' => 'Bürobedarf Muster GmbH', 'vat_id' => 'DE299999999', 'email' => 'rechnung@buerobedarf-muster.example', 'active' => true],
            );
            $rows = [
                ['sha' => 'demo-incoming-assigned', 'title' => 'Eingangsrechnung RE-2026-1187 — Bürobedarf Muster GmbH', 'attributes' => [
                    'recognition' => \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted, 'invoice_number' => 'RE-2026-1187',
                    'seller_name' => 'Bürobedarf Muster GmbH', 'seller_vat_id' => 'DE299999999', 'issue_date' => \Carbon\Carbon::now()->subDays(3)->toDateString(),
                    'currency' => 'EUR', 'amount_net' => '120.00', 'amount_tax' => '22.80', 'amount_gross' => '142.80',
                    'supplier_id' => $supplier->id, 'match_kind' => \App\Enums\Invoicing\IncomingInvoiceMatchKind::VatId, 'matched_at' => now(),
                ]],
                ['sha' => 'demo-incoming-unrecognized', 'title' => 'Rechnungseingang ohne erkannte Daten — scan-0815.pdf', 'attributes' => [
                    'recognition' => \App\Enums\Invoicing\IncomingInvoiceRecognition::None,
                ]],
            ];
            foreach ($rows as $row) {
                $sha256 = (string) self::demoSha($organization, $row['sha']);
                if (\App\Models\Invoicing\IncomingEInvoice::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('sha256', $sha256)->exists()) {
                    continue;
                }
                $document = \App\Models\Document\Document::query()->create([
                    'organization_id' => $organization->id,
                    'title' => $row['title'],
                    'document_type' => \App\Enums\Document\DocumentType::Invoice->value,
                    'status' => \App\Enums\Document\DocumentStatus::Active->value,
                    'created_by_user_id' => $actor->id,
                ]);
                \App\Models\Invoicing\IncomingEInvoice::query()->create([
                    'organization_id' => $organization->id,
                    'document_id' => $document->id,
                    'sha256' => $sha256,
                    'source' => 'mail',
                    'sender_email' => 'rechnung@buerobedarf-muster.example',
                    'received_at' => now()->subDays(2),
                    'status' => \App\Enums\Invoicing\IncomingEInvoiceStatus::Received,
                    'summary' => [],
                    ...$row['attributes'],
                ]);
            }

            return count($rows);
        } catch (\Throwable) {
            // Demo-Seeder bleibt robust.
            return 0;
        }
    }

    /**
     * Aktiver Abrechnungsplan (MVP-415) und Rechnungsentwurf mit Positionsrabatt und Skonto (MVP-416), Phase 38.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedBillingBasics(Organization $organization, Customer $customer, Collection $users): int {
        /** @var User|null $actor */
        $actor = $users->first();
        if ($actor === null) {
            return 0;
        }
        $count = 0;

        // 3) Aktiver Abrechnungsplan (MVP-415) — monatliche Wartungspauschale.
        if ($this->moduleActive('module.vertrieb')) {
            try {
                $schedule = \App\Models\Invoicing\InvoiceSchedule::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'customer_id' => $customer->id,
                    'title' => (string) __('Wartungspauschale monatlich (Demo)'),
                ], [
                    'interval_unit' => \App\Models\Invoicing\InvoiceSchedule::UNIT_MONTH,
                    'interval_count' => 1,
                    'billing_period_mode' => 'previous',
                    'next_run_on' => \Illuminate\Support\Carbon::now()->addMonth()->startOfMonth()->toDateString(),
                    'status' => \App\Enums\Invoicing\InvoiceScheduleStatus::Active,
                    'created_by' => $actor->id,
                ]);
                if ($schedule->wasRecentlyCreated) {
                    $schedule->items()->create([
                        'organization_id' => $organization->id,
                        'position' => 1,
                        'description' => (string) __('Wartungspauschale {zeitraum}'),
                        'quantity' => '1',
                        'unit' => (string) __('Pauschale'),
                        'unit_price' => '190.00',
                        'tax_rate' => 19,
                    ]);
                }
                $count++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('Demo-Seeder: Abrechnungsplan übersprungen: ' . $e->getMessage());
            }
        }

        // 4) Rechnungsentwurf mit Positionsrabatt und Skonto (MVP-416).
        if ($this->moduleActive('module.vertrieb')) {
            try {
                $invoice = Invoice::create([
                    'organization_id' => $organization->id,
                    'customer_id' => $customer->id,
                    'number' => app(\App\Services\Invoicing\InvoiceGenerator::class)->nextNumber($organization->id),
                    'status' => InvoiceStatus::Draft,
                    'currency' => $customer->currency,
                    'tax_rate' => 19,
                    'skonto_percent' => '2.00',
                    'skonto_days' => 10,
                    'notes' => (string) __('Demo: Rechnung mit Positionsrabatt und Skonto (2 % bei Zahlung in 10 Tagen).'),
                    'created_by' => $actor->id,
                ]);
                $invoice->items()->create([
                    'organization_id' => $organization->id,
                    'service_date' => \Illuminate\Support\Carbon::now()->toDateString(),
                    'description' => (string) __('Serviceeinsatz vor Ort (Demo)'),
                    'quantity' => '3',
                    'unit' => (string) __('invoicing.unit_hour'),
                    'unit_price' => '95.00',
                    'discount_percent' => '10.00',
                    'tax_rate' => 19,
                    'position' => 1,
                ]);
                $count++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('Demo-Seeder: Rabatt/Skonto-Rechnung übersprungen: ' . $e->getMessage());
            }
        }

        return $count;
    }
}
