<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnosticSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Diagnostics;

use Carbon\CarbonImmutable;

final class DiagnosticSection {
    /**
     * @param  array<string, scalar|null>  $metrics
     * @param  list<string>  $messages
     */
    public function __construct(
        public readonly string $code,
        public readonly DiagnosticStatus $status,
        public readonly array $metrics = [],
        public readonly array $messages = [],
        public readonly ?CarbonImmutable $checkedAt = null,
    ) {}

    public static function unknown(string $code, string $reason): self {
        return new self(
            code: $code,
            status: DiagnosticStatus::Unknown,
            metrics: [],
            messages: [$reason],
            checkedAt: CarbonImmutable::now(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'code' => $this->code,
            'status' => $this->status->value,
            'metrics' => $this->metrics,
            'messages' => $this->messages,
            'checked_at' => $this->checkedAt?->toIso8601String(),
        ];
    }
}
