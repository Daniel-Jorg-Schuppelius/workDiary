<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingActivityStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Freigabe-/Lebenszyklus-Status einer Verarbeitungstätigkeit im VVT. */
enum ProcessingActivityStatus: string {
    case Draft = 'draft';         // Entwurf
    case InReview = 'in_review';  // zur Prüfung eingereicht
    case Approved = 'approved';   // freigegeben (gültige Version)
    case Archived = 'archived';   // außer Betrieb

    public function label(): string {
        return match ($this) {
            self::Draft => __('Entwurf'),
            self::InReview => __('In Prüfung'),
            self::Approved => __('Freigegeben'),
            self::Archived => __('Archiviert'),
        };
    }
}
