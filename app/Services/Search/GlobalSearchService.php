<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\User\Permission;
use App\Models\{Asset, Attachment, Customer, DiaryEntry, Document, Expense, FormSubmission, PerDiemTrip, Project, User};
use App\Services\Asset\AssetFormOptions;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{CarbonFmt, OrganizationContext};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\Gate;

/**
 * Gemeinsame Treffer-Queries der globalen Suche (MVP-014, Feature 023):
 * Command-Palette (Limit 5 je Gruppe) UND Vollergebnisseite `/suche`
 * (Vollaudit 2026-07, M8) nutzen exakt dieselben rechte- und org-sicheren
 * Gruppen — keine zweite Sichtbarkeitslogik.
 *
 * Seit Feature 153 kommen Aufträge, Kommunikation, Wissen und Kommentare
 * (plus Zeiten, Stundenzettel, Tickets, Protokolle, offene Punkte) aus dem
 * Tätigkeitsindex ({@see ActivitySearchService}) als Gruppe „Tätigkeiten";
 * hier bleiben die Stammdaten und Objekte.
 *
 * Filter (M8): Domäne (Gruppen-Key), Zeitraum (domänenspezifische
 * Datumsspalte), Person und Kunde. Gruppen ohne Personen-/Kundenbezug liefern
 * bei gesetztem Personen-/Kundenfilter bewusst keine Treffer (ehrlich statt
 * still ignoriert).
 *
 * Anzeige-Zeitstempel (W4.2): laufen über {@see CarbonFmt} — datetime-Spalten
 * (UTC) erst via orgTz() in die Anzeige-Zeitzone, reine date-Casts direkt fdate().
 *
 * @phpstan-type SearchItem array{id: int|string, title: string, subtitle: string|null, url: string}
 * @phpstan-type SearchGroup array{key: string, label: string, icon: string, items: list<array{id: int|string, title: string, subtitle: string|null, url: string}>}
 * @phpstan-type SearchFilters array{domain?: string|null, from?: string|null, to?: string|null, person?: int|null, customer?: int|null}
 */
class GlobalSearchService {
    public const ACTIVITIES = 'activities';

    public function __construct(
        private readonly FeatureFlagResolver $featureFlags,
        private readonly AssetFormOptions $assetOptions,
        private readonly ActivitySearchService $activities,
    ) {}

    /**
     * Verfügbare Domänen (Key => Label) für Filter-UI.
     *
     * @return array<string, string>
     */
    public function domains(): array {
        return [
            self::ACTIVITIES => (string) __('search.group.activities'),
            'customers' => (string) __('Kunden'),
            'projects' => (string) __('Projekte'),
            'assets' => (string) __('Objekte & Assets'),
            'expenses' => (string) __('Spesen'),
            'per_diem_trips' => (string) __('Reisekosten'),
            'users' => (string) __('Mitarbeiter'),
            'documents' => (string) __('document.title.index'),
            'forms' => (string) __('form.title.submissions'),
            'attachments' => (string) __('Anhänge'),
        ];
    }

    /**
     * Alle sichtbaren Treffergruppen für den Begriff (leer gefilterte Gruppen
     * werden entfernt). Die Vollergebnisseite zeigt die Tätigkeiten selbst
     * und fragt deshalb ohne sie.
     *
     * @param  SearchFilters  $filters
     * @return list<SearchGroup>
     */
    public function groups(User $user, string $term, array $filters = [], int $limit = 5, bool $withActivities = true): array {
        $orgId = OrganizationContext::currentId() ?? $user->organization_id;
        $domain = $filters['domain'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $person = $filters['person'] ?? null;
        $customer = $filters['customer'] ?? null;

        $wants = static fn(string $key): bool => $domain === null || $domain === $key;
        $range = static function ($query, string $column) use ($from, $to): void {
            if ($from !== null) {
                $query->whereDate($column, '>=', $from);
            }
            if ($to !== null) {
                $query->whereDate($column, '<=', $to);
            }
        };

        $groups = [];

        // Tätigkeiten (Feature 153): Zeiten, Aufträge inkl. Kommentare,
        // Stundenzettel, Tickets, Protokolle, offene Punkte, Kommunikation,
        // Wissen — Rechte je Quelle im ActivitySearchVisibility.
        if ($withActivities && $wants(self::ACTIVITIES)) {
            $groups[] = $this->makeGroup(
                self::ACTIVITIES,
                (string) __('search.group.activities'),
                'manage_search',
                $this->activities->typeAhead($user, $term, ['from' => $from, 'to' => $to, 'person' => $person, 'customer' => $customer], $limit),
            );
        }

        if ($wants('customers') && $person === null && $customer === null) {
            $query = Customer::query()
                ->where('organization_id', $orgId)
                ->where(fn($q) => $q->whereLikeEscaped('name', $term)
                    ->orWhereLikeEscaped('number', $term)
                    ->orWhereLikeEscaped('email', $term));
            $range($query, 'created_at');
            $groups[] = $this->makeGroup(
                'customers',
                (string) __('Kunden'),
                'badge',
                $query->orderBy('name')->limit($limit)->get()
                    ->map(fn(Customer $c) => [
                        'id' => $c->id,
                        'title' => (string) $c->name,
                        'subtitle' => trim(($c->number ? '#' . $c->number : '') . ($c->email ? ' · ' . $c->email : '')) ?: null,
                        'url' => route('customers.show', $c),
                    ])
                    ->all()
            );
        }

        if ($wants('projects') && $person === null) {
            $query = Project::query()
                ->where('organization_id', $orgId)
                ->where(fn($q) => $q->whereLikeEscaped('name', $term))
                ->when($customer !== null, fn($q) => $q->where('customer_id', $customer))
                ->with('customer:id,name');
            $range($query, 'created_at');
            $groups[] = $this->makeGroup(
                'projects',
                (string) __('Projekte'),
                'folder_special',
                $query->orderBy('name')->limit($limit)->get()
                    ->map(fn(Project $p) => [
                        'id' => $p->id,
                        'title' => (string) $p->name,
                        'subtitle' => $p->customer?->name,
                        'url' => route('projects.show', $p),
                    ])
                    ->all()
            );
        }

        // Objekte & Assets (Vollreview W5.2): nur mit asset.view (AssetPolicy::
        // viewAny, Admin-Bypass); gefunden über Name sowie Asset-/Inventar-/
        // Seriennummer. Kein Personenbezug → Personen-Filter liefert bewusst
        // keine Treffer; Kunden-Filter über customer_id.
        if ($wants('assets') && $person === null && Gate::forUser($user)->allows('viewAny', Asset::class)) {
            $assetQuery = Asset::query()
                ->where('organization_id', $orgId)
                ->where(fn($q) => $q->whereLikeEscaped('name', $term)
                    ->orWhereLikeEscaped('asset_no', $term)
                    ->orWhereLikeEscaped('inventory_no', $term)
                    ->orWhereLikeEscaped('serial_no', $term))
                ->when($customer !== null, fn($q) => $q->where('customer_id', $customer))
                ->with('customer:id,name');
            $range($assetQuery, 'created_at');
            $statusLabels = $this->assetOptions->statusOptions();
            $groups[] = $this->makeGroup(
                'assets',
                (string) __('Objekte & Assets'),
                'precision_manufacturing',
                $assetQuery->orderBy('name')->limit($limit)->get()
                    ->map(fn(Asset $a) => [
                        'id' => $a->id,
                        'title' => (string) $a->name,
                        'subtitle' => trim('#' . $a->asset_no
                            . ' · ' . ($statusLabels[$a->status->value] ?? $a->status->value)
                            . ($a->inventory_no ? ' · ' . $a->inventory_no : ($a->serial_no ? ' · ' . $a->serial_no : ''))
                            . ($a->customer ? ' · ' . $a->customer->name : '')),
                        'url' => route('assets.show', $a),
                    ])
                    ->all()
            );
        }

        if ($wants('expenses')) {
            $expenseQuery = Expense::query()
                ->where('organization_id', $orgId)
                ->where(fn($q) => $q->whereLikeEscaped('vendor', $term)
                    ->orWhereLikeEscaped('description', $term)
                    ->orWhereLikeEscaped('reimbursement_reference', $term))
                ->when($person !== null, fn($q) => $q->where('user_id', $person))
                ->when($customer !== null, fn($q) => $q->where('customer_id', $customer));
            // ExpensePolicy::viewAny ist bewusst `true` (Seitenzugriff) — die
            // DATEN-Sichtbarkeit ist wie im Web-Index eigene Belege, alle nur
            // für Admins (Freigabe via Admin-Bypass). Vorher lief die Suche
            // hier zu offen (Vollaudit-Nacharbeit M8).
            if (! $user->isAdmin()) {
                $expenseQuery->where('user_id', $user->id);
            }
            $range($expenseQuery, 'date');
            $groups[] = $this->makeGroup(
                'expenses',
                (string) __('Spesen'),
                'receipt_long',
                $expenseQuery->orderByDesc('date')->limit($limit)->get()
                    ->map(fn(Expense $e) => [
                        'id' => $e->id,
                        'title' => $e->vendor ?: ($e->description ?: (string) __('Spese #:id', ['id' => $e->id])),
                        'subtitle' => CarbonFmt::fdate($e->date)
                            . ' · ' . NumberHelper::toGermanFormat(($e->amount_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) . ' €',
                        'url' => route('expenses.show', $e),
                    ])
                    ->all()
            );
        }

        if ($wants('per_diem_trips') && $customer === null) {
            $tripQuery = PerDiemTrip::query()
                ->where('organization_id', $orgId)
                ->where(fn($q) => $q->whereLikeEscaped('location', $term)
                    ->orWhereLikeEscaped('purpose', $term)
                    ->orWhereLikeEscaped('country', $term))
                ->when($person !== null, fn($q) => $q->where('user_id', $person));
            // Analog zu Spesen: eigene Reisen, alle nur für Admins (s. o.).
            if (! $user->isAdmin()) {
                $tripQuery->where('user_id', $user->id);
            }
            $range($tripQuery, 'started_at');
            $groups[] = $this->makeGroup(
                'per_diem_trips',
                (string) __('Reisekosten'),
                'flight',
                $tripQuery->orderByDesc('started_at')->limit($limit)->get()
                    ->map(fn(PerDiemTrip $t) => [
                        'id' => $t->id,
                        'title' => trim(($t->location ?: '—') . ($t->country ? ' (' . $t->country . ')' : '')),
                        'subtitle' => CarbonFmt::fdate(CarbonFmt::orgTz($t->started_at))
                            . ($t->purpose ? ' · ' . mb_strimwidth($t->purpose, 0, 60, '…') : ''),
                        'url' => route('per-diem-trips.show', $t),
                    ])
                    ->all()
            );
        }

        // Nur Admin/Org-Manager dürfen Mitarbeiter durchsuchen.
        if ($wants('users') && $customer === null && ($user->isAdmin() || Gate::forUser($user)->allows('manage-members'))) {
            $query = User::query()
                ->where('organization_id', $orgId)
                ->where(fn($q) => $q->whereLikeEscaped('name', $term)->orWhereLikeEscaped('email', $term))
                ->when($person !== null, fn($q) => $q->whereKey($person));
            $range($query, 'created_at');
            $groups[] = $this->makeGroup(
                'users',
                (string) __('Mitarbeiter'),
                'group',
                $query->orderBy('name')->limit($limit)->get()
                    ->map(fn(User $u) => [
                        'id' => $u->id,
                        'title' => (string) $u->name,
                        'subtitle' => $u->email,
                        'url' => Gate::forUser($user)->allows('manage-members') ? route('org.members.index') : '#',
                    ])
                    ->all()
            );
        }

        // Dokumente (MVP-031): document.viewAny UND aktives Modul (Plan/Lizenz).
        // Keine Detailseite — Link auf die vorgefilterte Liste (?q=Titel).
        if (
            $wants('documents') && $person === null && $customer === null
            && $this->featureFlags->isEnabled('module.documents') && Gate::forUser($user)->allows('viewAny', Document::class)
        ) {
            $query = Document::query()->visibleTo($user)->whereLikeEscaped('title', $term);
            $range($query, 'updated_at');
            $groups[] = $this->makeGroup(
                'documents',
                (string) __('document.title.index'),
                'folder_open',
                $query->latest('updated_at')->limit($limit)->get()
                    ->map(fn(Document $d) => [
                        'id' => $d->id,
                        'title' => (string) $d->title,
                        'subtitle' => $d->document_type->label() . ' · ' . $d->effectiveStatus()->label(),
                        'url' => route('documents.index', ['q' => $d->title]),
                    ])
                    ->all()
            );
        }

        // Formulare (Feature 032): Vorlagen-Sicht sieht alle Submissions, alle
        // anderen ausschließlich die EIGENEN (wie FormSubmissionController).
        if (
            $wants('forms') && $customer === null
            && $this->featureFlags->isEnabled('module.forms') && Gate::forUser($user)->allows('viewAny', FormSubmission::class)
        ) {
            $submissionQuery = FormSubmission::query()
                ->with(['template', 'submitter'])
                ->whereHas('template', fn($q) => $q->whereLikeEscaped('name', $term))
                ->when($person !== null, fn($q) => $q->where('submitted_by_user_id', $person));
            if (! ($user->isAdmin() || $user->can(Permission::FormTemplateViewAny->value))) {
                $submissionQuery->where('submitted_by_user_id', $user->id);
            }
            $range($submissionQuery, 'submitted_at');
            $groups[] = $this->makeGroup(
                'forms',
                (string) __('form.title.submissions'),
                'edit_note',
                $submissionQuery->orderByDesc('submitted_at')->limit($limit)->get()
                    ->map(fn(FormSubmission $s) => [
                        'id' => $s->id,
                        'title' => $s->template->name ?? __('form.title.submissions') . ' #' . $s->id,
                        'subtitle' => CarbonFmt::fdate(CarbonFmt::orgTz($s->submitted_at))
                            . ($s->submitter ? ' · ' . $s->submitter->name : ''),
                        'url' => route('form-submissions.show', $s),
                    ])
                    ->all()
            );
        }

        // Anhang-Metadaten (MVP-014-Domäne, Vollaudit M8): Dateiname; sichtbar
        // sind Anhänge sichtbarer Aufträge sowie eigene Uploads. Download läuft
        // ohnehin über die AttachmentPolicy. Volltext/OCR bleibt Folge-MVP.
        if ($wants('attachments') && $customer === null) {
            $attachmentQuery = Attachment::query()
                ->where('organization_id', $orgId)
                ->whereLikeEscaped('original_name', $term)
                ->whereNull('meta_type')
                ->when($person !== null, fn($q) => $q->where('user_id', $person))
                ->where(function ($q) use ($user): void {
                    $q->where('user_id', $user->id)
                        ->orWhere(function ($p) use ($user): void {
                            $p->where('attachable_type', DiaryEntry::class)
                                ->whereExists(function ($sub) use ($user): void {
                                    $sub->selectRaw('1')
                                        ->from('diary_entries')
                                        ->whereColumn('diary_entries.id', 'attachments.attachable_id');
                                    if (! ($user->isAdmin() || $user->can(Permission::DiaryViewAny->value))) {
                                        $sub->where(fn($w) => $w->where('diary_entries.user_id', $user->id)
                                            ->orWhere('diary_entries.assigned_user_id', $user->id));
                                    }
                                });
                        });
                });
            $range($attachmentQuery, 'created_at');
            $groups[] = $this->makeGroup(
                'attachments',
                (string) __('Anhänge'),
                'attach_file',
                $attachmentQuery->orderByDesc('created_at')->limit($limit)->get()
                    ->map(fn(Attachment $a) => [
                        'id' => $a->id,
                        'title' => (string) $a->original_name,
                        'subtitle' => ($a->created_at !== null ? CarbonFmt::fdate(CarbonFmt::orgTz($a->created_at)) : '')
                            . ($a->mime ? ' · ' . $a->mime : ''),
                        'url' => route('attachments.download', $a),
                    ])
                    ->all()
            );
        }

        // Leere Gruppen entfernen.
        return array_values(array_filter($groups, static fn(array $g): bool => count($g['items']) > 0));
    }

    /**
     * @param  array<int, array{id: int|string, title: string, subtitle: string|null, url: string}>  $items
     * @return SearchGroup
     */
    private function makeGroup(string $key, string $label, string $icon, array $items): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'items' => array_values($items),
        ];
    }
}
