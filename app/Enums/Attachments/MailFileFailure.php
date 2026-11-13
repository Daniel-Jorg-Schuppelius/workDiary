<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileFailure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Attachments;

/**
 * Warum eine Mail-Datei nicht gelesen wurde (Feature 204, MVP-1652). Der
 * Grund steht im Enum, nicht in der Meldung des Parsers: die Person bekommt
 * einen verständlichen Satz statt einer Fehlerseite, der Suchindex legt den
 * Grund ab.
 */
enum MailFileFailure: string {
    /** Datei fehlt auf dem Speicher. */
    case Missing = 'missing';

    /** Datei ist größer als die Grenze für Anhänge. */
    case TooLarge = 'too_large';

    /** Mehr Anhänge (über alle Ebenen) als erlaubt. */
    case TooManyAttachments = 'too_many_attachments';

    /** Anhänge zusammen größer als erlaubt. */
    case AttachmentsTooLarge = 'attachments_too_large';

    /** Eingebettete Mails tiefer verschachtelt als erlaubt. */
    case TooDeep = 'too_deep';

    /** Aufbau zu verschachtelt oder zu viele Teile für ein sicheres Lesen. */
    case TooComplex = 'too_complex';

    /** Beschädigt oder keine Mail. */
    case Malformed = 'malformed';

    /** Lesen abgebrochen (Zeitlimit oder Absturz im Hintergrund) — wird nicht wiederholt. */
    case Aborted = 'aborted';

    /** Verständliche Meldung für die Person. */
    public function message(): string {
        return (string) __('mail_file.failure.' . $this->value);
    }
}
