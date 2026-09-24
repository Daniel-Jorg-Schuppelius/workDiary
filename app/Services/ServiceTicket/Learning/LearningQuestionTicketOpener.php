<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionTicketOpener.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Learning;

use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketSource};
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Learning\Contracts\QuestionTicketOpener;
use App\Services\ServiceTicket\ServiceTicketService;

/** Lernfrage → Ticket der Art „Frage", zugewiesen an die Kursverantwortung. */
final class LearningQuestionTicketOpener implements QuestionTicketOpener {
    public function __construct(private readonly ServiceTicketService $tickets) {}

    public function open(Organization $organization, User $learner, array $attributes, User $assignee): ServiceTicket {
        $ticket = $this->tickets->create($organization, $learner, [
            'kind' => ServiceTicketKind::Question->value,
            'title' => (string) ($attributes['title'] ?? ''),
            'description' => (string) ($attributes['description'] ?? ''),
            'source' => ServiceTicketSource::Manual->value,
            'source_reference' => (string) ($attributes['source_reference'] ?? ''),
        ]);
        // Zuständig ist die verantwortliche Person des Kurses, sonst der
        // erste Trainer — so landet die Frage nicht in einer leeren Queue.
        $ticket->forceFill(['assigned_to_user_id' => $assignee->id])->save();

        return $ticket;
    }
}
