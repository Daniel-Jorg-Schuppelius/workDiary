<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteNotReplyableException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Communication\CommunicationNote;
use RuntimeException;

/** Die Notiz stammt nicht aus einer eingegangenen E-Mail oder ihr Träger fehlt — es gibt nichts zu beantworten. */
final class CommunicationNoteNotReplyableException extends RuntimeException {
    public function __construct(public readonly CommunicationNote $note) {
        parent::__construct('Kommunikationsnotiz #' . $note->getKey() . ' lässt sich nicht per E-Mail beantworten.');
    }
}
