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
        return match ($this) {
            self::NoticeDeadline => (string) __('Kündigungsfrist'),
            self::RenewalWarning => (string) __('Verlängerungswarnung'),
            self::Payment => (string) __('Zahlungstermin'),
            self::Review => (string) __('Prüftermin'),
            self::Indexation => (string) __('Indexanpassung'),
            self::Documentation => (string) __('Nachweis/Dokument'),
            self::Other => (string) __('Sonstige Pflicht'),
        };
    }
}
