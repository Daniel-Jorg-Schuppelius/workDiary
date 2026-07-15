<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractObligationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

/**
 * Obligationsart im Vertragskalender (Welle D, CLM): speist die bestehende
 * Fristen-/Eskalationsmechanik (contract.deadlineDue) — Kündigungsfrist,
 * Verlängerungswarnung, Zahlungs-/Prüf-/Indexierungstermine.
 */
enum ContractObligationKind: string {
    case NoticeDeadline = 'notice_deadline';
    case RenewalWarning = 'renewal_warning';
    case Payment = 'payment';
    case Review = 'review';
    case Indexation = 'indexation';
    case Documentation = 'documentation';
    case Other = 'other';

    public function label(): string {
        return (string) __($this->labelKey());
    }

    /**
     * Quell-Key des Labels (JSON-Katalog) — für render-time-i18n-Params
     * (NotificationText: ['key' => …]), damit Empfänger das Label in ihrer
     * Sprache sehen statt in der des Erzeugers.
     */
    public function labelKey(): string {
        return match ($this) {
            self::NoticeDeadline => 'Kündigungsfrist',
            self::RenewalWarning => 'Verlängerungswarnung',
            self::Payment => 'Zahlungstermin',
            self::Review => 'Prüftermin',
            self::Indexation => 'Indexanpassung',
            self::Documentation => 'Nachweis/Dokument',
            self::Other => 'Sonstige Pflicht',
        };
    }
}
