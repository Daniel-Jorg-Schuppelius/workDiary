<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Http\Controllers;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Seafile\Api\SeafileClientFactory;
use App\Plugins\Seafile\Services\SeafileIntake;
use App\Plugins\Support\Intake\{IntakeAccount, TokenIntakeController};

/** Seafile-Quelle des Cloud-Dokumenteingangs verbinden (MVP-1346), Ablauf in {@see TokenIntakeController}. */
class SeafileIntakeController extends TokenIntakeController {
    public function __construct(private readonly SeafileClientFactory $clients, private readonly SeafileIntake $intake) {}

    protected function provider(): CloudIntakeProvider {
        return CloudIntakeProvider::Seafile;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    protected function baseUrl(int $organizationId): ?string {
        return $this->clients->baseUrl($organizationId);
    }

    protected function account(CloudDocumentConnection $connection): IntakeAccount {
        return $this->intake->account($connection);
    }

    protected function connectDialogView(): string {
        return 'seafile::cloud-intake._connect_dialog';
    }
}
