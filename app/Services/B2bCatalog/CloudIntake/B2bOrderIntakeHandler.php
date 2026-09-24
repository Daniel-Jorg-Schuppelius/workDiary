<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bOrderIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\B2bCatalog\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeRouteTarget};
use App\Models\B2b\B2bOrder;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Platform\User;
use App\Plugins\Support\Intake\IntakeItem;
use App\Services\B2bCatalog\B2bOrderIntakeService;
use App\Services\CloudIntake\Contracts\CloudIntakeHandler;
use App\Services\Licensing\ModuleStatusResolver;
use CommonToolkit\Helper\FileSystem\File;

/**
 * openTRANS-Bestellungen (Feature 099, MVP-458): Datei als
 * openTRANS-2.1-ORDER parsen und Inbox-First spiegeln — kein Blind-Import.
 */
final class B2bOrderIntakeHandler implements CloudIntakeHandler {
    public function __construct(
        private readonly B2bOrderIntakeService $orders,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public function target(): CloudIntakeRouteTarget {
        return CloudIntakeRouteTarget::B2bOrder;
    }

    public function intake(CloudDocumentConnection $connection, IntakeItem $item, string $quarantinePath, User $actor): array {
        $organization = $connection->organization;
        if ($organization === null || ! $this->modules->isActiveFor($organization, 'module.b2b_katalog')) {
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => 'b2b_order_module_inactive'];
        }

        try {
            $result = $this->orders->intake(
                $organization,
                File::read($quarantinePath),
                B2bOrder::SOURCE_CLOUD,
            );
        } catch (\RuntimeException) {
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => 'b2b_order_unreadable'];
        }

        return $result['status'] === 'duplicate'
            ? ['status' => CloudIntakeItemStatus::Duplicate, 'imported' => $result['order'], 'reason' => 'b2b_order_duplicate']
            : ['status' => CloudIntakeItemStatus::Imported, 'imported' => $result['order'], 'reason' => null];
    }
}
