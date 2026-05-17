<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceViolation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

/**
 * Eine einzelne Compliance-Verletzung (z.B. Ruhezeit, Maximalstunden).
 */
final class ComplianceViolation
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    /**
     * @param  array<int, int>  $relatedShiftIds
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $relatedShiftIds = [],
        public readonly array $context = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'related_shift_ids' => $this->relatedShiftIds,
            'context' => $this->context,
        ];
    }
}
