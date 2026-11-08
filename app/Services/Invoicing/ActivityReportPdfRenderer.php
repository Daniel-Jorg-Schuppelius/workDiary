<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityReportPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Invoicing\ActivityReportPersonDisplay;
use App\Models\Contracts\SupportingDocumentCarrier;
use App\Models\Customer\Customer;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition, ExternalInvoice};
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\Organization;
use App\Models\Time\{TimeEntry, Timesheet};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Numbering\NumberSequenceService;
use App\Settings\SettingsRegistry;
use App\Support\{DocumentLocale, MorphMap};
use Illuminate\Support\{Collection, Str};

/**
 * Leistungsnachweis zu einer Rechnung (Feature 196): je Position die verknüpften
 * Zeiten mit Datum, Person, Uhrzeit, Dauer und Tätigkeit, dazu die Freigaben
 * unterschriebener Stundenzettel. Für eine extern ausgestellte Rechnung kommen
 * die Zeiten aus den zugeordneten, eingefrorenen Übergabepositionen. Geht als eingebettete Unterlage (BG-24) in
 * die E-Rechnung; die Leistungsbeschreibung bleibt im strukturierten Teil
 * (BMF 15.10.2026 Rn. 12).
 */
class ActivityReportPdfRenderer {
    /** Obergrenze der Zeilen; darüber kürzt der Nachweis mit Hinweis. */
    public const int MAX_ENTRIES = 5000;

    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** Null, wenn keine Position abgerechnete Zeiten trägt. */
    public function output(SupportingDocumentCarrier $carrier): ?string {
        return $this->render($this->viewData($carrier), $carrier->carrierCustomer(), $carrier->carrierOrganization());
    }

    /**
     * Leistungsnachweis zu einem Übergabenachweis an ein Buchhaltungsprogramm (Feature 045/164):
     * dieselbe Darstellung, gruppiert nach Projekt. Null ohne übergebene Zeiten.
     */
    public function outputForTransfer(BillingTransfer $transfer): ?string {
        $transfer->loadMissing(['customer', 'items']);
        $ids = $transfer->items
            ->filter(static fn ($item): bool => MorphMap::is((string) $item->source_type, TimeEntry::class))
            ->pluck('source_id')
            ->all();
        $entries = TimeEntry::query()->whereIn('id', $ids)->with(['user', 'task', 'timesheet', 'project'])->get();
        $groups = $entries
            ->groupBy(static fn (TimeEntry $entry): string => (string) ($entry->project->name ?? ''))
            ->map(static fn (Collection $group, string $project): array => ['title' => $project !== '' ? $project : (string) __('invoicing.activity_report.no_project'), 'entries' => $group])
            ->values();
        $organization = Organization::query()->withoutGlobalScopes()->find($transfer->organization_id);

        return $this->render($this->build($groups, $organization, [
            'reference_label' => (string) __('invoicing.activity_report.transfer'),
            'reference_value' => (string) $transfer->getKey(),
            'reference_date' => $transfer->transferred_at ?? $transfer->created_at,
            'customer_name' => $transfer->customer->company ?: $transfer->customer->name,
        ]), $transfer->customer, $organization);
    }

    public function hasEntries(SupportingDocumentCarrier $carrier): bool {
        return $this->groupsFor($carrier)->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(SupportingDocumentCarrier $carrier): array {
        $groups = $this->groupsFor($carrier);
        if ($carrier instanceof ExternalInvoice) {
            return $this->build($groups, $carrier->organization, [
                'reference_label' => (string) __($carrier->isCreditNote() ? 'invoicing.activity_report.credit_note' : 'invoicing.activity_report.invoice'),
                'reference_value' => $carrier->external_number ?: (string) __('invoicing.activity_report.draft'),
                'reference_date' => $carrier->issued_on,
                'customer_name' => $carrier->customer === null ? null : ($carrier->customer->company ?: $carrier->customer->name),
            ]);
        }
        /** @var Invoice $invoice */
        $invoice = $carrier;

        return $this->build($groups, $invoice->organization, [
            'reference_label' => (string) __('invoicing.activity_report.invoice'),
            'reference_value' => NumberSequenceService::isDraft((string) $invoice->number) ? (string) __('invoicing.activity_report.draft') : (string) $invoice->number,
            'reference_date' => $invoice->issued_on,
            'customer_name' => $invoice->customer->company ?: $invoice->customer->name,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function render(array $data, ?Customer $customer, ?Organization $organization): ?string {
        if ($data['groups'] === []) {
            return null;
        }

        return DocumentLocale::within($customer, $organization, fn (): string => $this->design->renderPdf(
            RenderDocumentKind::ActivityReport,
            'invoices.activity-report-pdf',
            $data,
            $organization,
            payload: $organization === null ? null : $this->design->payloadFor($organization, RenderDocumentKind::ActivityReport, $customer?->id !== null ? (int) $customer->id : null),
        ));
    }

    /**
     * @param  iterable<int, array{title: string, entries: iterable<int, TimeEntry>, position?: int}>  $source
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function build(iterable $source, ?Organization $organization, array $meta): array {
        $display = $this->personDisplay($organization);
        $groups = [];
        $approvals = [];
        $shown = 0;
        $total = 0;
        $dates = [];

        foreach ($source as $group) {
            $rows = [];
            $minutes = 0;
            foreach ($group['entries'] as $entry) {
                $total++;
                $minutes += (int) $entry->minutes;
                $day = $entry->started_at ?? $entry->date ?? $entry->timesheet?->work_date;
                if ($day !== null) {
                    $dates[] = $day->copy()->startOfDay();
                }
                if ($entry->timesheet !== null && $entry->timesheet->isSigned()) {
                    $approvals[$entry->timesheet->id] = $this->approval($entry->timesheet, $display);
                }
                if ($shown >= self::MAX_ENTRIES) {
                    continue;
                }
                $shown++;
                $rows[] = [
                    'date' => $day,
                    'person' => $display->format($entry->user?->name),
                    'started_at' => $entry->started_at,
                    'ended_at' => $entry->ended_at,
                    'minutes' => (int) $entry->minutes,
                    'description' => trim((string) $entry->description),
                    'task' => $entry->task?->title,
                ];
            }
            $groups[] = ['title' => $group['title'], 'position' => $group['position'] ?? null, 'rows' => $rows, 'minutes' => $minutes];
        }

        usort($dates, static fn ($a, $b): int => $a <=> $b);
        ksort($approvals);

        return [
            ...$meta,
            'groups' => $groups,
            'approvals' => array_values($approvals),
            'total_minutes' => array_sum(array_column($groups, 'minutes')),
            'period_from' => $dates[0] ?? null,
            'period_to' => $dates === [] ? null : end($dates),
            'shown' => $shown,
            'total' => $total,
            'show_person' => $display !== ActivityReportPersonDisplay::None,
        ];
    }

    /**
     * Unterschriebene Stundenzettel der abgerechneten Zeiten (für die Beilage als eigene Datei).
     *
     * @return Collection<int, Timesheet>
     */
    public function signedTimesheets(SupportingDocumentCarrier $carrier): Collection {
        return $this->groupsFor($carrier)
            ->flatMap(static fn (array $group): Collection => $group['entries'])
            ->map(static fn (TimeEntry $entry): ?Timesheet => $entry->timesheet)
            ->filter(static fn (?Timesheet $sheet): bool => $sheet !== null && $sheet->isSigned())
            ->unique('id')
            ->sortBy(static fn (Timesheet $sheet): string => $sheet->work_date->toDateString() . '#' . str_pad((string) $sheet->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Gruppen des Nachweises je Rechnungsposition; Positionen ohne Zeiten fallen weg.
     *
     * @return Collection<int, array{title: string, position: int, entries: Collection<int, TimeEntry>}>
     */
    private function groupsFor(SupportingDocumentCarrier $carrier): Collection {
        if ($carrier instanceof ExternalInvoice) {
            return $this->entriesByPosition($carrier);
        }
        /** @var Invoice $carrier */

        return $this->entriesByItem($carrier)->map(static fn (array $group): array => [
            'title' => (string) __('invoicing.activity_report.position', [
                'position' => $group['item']->position,
                'description' => Str::limit((string) $group['item']->description, 160),
            ]),
            'position' => (int) $group['item']->position,
            'entries' => $group['entries'],
        ]);
    }

    /**
     * Zeiten der zugeordneten Übergabepositionen in Zuordnungsreihenfolge (Feature 196, externe Rechnung).
     *
     * @return Collection<int, array{title: string, position: int, entries: Collection<int, TimeEntry>}>
     */
    private function entriesByPosition(ExternalInvoice $invoice): Collection {
        $positions = $invoice->positions()->get();
        $ids = $positions->filter(static fn (BillingTransferPosition $p): bool => $p->source_kind === BillingTransferPosition::KIND_TIME)
            ->flatMap(static fn (BillingTransferPosition $p): array => array_map('intval', (array) $p->source_ids))
            ->unique()->values()->all();
        $entries = TimeEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $invoice->organization_id)
            ->whereIn('id', $ids)
            ->with(['user', 'task', 'timesheet'])
            ->get()->keyBy('id');

        return $positions->values()
            ->map(static function (BillingTransferPosition $position, int $index) use ($entries): array {
                $own = collect((array) $position->source_ids)
                    ->map(static fn ($id): ?TimeEntry => $entries->get((int) $id))
                    ->filter()
                    ->sortBy(static fn (TimeEntry $e): string => (($e->started_at ?? $e->date)?->toIso8601String() ?? '') . '#' . str_pad((string) $e->id, 10, '0', STR_PAD_LEFT))
                    ->values();

                return [
                    'title' => (string) __('invoicing.activity_report.position', [
                        'position' => $index + 1,
                        'description' => Str::limit((string) $position->name, 160),
                    ]),
                    'position' => $index + 1,
                    'entries' => $own->toBase(),
                ];
            })
            ->filter(static fn (array $group): bool => $group['entries']->isNotEmpty())
            ->values();
    }

    /**
     * Abgerechnete Zeiten je Position in Belegreihenfolge; Positionen ohne Zeiten fallen weg.
     *
     * @return Collection<int, array{item: InvoiceItem, entries: Collection<int, TimeEntry>}>
     */
    private function entriesByItem(Invoice $invoice): Collection {
        $invoice->loadMissing(['items.timeEntries.user', 'items.timeEntries.task', 'items.timeEntries.timesheet', 'items.timeEntry.user', 'items.timeEntry.task', 'items.timeEntry.timesheet']);

        return $invoice->pricedItems()
            ->map(static function (InvoiceItem $item): array {
                $entries = $item->timeEntries;
                if ($item->timeEntry !== null && ! $entries->contains('id', $item->timeEntry->id)) {
                    $entries = $entries->push($item->timeEntry);
                }

                return [
                    'item' => $item,
                    'entries' => $entries->sortBy(static fn (TimeEntry $e): string => (($e->started_at ?? $e->date)?->toIso8601String() ?? '') . '#' . str_pad((string) $e->id, 10, '0', STR_PAD_LEFT))->values()->toBase(),
                ];
            })
            ->filter(static fn (array $group): bool => $group['entries']->isNotEmpty())
            ->values();
    }

    /** @return array<string, mixed> */
    private function approval(Timesheet $timesheet, ActivityReportPersonDisplay $display): array {
        return [
            'date' => $timesheet->work_date,
            'person' => $display->format($timesheet->user?->name),
            'signer' => $timesheet->customer_name,
            'role' => $timesheet->customer_role,
            'signed_at' => $timesheet->signed_at,
            'hash' => $timesheet->signature_hash,
        ];
    }

    private function personDisplay(?Organization $organization): ActivityReportPersonDisplay {
        $value = $organization === null ? null : app(SettingsRegistry::class)->effective('einvoice.activity_report_person_display', $organization)->value;

        return ActivityReportPersonDisplay::tryFrom((string) $value) ?? ActivityReportPersonDisplay::FullName;
    }
}
