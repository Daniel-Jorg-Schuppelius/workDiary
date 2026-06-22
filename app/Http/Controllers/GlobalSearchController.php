<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission;
use App\Models\{CommunicationNote, Customer, DiaryEntry, Document, Expense, FormSubmission, KnowledgeArticle, PerDiemTrip, Project, User};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Liefert die Treffer für die globale Suche (Command-Palette / Spotlight).
 *
 * Pro Entität werden bis zu 5 Treffer zurückgegeben (Limit je Endpoint-Aufruf
 * insgesamt ≤ 30 Datensätze), gefiltert nach Organisation des angemeldeten
 * Benutzers. Datenschutz: Mitarbeiterliste ist Admins/Approver:innen vorbehalten.
 */
class GlobalSearchController extends Controller {
    private const PER_TYPE_LIMIT = 5;

    public function __invoke(Request $request, FeatureFlagResolver $featureFlags): JsonResponse {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        /** @var User $user */
        $user = Auth::user();
        $orgId = $user->organization_id;
        $like = '%' . $term . '%';

        $groups = [];

        $groups[] = $this->makeGroup(
            'customers',
            __('Kunden'),
            'badge',
            Customer::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->where('name', 'like', $like)
                    ->orWhere('number', 'like', $like)
                    ->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(Customer $c) => [
                    'id' => $c->id,
                    'title' => $c->name,
                    'subtitle' => trim(($c->number ? '#' . $c->number : '') . ($c->email ? ' · ' . $c->email : '')) ?: null,
                    'url' => route('customers.show', $c),
                ])
                ->all(),
        );

        $groups[] = $this->makeGroup(
            'projects',
            __('Projekte'),
            'folder_special',
            Project::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->where('name', 'like', $like))
                ->with('customer:id,name')
                ->orderBy('name')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(Project $p) => [
                    'id' => $p->id,
                    'title' => $p->name,
                    'subtitle' => $p->customer?->name,
                    'url' => route('projects.show', $p),
                ])
                ->all(),
        );

        // Aufträge / Tagebucheinträge (MVP-014): durchsucht Titel, Beschreibung
        // und Rückmeldung. Sichtbarkeit wie der Index — wer kein diary.viewAny
        // besitzt (und kein Admin ist), sieht ausschließlich EIGENE oder ihm
        // zugewiesene Aufträge. Mandantengrenze über den Organization-Scope.
        $diaryQuery = DiaryEntry::query()
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where(fn($q) => $q->where('title', 'like', $like)
                ->orWhere('content', 'like', $like)
                ->orWhere('response', 'like', $like));
        if (! ($user->isAdmin() || $user->can(Permission::DiaryViewAny->value))) {
            $diaryQuery->where(fn($q) => $q->where('user_id', $user->id)
                ->orWhere('assigned_user_id', $user->id));
        }
        $groups[] = $this->makeGroup(
            'diary',
            __('Aufträge'),
            'assignment',
            $diaryQuery->with('customer:id,name')
                ->orderByDesc('start_at')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(DiaryEntry $d) => [
                    'id' => $d->id,
                    'title' => $d->title ?: ($d->content ? mb_strimwidth($d->content, 0, 60, '…') : __('Auftrag #:id', ['id' => $d->id])),
                    'subtitle' => trim($d->status->label()
                        . ($d->customer ? ' · ' . $d->customer->name : '')
                        . ($d->start_at ? ' · ' . $d->start_at->format('d.m.Y') : '')),
                    'url' => route('diary.show', $d),
                ])
                ->all(),
        );

        $expenseQuery = Expense::query()
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where(fn($q) => $q->where('vendor', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('reimbursement_reference', 'like', $like));
        if (! Gate::allows('viewAny', Expense::class)) {
            $expenseQuery->where('user_id', $user->id);
        }
        $groups[] = $this->makeGroup(
            'expenses',
            __('Spesen'),
            'receipt_long',
            $expenseQuery->orderByDesc('date')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(Expense $e) => [
                    'id' => $e->id,
                    'title' => $e->vendor ?: ($e->description ?: __('Spese #:id', ['id' => $e->id])),
                    'subtitle' => $e->date->format('d.m.Y')
                        . ' · ' . number_format((float) $e->amount_gross, 2, ',', '.') . ' €',
                    'url' => route('expenses.show', $e),
                ])
                ->all(),
        );

        $tripQuery = PerDiemTrip::query()
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where(fn($q) => $q->where('location', 'like', $like)
                ->orWhere('purpose', 'like', $like)
                ->orWhere('country', 'like', $like));
        if (! Gate::allows('viewAny', PerDiemTrip::class)) {
            $tripQuery->where('user_id', $user->id);
        }
        $groups[] = $this->makeGroup(
            'per_diem_trips',
            __('Reisekosten'),
            'flight',
            $tripQuery->orderByDesc('started_at')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(PerDiemTrip $t) => [
                    'id' => $t->id,
                    'title' => trim(($t->location ?: '—') . ($t->country ? ' (' . $t->country . ')' : '')),
                    'subtitle' => $t->started_at->format('d.m.Y')
                        . ($t->purpose ? ' · ' . mb_strimwidth($t->purpose, 0, 60, '…') : ''),
                    'url' => route('per-diem-trips.show', $t),
                ])
                ->all(),
        );

        // Nur Admin/Org-Manager dürfen Mitarbeiter durchsuchen.
        if ($user->isAdmin() || Gate::allows('manage-members')) {
            $groups[] = $this->makeGroup(
                'users',
                __('Mitarbeiter'),
                'group',
                User::query()
                    ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                    ->where(fn($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->orderBy('name')
                    ->limit(self::PER_TYPE_LIMIT)
                    ->get()
                    ->map(fn(User $u) => [
                        'id' => $u->id,
                        'title' => $u->name,
                        'subtitle' => $u->email,
                        'url' => Gate::allows('manage-members') ? route('org.members.index') : '#',
                    ])
                    ->all(),
            );
        }

        // Kommunikationsnotizen (MVP-012): nur mit communication.viewAny;
        // der visibleTo-Scope blendet vertrauliche Notizen Dritter aus
        // (sichtbar nur für Erfasser + communication.confidential.manage).
        // Mandantengrenze: BelongsToOrganization-Global-Scope.
        if (Gate::allows('viewAny', CommunicationNote::class)) {
            $notes = CommunicationNote::query()
                ->visibleTo($user)
                ->where('subject', 'like', $like)
                ->with('notable')
                ->orderByDesc('occurred_at')
                ->limit(self::PER_TYPE_LIMIT)
                ->get();
            $noteItems = [];
            foreach ($notes as $n) {
                $url = $this->communicationNoteUrl($n);
                if ($url === null) {
                    continue; // Bezug fehlt (z. B. soft-deleted) → kein Deep-Link möglich.
                }
                $notableName = $this->notableName($n);
                $noteItems[] = [
                    'id' => $n->id,
                    'title' => $n->subject,
                    'subtitle' => $n->occurred_at->format('d.m.Y')
                        . ' · ' . $n->type->label()
                        . ($notableName !== null ? ' · ' . $notableName : ''),
                    'url' => $url,
                ];
            }
            $groups[] = $this->makeGroup(
                'communication',
                __('communication.title.index'),
                'forum',
                $noteItems,
            );
        }

        // Dokumente (MVP-031): document.viewAny UND aktives Modul (Plan/Lizenz,
        // Muster wie DiaryEntryTimelineService::canSeeDocuments()). Keine
        // Detailseite — Link auf die vorgefilterte Liste (?q=Titel).
        if ($featureFlags->isEnabled('module.documents') && Gate::allows('viewAny', Document::class)) {
            $groups[] = $this->makeGroup(
                'documents',
                __('document.title.index'),
                'folder_open',
                Document::query()
                    ->where('title', 'like', $like)
                    ->latest('updated_at')
                    ->limit(self::PER_TYPE_LIMIT)
                    ->get()
                    ->map(fn(Document $d) => [
                        'id' => $d->id,
                        'title' => $d->title,
                        'subtitle' => $d->document_type->label() . ' · ' . $d->effectiveStatus()->label(),
                        'url' => route('documents.index', ['q' => $d->title]),
                    ])
                    ->all(),
            );
        }

        // Wissensbasis (Feature 011): Sichtbarkeit wie der Index —
        // Redaktion (knowledge.publish/Admin) sieht alle Status, alle
        // anderen Veröffentlichtes plus EIGENE Artikel.
        if ($featureFlags->isEnabled('module.knowledge') && Gate::allows('viewAny', KnowledgeArticle::class)) {
            $knowledgeQuery = KnowledgeArticle::query()
                ->where(fn($q) => $q->where('title', 'like', $like)
                    ->orWhere('problem', 'like', $like));
            if (! ($user->isAdmin() || $user->can(Permission::KnowledgePublish->value))) {
                $knowledgeQuery->where(fn($q) => $q->where('status', ArticleStatus::Published->value)
                    ->orWhere('created_by_user_id', $user->id));
            }
            $groups[] = $this->makeGroup(
                'knowledge',
                __('knowledge.title.index'),
                'school',
                $knowledgeQuery->orderByDesc('created_at')
                    ->limit(self::PER_TYPE_LIMIT)
                    ->get()
                    ->map(fn(KnowledgeArticle $a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'subtitle' => trim($a->status->label() . ($a->category ? ' · ' . $a->category : '')),
                        'url' => route('knowledge.show', $a),
                    ])
                    ->all(),
            );
        }

        // Formulare (Feature 032): Suche über den Vorlagen-Namen; Vorlagen-
        // Sicht (formTemplate.viewAny/Admin) sieht alle Submissions, alle
        // anderen ausschließlich die EIGENEN (wie FormSubmissionController).
        if ($featureFlags->isEnabled('module.forms') && Gate::allows('viewAny', FormSubmission::class)) {
            $submissionQuery = FormSubmission::query()
                ->with(['template', 'submitter'])
                ->whereHas('template', fn($q) => $q->where('name', 'like', $like));
            if (! ($user->isAdmin() || $user->can(Permission::FormTemplateViewAny->value))) {
                $submissionQuery->where('submitted_by_user_id', $user->id);
            }
            $groups[] = $this->makeGroup(
                'forms',
                __('form.title.submissions'),
                'edit_note',
                $submissionQuery->orderByDesc('submitted_at')
                    ->limit(self::PER_TYPE_LIMIT)
                    ->get()
                    ->map(fn(FormSubmission $s) => [
                        'id' => $s->id,
                        'title' => $s->template->name ?? __('form.title.submissions') . ' #' . $s->id,
                        'subtitle' => $s->submitted_at->format('d.m.Y')
                            . ($s->submitter ? ' · ' . $s->submitter->name : ''),
                        'url' => route('form-submissions.show', $s),
                    ])
                    ->all(),
            );
        }

        // Leere Gruppen entfernen.
        $groups = array_values(array_filter($groups, fn(array $g) => count($g['items']) > 0));

        return response()->json(['groups' => $groups, 'q' => $term]);
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
     * @param array<int, array{id:int|string,title:string,subtitle:?string,url:string}> $items
     * @return array{key:string,label:string,icon:string,items:array<int, array{id:int|string,title:string,subtitle:?string,url:string}>}
     */
    private function makeGroup(string $key, string $label, string $icon, array $items): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'items' => $items,
        ];
    }
}
