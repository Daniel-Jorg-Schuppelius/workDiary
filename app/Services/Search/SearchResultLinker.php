<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchResultLinker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\Search\SearchSourceType;
use App\Models\Communication\CommunicationNote;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Document\Document;
use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Protocol\Protocol;
use App\Models\Search\SearchDocument;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Time\{TimeEntry, Timesheet};
use App\Support\{EntityUrl, Sqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Deep-Links der Treffer auf die Originale. Zeiten und Stundenzettel haben
 * keine eigene Detailseite, und ihre Projekt-Reiter folgen dem globalen
 * Header-Zeitraum (AGENTS.md §8) — ein Direktlink auf den Reiter zeigt einen
 * älteren Eintrag nicht. Sie laufen deshalb über `search.open`, das den
 * Zeitraum auf den Tag des Eintrags setzt. Notizen und offene Punkte springen
 * auf die Seite ihres Bezugs mit Anker.
 */
final class SearchResultLinker {
    /**
     * @param  Collection<int, SearchDocument>  $documents
     * @return array<string, string|null> Schlüssel „typ:id"
     */
    public function urls(Collection $documents): array {
        $notes = $this->load($documents, SearchSourceType::CommunicationNote, CommunicationNote::class, ['id', 'notable_type', 'notable_id']);
        $issues = $this->load($documents, SearchSourceType::OpenIssue, OpenIssue::class, ['id', 'subject_type', 'subject_id']);
        // Lernkurse (MVP-789): die eigene Einschreibung führt in den Player,
        // sonst in die Kursakte (nur mit learning.viewAny).
        $courseIds = $documents->filter(static fn(SearchDocument $d): bool => $d->source_type === SearchSourceType::LearningCourse)->pluck('source_id')->all();
        $enrollments = $courseIds !== [] && Auth::id() !== null
            ? \App\Models\Learning\LearningEnrollment::query()->where('user_id', Auth::id())->whereIn('learning_course_id', $courseIds)->pluck('id', 'learning_course_id')
            : collect();

        $urls = [];
        foreach ($documents as $document) {
            $id = $document->source_id;
            $urls[$document->source_type->value . ':' . $id] = match ($document->source_type) {
                SearchSourceType::TimeEntry => $document->project_id !== null ? $this->jump(SearchSourceType::TimeEntry, TimeEntry::class, $id) : null,
                SearchSourceType::Timesheet => $document->project_id !== null ? $this->jump(SearchSourceType::Timesheet, Timesheet::class, $id) : null,
                SearchSourceType::DiaryEntry => route('diary.show', Sqid::encode(DiaryEntry::class, $id)),
                SearchSourceType::ServiceTicket => route('service-tickets.show', Sqid::encode(ServiceTicket::class, $id)),
                SearchSourceType::Protocol => route('protocols.show', Sqid::encode(Protocol::class, $id)),
                SearchSourceType::KnowledgeArticle => route('knowledge.show', Sqid::encode(KnowledgeArticle::class, $id)),
                SearchSourceType::RemoteSession => route('admin.remote-support.pending.index'),
                SearchSourceType::OpenIssue => isset($issues[$id])
                    ? self::withAnchor(EntityUrl::byType($issues[$id]->subject_type, (int) $issues[$id]->subject_id), '#open-issues')
                    : null,
                SearchSourceType::CommunicationNote => isset($notes[$id]) ? $this->noteUrl($notes[$id]) : null,
                SearchSourceType::LearningCourse => $this->learningCourseUrl($id, $enrollments),
                SearchSourceType::Document => route('documents.show', Sqid::encode(Document::class, $id)),
            };
        }

        return $urls;
    }

    /** @param  \Illuminate\Support\Collection<int, int>  $enrollments  Einschreibungs-ID je Kurs-ID */
    private function learningCourseUrl(int $courseId, \Illuminate\Support\Collection $enrollments): ?string {
        if (isset($enrollments[$courseId])) {
            return route('learning.my.show', Sqid::encode(\App\Models\Learning\LearningEnrollment::class, (int) $enrollments[$courseId]));
        }

        return Gate::allows('viewAny', \App\Models\Learning\LearningCourse::class)
            ? route('learning.courses.show', Sqid::encode(\App\Models\Learning\LearningCourse::class, $courseId))
            : null;
    }

    /**
     * Sprung über `search.open` (setzt den Header-Zeitraum auf den Eintragstag).
     *
     * @param  class-string<Model>  $class
     */
    private function jump(SearchSourceType $type, string $class, int $id): string {
        return route('search.open', ['type' => $type->value, 'id' => Sqid::encode($class, $id)]);
    }

    private function noteUrl(CommunicationNote $note): ?string {
        // Interne Organisationsnotizen (Feature 154) haben keine Akte — die zentrale Liste öffnet sie im Lesedialog.
        if ($note->isOrganizationNote()) {
            return route('communication-notes.index', ['note' => Sqid::encode(CommunicationNote::class, (int) $note->id)]);
        }

        return self::withAnchor(EntityUrl::byType($note->notable_type, (int) $note->notable_id), '#communication-note-' . $note->id);
    }

    private static function withAnchor(?string $url, string $anchor): ?string {
        return $url === null ? null : $url . $anchor;
    }

    /**
     * @template T of Model
     *
     * @param  Collection<int, SearchDocument>  $documents
     * @param  class-string<T>  $class
     * @param  list<string>  $columns
     * @return Collection<int, T>
     */
    private function load(Collection $documents, SearchSourceType $type, string $class, array $columns): Collection {
        $ids = $documents->filter(static fn(SearchDocument $d): bool => $d->source_type === $type)->pluck('source_id')->all();

        return $ids === []
            ? collect()
            : $class::query()->withoutGlobalScopes()->whereKey($ids)->get($columns)->keyBy('id');
    }
}
