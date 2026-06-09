<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingMessageService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\{MessageAuthorType, MessageVisibility};
use App\Models\User;
use App\Models\Whistleblowing\{Message, WhistleblowingCase};
use Illuminate\Support\Carbon;

/**
 * Schreibt Nachrichten/Notizen in den fall-bezogenen Strom. Body wird mit dem
 * Fall-DEK verschluesselt; nur an den Reporter freigegebene Nachrichten erzeugen
 * ein (inhaltsfreies) Event.
 */
class WhistleblowingMessageService {
    public function __construct(private readonly WhistleblowingEventService $events) {}

    /** Interne Notiz (nie fuer den Reporter sichtbar). */
    public function addInternalNote(WhistleblowingCase $case, string $body, User $author): Message {
        return $this->create($case, $body, $author, MessageVisibility::Internal);
    }

    /** Freigegebene Nachricht an den Reporter (Postfach). */
    public function sendToReporter(WhistleblowingCase $case, string $body, User $author): Message {
        $message = $this->create($case, $body, $author, MessageVisibility::Reporter);

        $this->events->record($case, WhistleblowingEventService::MESSAGE_SENT_TO_REPORTER, $author);

        return $message;
    }

    /** Nachricht der meldenden Person aus dem anonymen Postfach (kein User). */
    public function receiveFromReporter(WhistleblowingCase $case, string $body): Message {
        $message = new Message;
        $message->organization_id = $case->getAttribute('organization_id');
        $message->case_id = $case->getKey();
        $message->setRelation('case', $case);
        $message->author_type = MessageAuthorType::Reporter;
        $message->author_user_id = null;
        $message->visibility = MessageVisibility::Reporter;
        $message->body_ciphertext = $body;
        $message->sent_at = Carbon::now();
        $message->save();

        // Inhaltsfreies Event, damit Bearbeiter die Rueckmeldung sehen.
        $this->events->recordSystem((int) $case->getAttribute('organization_id'), WhistleblowingEventService::MESSAGE_FROM_REPORTER, [
            'case_id' => (int) $case->getKey(),
        ]);

        return $message;
    }

    private function create(WhistleblowingCase $case, string $body, User $author, MessageVisibility $visibility): Message {
        $message = new Message;
        $message->organization_id = $case->getAttribute('organization_id');
        $message->case_id = $case->getKey();
        $message->setRelation('case', $case); // DEK fuer den Cast verfuegbar machen
        $message->author_type = MessageAuthorType::Handler;
        $message->author_user_id = $author->getKey();
        $message->visibility = $visibility;
        $message->body_ciphertext = $body;
        $message->sent_at = Carbon::now();
        $message->save();

        return $message;
    }
}
