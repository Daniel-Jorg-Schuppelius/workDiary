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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Freigabe-/Lebenszyklus-Status einer Verarbeitungstätigkeit im VVT. */
enum ProcessingActivityStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';         // Entwurf
    case InReview = 'in_review';  // zur Prüfung eingereicht
    case Approved = 'approved';   // freigegeben (gültige Version)
    case Archived = 'archived';   // außer Betrieb

    public function label(): string {
        return match ($this) {
            self::Draft => __('enums.privacy.processing_activity_status.draft'),
            self::InReview => __('enums.privacy.processing_activity_status.in_review'),
            self::Approved => __('enums.privacy.processing_activity_status.approved'),
            self::Archived => __('enums.privacy.processing_activity_status.archived'),
        };
    }
}
