<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeletionBlockedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Ein Kunde lässt sich nicht löschen (MVP-1591) — Oberfläche und API melden denselben Grund. */
final class CustomerDeletionBlockedException extends RuntimeException {
    public const string REASON_LEGAL_HOLD = 'legal_hold';

    public const string REASON_COLLECTIVE = 'collective_documents';

    public const string REASON_PROJECTS = 'projects';

    public const string REASON_EXTERNAL_REFERENCES = 'external_references';

    public const string REASON_DOCUMENTS = 'documents';

    private function __construct(public readonly string $reason, string $message) {
        parent::__construct($message);
    }

    public static function legalHold(): self {
        return new self(self::REASON_LEGAL_HOLD, (string) __('Kunde steht unter Legal Hold — Löschen ist bis zur Aufhebung ausgeschlossen.'));
    }

    public static function collectiveWithDocuments(): self {
        return new self(self::REASON_COLLECTIVE, (string) __('Der Sammelkontakt hat Belege und lässt sich nicht löschen.'));
    }

    public static function projects(): self {
        return new self(self::REASON_PROJECTS, (string) __('Kunde kann nicht gelöscht werden: Es existieren noch Projekte. Bitte zuerst archivieren oder Projekte entfernen.'));
    }

    public static function externalReferences(): self {
        return new self(self::REASON_EXTERNAL_REFERENCES, (string) __('Kunde kann nicht gelöscht werden: Es existieren externe Referenzen (z. B. Lexoffice). Bitte stattdessen archivieren.'));
    }

    public static function documents(PartyDeletionBlockedException $previous): self {
        return new self(self::REASON_DOCUMENTS, $previous->getMessage());
    }

    /** HTTP-Status der API: Legal Hold sperrt (423), alles andere ist ein Konflikt (409). */
    public function status(): int {
        return $this->reason === self::REASON_LEGAL_HOLD ? 423 : 409;
    }
}
