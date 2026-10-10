<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgreementStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Status eines Auftragsverarbeitungsvertrags (AVV/DPA). */
enum AgreementStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Terminated = 'terminated';
    case Expired = 'expired';

    public function label(): string {
        return match ($this) {
            self::Draft => __('enums.privacy.agreement_status.draft'),
            self::Active => __('enums.privacy.agreement_status.active'),
            self::Terminated => __('enums.privacy.agreement_status.terminated'),
            self::Expired => __('enums.privacy.agreement_status.expired'),
        };
    }
}
