<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPackageIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeRouteTarget};
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Platform\User;
use App\Plugins\Support\Intake\IntakeItem;
use App\Services\CloudIntake\Contracts\CloudIntakeHandler;
use App\Services\Gaeb\GaebPackageIntakeService;
use CommonToolkit\Helper\FileSystem\File;

/**
 * Vergabeunterlagen (Feature 108, MVP-627): ZIP zerlegen, GAEB-Dateien als
 * Vorschlag ablegen, Rest ins DMS.
 *
 * Der Ordnerweg hat **keinen Vergabevorgang** — er kennt nur den Ordner.
 * Deshalb entstehen hier ausschließlich GAEB-Vorschläge; die Zuordnung zur
 * Akte macht, wer den Vorschlag annimmt. Restdokumente ohne Akte blind ins
 * DMS zu legen, machte sie unauffindbar.
 */
final class GaebPackageIntakeHandler implements CloudIntakeHandler {
    public function __construct(private readonly GaebPackageIntakeService $packages) {}

    public function target(): CloudIntakeRouteTarget {
        return CloudIntakeRouteTarget::GaebPackage;
    }

    public function intake(CloudDocumentConnection $connection, IntakeItem $item, string $quarantinePath, User $actor): array {
        $organizationId = (int) $connection->organization_id;

        try {
            $result = $this->packages->intake(
                File::read($quarantinePath),
                $item->name,
                $organizationId,
                $actor,
            );
        } catch (\RuntimeException $e) {
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => $e->getMessage()];
        }

        if ($result['gaeb'] === []) {
            // Kein GAEB im Paket ist kein Fehler - nur nichts für diesen Weg.
            return ['status' => CloudIntakeItemStatus::Rejected, 'imported' => null, 'reason' => 'gaeb_package_without_gaeb'];
        }

        return ['status' => CloudIntakeItemStatus::Inbox, 'imported' => $result['gaeb'][0], 'reason' => null];
    }
}
