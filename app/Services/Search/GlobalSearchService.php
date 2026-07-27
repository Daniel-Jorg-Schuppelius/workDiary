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

use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission;
use App\Models\{Attachment, Comment, CommunicationNote, Customer, DiaryEntry, Document, Expense, FormSubmission, KnowledgeArticle, PerDiemTrip, Project, User};
use App\Services\Licensing\FeatureFlagResolver;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\Gate;

/**
 * Gemeinsame Treffer-Queries der globalen Suche (MVP-014, Feature 023):
 * Command-Palette (Limit 5 je Gruppe) UND Vollergebnisseite `/suche`
 * (Vollaudit 2026-07, M8) nutzen exakt dieselben rechte- und org-sicheren
 * Gruppen — keine zweite Sichtbarkeitslogik.
 *
 * Filter (M8): Domäne (Gruppen-Key), Zeitraum (domänenspezifische
 * Datumsspalte), Person und Kunde. Gruppen ohne Personen-/Kundenbezug liefern
 * bei gesetztem Personen-/Kundenfilter bewusst keine Treffer (ehrlich statt
 * still ignoriert). Status-/Tag-/Sichtbarkeits-Filter sind bewusst
 * zurückgestellt (heterogene Domänen) — in der Feature-Doku ausgewiesen.
 *
 * Neue MVP-014-Domänen (M8): Kommentare (Comment.body, Sichtbarkeit über den
 * Auftrag) und Anhang-Metadaten (Attachment.original_name; Aufträge des
 * Nutzers bzw. eigene Uploads — Volltext/OCR bleibt Folge-MVP).
 *
 * @phpstan-type SearchItem array{id: int|string, title: string, subtitle: string|null, url: string}
 * @phpstan-type SearchGroup array{key: string, label: string, icon: string, items: list<array{id: int|string, title: string, subtitle: string|null, url: string}>}
 * @phpstan-type SearchFilters array{domain?: string|null, from?: string|null, to?: string|null, person?: int|null, customer?: int|null}
 */
class GlobalSearchService {
    public function __construct(private readonly FeatureFlagResolver $featureFlags) {}

    /**
     * Verfügbare Domänen (Key => Label) für Filter-UI.
     *
     * @return array<string, string>
     */
    public function domains(): array {
        return [
            'customers' => (string) __('Kunden'),
            'projects' => (string) __('Projekte'),
            'diary' => (string) __('Aufträge'),
            'expenses' => (string) __('Spesen'),
            'per_diem_trips' => (string) __('Reisekosten'),
            'users' => (string) __('Mitarbeiter'),
            'communication' => (string) __('communication.title.index'),
            'documents' => (string) __('document.title.index'),
            'knowledge' => (string) __('knowledge.title.index'),
            'forms' => (string) __('form.title.submissions'),
            'comments' => (string) __('Kommentare'),
            'attachments' => (string) __('Anhänge'),
        ];
    }

    /**
     * Alle sichtbaren Treffergruppen für den Begriff (leer gefilterte Gruppen
     * werden entfernt).
     *
     * @param  SearchFilters  $filters
     * @return list<SearchGroup>
     */
    public function groups(User $user, string $term, array $filters = [], int $limit = 5): array {
        $orgId = $user->organization_id;
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

        if ($wants('customers') && $person === null && $customer === null) {
            $query = Customer::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->whereLikeEscaped('name', $term)
                    ->orWhereLikeEscaped('number', $term)
                    ->orWhereLikeEscaped('email', $term));
            $range($query, 'created_at');
            $groups[] = $this->makeGroup('customers', (string) __('Kunden'), 'badge',
                $query->orderBy('name')->limit($limit)->get()
                    ->map(fn(Customer $c) => [
                        'id' => $c->id,
                        'title' => (string) $c->name,
                        'subtitle' => trim(($c->number ? '#' . $c->number : '') . ($c->email ? ' · ' . $c->email : '')) ?: null,
                        'url' => route('customers.show', $c),
                    ])
                    ->all());
        }

        if ($wants('projects') && $person === null) {
            $query = Project::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->whereLikeEscaped('name', $term))
                ->when($customer !== null, fn($q) => $q->where('customer_id', $customer))
                ->with('customer:id,name');
            $range($query, 'created_at');
            $groups[] = $this->makeGroup('projects', (string) __('Projekte'), 'folder_special',
                $query->orderBy('name')->limit($limit)->get()
                    ->map(fn(Project $p) => [
                        'id' => $p->id,
                        'title' => (string) $p->name,
                        'subtitle' => $p->customer?->name,
                        'url' => route('projects.show', $p),
                    ])
                    ->all());
        }

        // Aufträge / Tagebucheinträge (MVP-014): Sichtbarkeit wie der Index — ohne
        // diary.viewAny (und kein Admin) nur EIGENE bzw. zugewiesene Aufträge.
        if ($wants('diary')) {
            $diaryQuery = $this->visibleDiaryQuery($user)
                ->where(fn($q) => $q->whereLikeEscaped('title', $term)
                    ->orWhereLikeEscaped('content', $term)
                    ->orWhereLikeEscaped('response', $term))
                ->when($person !== null, fn($q) => $q->where(fn($p) => $p->where('user_id', $person)->orWhere('assigned_user_id', $person)))
                ->when($customer !== null, fn($q) => $q->where('customer_id', $customer));
            $range($diaryQuery, 'start_at');
            $groups[] = $this->makeGroup('diary', (string) __('Aufträge'), 'assignment',
                $diaryQuery->with('customer:id,name')->orderByDesc('start_at')->limit($limit)->get()
                    ->map(fn(DiaryEntry $d) => [
                        'id' => $d->id,
                        'title' => $d->title ?: ($d->content ? mb_strimwidth($d->content, 0, 60, '…') : (string) __('Auftrag #:id', ['id' => $d->id])),
                        'subtitle' => trim($d->status->label()
                            . ($d->customer ? ' · ' . $d->customer->name : '')
                            . ($d->start_at ? ' · ' . $d->start_at->format('d.m.Y') : '')),
                        'url' => route('diary.show', $d),
                    ])
                    ->all());
        }

        if ($wants('expenses')) {
            $expenseQuery = Expense::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
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
            $groups[] = $this->makeGroup('expenses', (string) __('Spesen'), 'receipt_long',
                $expenseQuery->orderByDesc('date')->limit($limit)->get()
                    ->map(fn(Expense $e) => [
                        'id' => $e->id,
                        'title' => $e->vendor ?: ($e->description ?: (string) __('Spese #:id', ['id' => $e->id])),
                        'subtitle' => $e->date->format('d.m.Y')
                            . ' · ' . NumberHelper::toGermanFormat(($e->amount_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) . ' €',
                        'url' => route('expenses.show', $e),
                    ])
                    ->all());
        }

        if ($wants('per_diem_trips') && $customer === null) {
            $tripQuery = PerDiemTrip::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->whereLikeEscaped('location', $term)
                    ->orWhereLikeEscaped('purpose', $term)
                    ->orWhereLikeEscaped('country', $term))
                ->when($person !== null, fn($q) => $q->where('user_id', $person));
            // Analog zu Spesen: eigene Reisen, alle nur für Admins (s. o.).
            if (! $user->isAdmin()) {
                $tripQuery->where('user_id', $user->id);
            }
            $range($tripQuery, 'started_at');
            $groups[] = $this->makeGroup('per_diem_trips', (string) __('Reisekosten'), 'flight',
                $tripQuery->orderByDesc('started_at')->limit($limit)->get()
                    ->map(fn(PerDiemTrip $t) => [
                        'id' => $t->id,
                        'title' => trim(($t->location ?: '—') . ($t->country ? ' (' . $t->country . ')' : '')),
                        'subtitle' => $t->started_at->format('d.m.Y')
                            . ($t->purpose ? ' · ' . mb_strimwidth($t->purpose, 0, 60, '…') : ''),
                        'url' => route('per-diem-trips.show', $t),
                    ])
                    ->all());
        }

        // Nur Admin/Org-Manager dürfen Mitarbeiter durchsuchen.
        if ($wants('users') && $customer === null && ($user->isAdmin() || Gate::forUser($user)->allows('manage-members'))) {
            $query = User::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->whereLikeEscaped('name', $term)->orWhereLikeEscaped('email', $term))
                ->when($person !== null, fn($q) => $q->whereKey($person));
            $range($query, 'created_at');
            $groups[] = $this->makeGroup('users', (string) __('Mitarbeiter'), 'group',
                $query->orderBy('name')->limit($limit)->get()
                    ->map(fn(User $u) => [
                        'id' => $u->id,
                        'title' => (string) $u->name,
                        'subtitle' => $u->email,
                        'url' => Gate::forUser($user)->allows('manage-members') ? route('org.members.index') : '#',
                    ])
                    ->all());
        }

        // Kommunikationsnotizen (MVP-012): nur mit communication.viewAny; der
        // visibleTo-Scope blendet vertrauliche Notizen Dritter aus.
        if ($wants('communication') && Gate::forUser($user)->allows('viewAny', CommunicationNote::class)) {
            $noteQuery = CommunicationNote::query()
                ->visibleTo($user)
                ->whereLikeEscaped('subject', $term)
                ->when($person !== null, fn($q) => $q->where('created_by_user_id', $person))
                ->when($customer !== null, fn($q) => $q->where(fn($p) => $p
                    ->where('notable_type', Customer::class)->where('notable_id', $customer)))
                ->with('notable');
            $range($noteQuery, 'occurred_at');
            $noteItems = [];
            foreach ($noteQuery->orderByDesc('occurred_at')->limit($limit)->get() as $n) {
                $url = $this->communicationNoteUrl($n);
                if ($url === null) {
                    continue; // Bezug fehlt (z. B. soft-deleted) → kein Deep-Link möglich.
                }
                $notableName = $this->notableName($n);
                $noteItems[] = [
                    'id' => $n->id,
                    'title' => (string) $n->subject,
                    'subtitle' => $n->occurred_at->format('d.m.Y')
                        . ' · ' . $n->type->label()
                        . ($notableName !== null ? ' · ' . $notableName : ''),
                    'url' => $url,
                ];
            }
            $groups[] = $this->makeGroup('communication', (string) __('communication.title.index'), 'forum', $noteItems);
        }

        // Dokumente (MVP-031): document.viewAny UND aktives Modul (Plan/Lizenz).
        // Keine Detailseite — Link auf die vorgefilterte Liste (?q=Titel).
        if ($wants('documents') && $person === null && $customer === null
            && $this->featureFlags->isEnabled('module.documents') && Gate::forUser($user)->allows('viewAny', Document::class)) {
            $query = Document::query()->whereLikeEscaped('title', $term);
            $range($query, 'updated_at');
            $groups[] = $this->makeGroup('documents', (string) __('document.title.index'), 'folder_open',
                $query->latest('updated_at')->limit($limit)->get()
                    ->map(fn(Document $d) => [
                        'id' => $d->id,
                        'title' => (string) $d->title,
                        'subtitle' => $d->document_type->label() . ' · ' . $d->effectiveStatus()->label(),
                        'url' => route('documents.index', ['q' => $d->title]),
                    ])
                    ->all());
        }

        // Wissensbasis (Feature 011): Redaktion sieht alle Status, alle anderen
        // Veröffentlichtes plus EIGENE Artikel.
        if ($wants('knowledge') && $customer === null
            && $this->featureFlags->isEnabled('module.knowledge') && Gate::forUser($user)->allows('viewAny', KnowledgeArticle::class)) {
            $knowledgeQuery = KnowledgeArticle::query()
                ->where(fn($q) => $q->whereLikeEscaped('title', $term)
                    ->orWhereLikeEscaped('problem', $term))
                ->when($person !== null, fn($q) => $q->where('created_by_user_id', $person));
            if (! ($user->isAdmin() || $user->can(Permission::KnowledgePublish->value))) {
                $knowledgeQuery->where(fn($q) => $q->where('status', ArticleStatus::Published->value)
                    ->orWhere('created_by_user_id', $user->id));
            }
            $range($knowledgeQuery, 'created_at');
            $groups[] = $this->makeGroup('knowledge', (string) __('knowledge.title.index'), 'school',
                $knowledgeQuery->orderByDesc('created_at')->limit($limit)->get()
                    ->map(fn(KnowledgeArticle $a) => [
                        'id' => $a->id,
                        'title' => (string) $a->title,
                        'subtitle' => trim($a->status->label() . ($a->category ? ' · ' . $a->category : '')),
                        'url' => route('knowledge.show', $a),
                    ])
                    ->all());
        }

        // Formulare (Feature 032): Vorlagen-Sicht sieht alle Submissions, alle
        // anderen ausschließlich die EIGENEN (wie FormSubmissionController).
        if ($wants('forms') && $customer === null
            && $this->featureFlags->isEnabled('module.forms') && Gate::forUser($user)->allows('viewAny', FormSubmission::class)) {
            $submissionQuery = FormSubmission::query()
                ->with(['template', 'submitter'])
                ->whereHas('template', fn($q) => $q->whereLikeEscaped('name', $term))
                ->when($person !== null, fn($q) => $q->where('submitted_by_user_id', $person));
            if (! ($user->isAdmin() || $user->can(Permission::FormTemplateViewAny->value))) {
                $submissionQuery->where('submitted_by_user_id', $user->id);
            }
            $range($submissionQuery, 'submitted_at');
            $groups[] = $this->makeGroup('forms', (string) __('form.title.submissions'), 'edit_note',
                $submissionQuery->orderByDesc('submitted_at')->limit($limit)->get()
                    ->map(fn(FormSubmission $s) => [
                        'id' => $s->id,
                        'title' => $s->template->name ?? __('form.title.submissions') . ' #' . $s->id,
                        'subtitle' => $s->submitted_at->format('d.m.Y')
                            . ($s->submitter ? ' · ' . $s->submitter->name : ''),
                        'url' => route('form-submissions.show', $s),
                    ])
                    ->all());
        }

        // Kommentare (MVP-014-Domäne, Vollaudit M8): Auftrags-Kommentare mit der
        // Auftrags-Sichtbarkeit (Parent-Aggregat); Deep-Link auf #comments.
        if ($wants('comments')) {
            $commentQuery = Comment::query()
                ->where('commentable_type', DiaryEntry::class)
                ->whereLikeEscaped('body', $term)
                ->when($person !== null, fn($q) => $q->where('user_id', $person))
                ->whereHasMorph('commentable', [DiaryEntry::class], function ($q) use ($user, $customer): void {
                    if (! ($user->isAdmin() || $user->can(Permission::DiaryViewAny->value))) {
                        $q->where(fn($p) => $p->where('user_id', $user->id)->orWhere('assigned_user_id', $user->id));
                    }
                    if ($customer !== null) {
                        $q->where('customer_id', $customer);
                    }
                })
                ->with(['user:id,name', 'commentable']);
            $range($commentQuery, 'created_at');
            $groups[] = $this->makeGroup('comments', (string) __('Kommentare'), 'chat_bubble',
                $commentQuery->orderByDesc('created_at')->limit($limit)->get()
                    ->map(fn(Comment $c) => [
                        'id' => $c->id,
                        'title' => mb_strimwidth((string) $c->body, 0, 80, '…'),
                        'subtitle' => ($c->created_at?->format('d.m.Y') ?? '')
                            . ($c->user ? ' · ' . $c->user->name : '')
                            . ($c->commentable instanceof DiaryEntry && $c->commentable->title ? ' · ' . $c->commentable->title : ''),
                        'url' => route('diary.show', $c->commentable_id) . '#comments',
                    ])
                    ->all());
        }

        // Anhang-Metadaten (MVP-014-Domäne, Vollaudit M8): Dateiname; sichtbar
        // sind Anhänge sichtbarer Aufträge sowie eigene Uploads. Download läuft
        // ohnehin über die AttachmentPolicy. Volltext/OCR bleibt Folge-MVP.
        if ($wants('attachments') && $customer === null) {
            $attachmentQuery = Attachment::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
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
            $groups[] = $this->makeGroup('attachments', (string) __('Anhänge'), 'attach_file',
                $attachmentQuery->orderByDesc('created_at')->limit($limit)->get()
                    ->map(fn(Attachment $a) => [
                        'id' => $a->id,
                        'title' => (string) $a->original_name,
                        'subtitle' => ($a->created_at?->format('d.m.Y') ?? '')
                            . ($a->mime ? ' · ' . $a->mime : ''),
                        'url' => route('attachments.download', $a),
                    ])
                    ->all());
        }

        // Leere Gruppen entfernen.
        return array_values(array_filter($groups, static fn(array $g): bool => count($g['items']) > 0));
    }

    /**
     * Auftrags-Query mit der Index-Sichtbarkeit (eigene/zugewiesene vs. viewAny).
     *
     * @return \Illuminate\Database\Eloquent\Builder<DiaryEntry>
     */
    private function visibleDiaryQuery(User $user): \Illuminate\Database\Eloquent\Builder {
        $query = DiaryEntry::query()
            ->when($user->organization_id !== null, fn($q) => $q->where('organization_id', $user->organization_id));
        if (! ($user->isAdmin() || $user->can(Permission::DiaryViewAny->value))) {
            $query->where(fn($q) => $q->where('user_id', $user->id)
                ->orWhere('assigned_user_id', $user->id));
        }

        return $query;
    }

    /**
     * Anzeigename der Bezugsseite (Auftrag/Kunde/Projekt) einer
     * Kommunikationsnotiz — null, wenn der Bezug fehlt.
     */
    private function notableName(CommunicationNote $note): ?string {
        $notable = $note->notable;

        return match (true) {
            $notable instanceof DiaryEntry => $notable->title,
            $notable instanceof Customer, $notable instanceof Project => $notable->name,
            default => null,
        };
    }

    /**
     * Deep-Link auf die Bezugsseite mit Fragment-Anker (Muster wie die
     * Redirects des CommunicationNoteController: #communication-note-{id}).
     * Null, wenn der Bezug fehlt (z. B. soft-deleted) — Treffer wird übersprungen.
     */
    private function communicationNoteUrl(CommunicationNote $note): ?string {
        $notable = $note->notable;
        $base = match (true) {
            $notable instanceof DiaryEntry => route('diary.show', $notable),
            $notable instanceof Customer => route('customers.show', $notable),
            $notable instanceof Project => route('projects.show', $notable),
            default => null,
        };

        return $base === null ? null : $base . '#communication-note-' . $note->id;
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
