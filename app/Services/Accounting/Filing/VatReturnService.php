<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatReturnService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Filing;

use App\Models\Organization;
use App\Services\Accounting\Reports\VatPreviewBuilder;
use App\Services\Accounting\VatFilingProfileResolver;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Umsatzsteuer-Vorschau für einen Voranmeldungszeitraum (Feature 125,
 * MVP-685).
 *
 * Sie rechnet auf **ganzen Perioden** statt auf einem frei gewählten Zeitraum
 * und weist die angerechnete Sondervorauszahlung getrennt aus (Kennziffer 39).
 * Eine Vorschau bleibt es trotzdem: Übermittelt wird nichts.
 */
class VatReturnService {
    public function __construct(
        private readonly VatPreviewBuilder $vatPreviews,
        private readonly VatFilingProfileResolver $profile,
        private readonly VatFieldBreakdownService $fields,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Organization $organization, VatReturnPeriod $period): array {
        $data = $this->vatPreviews->build($organization, $period->from, $period->to);

        // Angerechnet wird nur in der letzten Periode des Jahres und nur, was
        // tatsächlich gezahlt wurde (§ 48 Abs. 4 UStDV).
        $prepayment = '0.00';
        if ($period->isLastOfYear()) {
            $extension = $this->profile->extensionFor($organization, $period->year);
            if ($extension?->special_prepayment_entry_id !== null) {
                $prepayment = $extension?->special_prepayment_amount?->getAmount() ?? '0.00';
            }
        }

        $payable = $data['payable'];

        $breakdown = $this->fields->forRange($organization, $period->from, $period->to);

        return $data + [
            'fields' => $breakdown['fields'],
            'field_unclear' => $breakdown['unclear'],
            'period' => $period,
            'interval' => $this->profile->at($organization, $period->to),
            'has_extension' => $this->profile->hasExtension($organization, $period->to),
            'special_prepayment' => $prepayment,
            'remaining' => NumberHelper::subtractPrecise($payable, $prepayment, 2),
        ];
    }
}
