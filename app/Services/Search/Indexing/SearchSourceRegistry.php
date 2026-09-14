<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSourceRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing;

use App\Enums\Search\SearchSourceType;
use App\Models\{Comment, DiaryEntry, ServiceTicketMessage};
use App\Services\Search\Indexing\Sources\{CommunicationNoteSource, DiaryEntrySource, KnowledgeArticleSource, OpenIssueSource, ProtocolSource, RemoteSessionSource, SearchSource, ServiceTicketSource, TimeEntrySource, TimesheetSource};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Alle Quellen des Tätigkeitsindex und die Zuordnung geänderter Modelle zu
 * ihrem Dokument — Kind-Modelle (Kommentar, Ticket-Nachricht) aktualisieren
 * das Dokument ihres Elternteils.
 */
final class SearchSourceRegistry {
    /** @var array<string, SearchSource> */
    private array $sources = [];

    public function __construct() {
        foreach ([
            new TimeEntrySource,
            new DiaryEntrySource,
            new TimesheetSource,
            new ServiceTicketSource,
            new ProtocolSource,
            new OpenIssueSource,
            new CommunicationNoteSource,
            new KnowledgeArticleSource,
            new RemoteSessionSource,
        ] as $source) {
            $this->sources[$source->type()->value] = $source;
        }
    }

    public function get(SearchSourceType $type): SearchSource {
        return $this->sources[$type->value];
    }

    /** @return list<SearchSource> */
    public function all(): array {
        return array_values($this->sources);
    }

    /**
     * Dokument, das eine Modelländerung betrifft.
     *
     * @return array{0: SearchSourceType, 1: int}|null
     */
    public function target(Model $model): ?array {
        $type = SearchSourceType::forModelClass($model::class);
        if ($type !== null) {
            return [$type, (int) $model->getKey()];
        }

        if ($model instanceof Comment) {
            $class = Relation::getMorphedModel((string) $model->commentable_type) ?? $model->commentable_type;

            return $class === DiaryEntry::class && $model->commentable_id !== null
                ? [SearchSourceType::DiaryEntry, (int) $model->commentable_id]
                : null;
        }

        if ($model instanceof ServiceTicketMessage) {
            return [SearchSourceType::ServiceTicket, (int) $model->service_ticket_id];
        }

        return null;
    }
}
