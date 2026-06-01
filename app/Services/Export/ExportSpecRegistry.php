<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportSpecRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export;

use App\Enums\Export\ExportEntity;
use App\Services\Export\Specs\{
    CustomerExportSpec,
    MaterialExportSpec,
    ProjectExportSpec,
    ScheduledShiftExportSpec,
    TourExportSpec,
    UserExportSpec
};
use InvalidArgumentException;

/**
 * Lookup-Registry für entitätsspezifische Export-Spezifikationen.
 */
class ExportSpecRegistry {
    public function __construct(
        private readonly CustomerExportSpec $customers,
        private readonly ProjectExportSpec $projects,
        private readonly UserExportSpec $users,
        private readonly MaterialExportSpec $materials,
        private readonly ScheduledShiftExportSpec $scheduledShifts,
        private readonly TourExportSpec $tours,
    ) {
    }

    public function for(ExportEntity $entity): ExportSpec {
        return match ($entity) {
            ExportEntity::Customers => $this->customers,
            ExportEntity::Projects => $this->projects,
            ExportEntity::Users => $this->users,
            ExportEntity::Materials => $this->materials,
            ExportEntity::ScheduledShifts => $this->scheduledShifts,
            ExportEntity::Tours => $this->tours,
        };
    }

    public function byValue(string $entityValue): ExportSpec {
        $entity = ExportEntity::tryFrom($entityValue)
            ?? throw new InvalidArgumentException("Unbekannte Export-Entität: {$entityValue}");

        return $this->for($entity);
    }
}
