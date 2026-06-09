<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgreementService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Privacy\AgreementStatus;
use App\Models\Privacy\ProcessingAgreement;
use Illuminate\Support\Carbon;

/**
 * Lebenszyklus eines AVV: Aktivierung und das Vertragsende mit Datenrueckgabe-
 * bzw. Loeschnachweis (Art. 28 Abs. 3 lit. g).
 */
class AgreementService {
    public function activate(ProcessingAgreement $agreement): ProcessingAgreement {
        $agreement->forceFill([
            'status' => AgreementStatus::Active,
            'terminated_at' => null,
        ])->save();

        return $agreement;
    }

    /** Vertrag kuendigen; Datenrueckgabe zunaechst offen. */
    public function terminate(ProcessingAgreement $agreement): ProcessingAgreement {
        $agreement->forceFill([
            'status' => AgreementStatus::Terminated,
            'terminated_at' => Carbon::now(),
            'data_return' => $agreement->getAttribute('data_return') ?? 'pending',
        ])->save();

        return $agreement;
    }

    /**
     * Datenrueckgabe/Loeschung bestaetigen.
     *
     * @param  'returned'|'deleted'  $mode
     */
    public function confirmDataReturn(ProcessingAgreement $agreement, string $mode): ProcessingAgreement {
        $agreement->forceFill([
            'data_return' => $mode,
            'data_return_confirmed_at' => Carbon::now(),
        ])->save();

        return $agreement;
    }
}
