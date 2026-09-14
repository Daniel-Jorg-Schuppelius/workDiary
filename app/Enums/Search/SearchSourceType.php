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
use App\Models\{CommunicationNote, DiaryEntry, KnowledgeArticle, OpenIssue, Protocol, RemotePendingSession, ServiceTicket, TimeEntry, Timesheet};

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
