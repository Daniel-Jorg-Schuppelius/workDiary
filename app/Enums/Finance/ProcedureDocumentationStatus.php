<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Statusmaschine einer Verfahrensdokumentations-Version (Feature 134):
 * Entwurf → veröffentlicht; mit Vier-Augen-Prinzip Entwurf → in Prüfung →
 * veröffentlicht bzw. zurück in den Entwurf (MVP-995). Die Veröffentlichung friert Freitext und den
 * generierten Systemteil (Snapshot + PDF-Hash) ein; Änderungen erzeugen
 * eine neue Version.
 */
enum ProcedureDocumentationStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';

    public function label(): string {
        return (string) __('enums.finance.procedure-documentation-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'warning',
            self::InReview => 'info',
            self::Published => 'success',
        };
    }

    /**
     * @return list<ProcedureDocumentationStatus>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::InReview, self::Published],
            self::InReview => [self::Published, self::Draft],
            self::Published => [],
        };
    }
}
