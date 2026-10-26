<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BulkWaiveResalePeriodsRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Http\Requests\BaseFormRequest;
use App\Models\Reselling\ResalePeriod;
use App\Support\Sqid;
use Illuminate\Validation\Rule;

/** Mehrere Perioden mit einem Grund verzichten oder strittig stellen (Feature 185, MVP-1415). */
class BulkWaiveResalePeriodsRequest extends BaseFormRequest {
    public const MAX_IDS = 200;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'ids' => ['required', 'array', 'max:' . self::MAX_IDS],
            'ids.*' => ['string', 'max:64'],
            'decision' => ['required', Rule::in(['waived', 'disputed'])],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return list<int> */
    public function periodIds(): array {
        /** @var list<string> $ids */
        $ids = $this->validated('ids');

        return array_values(array_filter(array_map(static fn(string $sqid): ?int => Sqid::decode(ResalePeriod::class, $sqid), $ids)));
    }

    public function status(): PeriodStatus {
        return $this->validated('decision') === 'waived' ? PeriodStatus::Waived : PeriodStatus::Disputed;
    }

    public function reason(): string {
        return (string) $this->validated('reason');
    }
}
