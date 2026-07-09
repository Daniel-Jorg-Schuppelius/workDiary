<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsSignal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Operations;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};

/**
 * Meldung einer Betriebs-Quelle an den OperationsAlertService
 * (Feature 041, 041-P0). dedupeKey identifiziert den Vorfall stabil
 * (z. B. "backup_overdue" oder "credential_expiring:pat:123"), damit
 * wiederholte Scanner-Läufe keine Duplikate erzeugen.
 */
final readonly class OperationsSignal {
    /**
     * @param array<string, mixed> $params i18n-Parameter für title_key
     * @param array<string, mixed> $linkParams
     */
    public function __construct(
        public OperationsTaskType $type,
        public string $dedupeKey,
        public OperationsTaskSeverity $severity,
        public string $titleKey,
        public array $params = [],
        public ?int $organizationId = null, // null = installationsweit (Betreiber-Org)
        public ?string $linkRoute = null,
        public array $linkParams = [],
        public ?string $message = null,     // optionaler Benachrichtigungstext
        public ?bool $notify = null,        // null = Default nach Severity
    ) {}
}
