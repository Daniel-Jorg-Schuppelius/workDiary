<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSourceType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Search;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Models\Auth\RemotePendingSession;
use App\Models\Communication\CommunicationNote;
use App\Models\{DiaryEntry, OpenIssue, Protocol, ServiceTicket, TimeEntry, Timesheet};
use App\Models\Document\Document;
use App\Models\Knowledge\KnowledgeArticle;

/**
 * Quellen des Tätigkeitsindex (Feature 153). Der Wert steht in
 * `search_documents.source_type` — umbenennen heißt Index neu aufbauen.
 */
enum SearchSourceType: string implements HasLabel {
    use HasOptions;

    case TimeEntry = 'time_entry';
    case DiaryEntry = 'diary_entry';
    case Timesheet = 'timesheet';
    case ServiceTicket = 'service_ticket';
    case Protocol = 'protocol';
    case OpenIssue = 'open_issue';
    case CommunicationNote = 'communication_note';
    case KnowledgeArticle = 'knowledge_article';
    case RemoteSession = 'remote_session';
    /** Freigegebene Lernkurse (Feature 149, MVP-789). */
    case LearningCourse = 'learning_course';
    /** Verwaltete Dokumente samt ausgelesenem Dateitext (MVP-819). */
    case Document = 'document';

    public function label(): string {
        return (string) __('search.source.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::TimeEntry => 'schedule',
            self::DiaryEntry => 'assignment',
            self::Timesheet => 'fact_check',
            self::ServiceTicket => 'support_agent',
            self::Protocol => 'description',
            self::OpenIssue => 'report',
            self::CommunicationNote => 'forum',
            self::KnowledgeArticle => 'school',
            self::RemoteSession => 'screen_share',
            self::LearningCourse => 'menu_book',
            self::Document => 'folder_open',
        };
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function modelClass(): string {
        return match ($this) {
            self::TimeEntry => TimeEntry::class,
            self::DiaryEntry => DiaryEntry::class,
            self::Timesheet => Timesheet::class,
            self::ServiceTicket => ServiceTicket::class,
            self::Protocol => Protocol::class,
            self::OpenIssue => OpenIssue::class,
            self::CommunicationNote => CommunicationNote::class,
            self::KnowledgeArticle => KnowledgeArticle::class,
            self::RemoteSession => RemotePendingSession::class,
            self::LearningCourse => \App\Models\Learning\LearningCourse::class,
            self::Document => Document::class,
        };
    }

    public static function forModelClass(string $class): ?self {
        foreach (self::cases() as $case) {
            if ($case->modelClass() === $class) {
                return $case;
            }
        }

        return null;
    }
}
