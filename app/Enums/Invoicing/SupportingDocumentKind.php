<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportingDocumentKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art einer rechnungsbegründenden Unterlage (BG-24, Feature 196). */
enum SupportingDocumentKind: string implements HasLabel {
    use HasOptions;

    /** Aus den abgerechneten Zeiten erzeugter Leistungsnachweis. */
    case ActivityReport = 'activity_report';
    /** Unterschriebene Stundenzettel der abgerechneten Zeiten als eigene Datei. */
    case SignedTimesheets = 'signed_timesheets';
    /** Hochgeladene Datei oder Link. */
    case Custom = 'custom';

    public function label(): string {
        return match ($this) {
            self::ActivityReport => (string) __('enums.invoicing.supporting_document_kind.activity_report'),
            self::SignedTimesheets => (string) __('enums.invoicing.supporting_document_kind.signed_timesheets'),
            self::Custom => (string) __('enums.invoicing.supporting_document_kind.custom'),
        };
    }

    public function isGenerated(): bool {
        return $this !== self::Custom;
    }
}
