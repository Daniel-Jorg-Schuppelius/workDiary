<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaperlessIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Paperless\Http\Controllers;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Paperless\Api\PaperlessClientFactory;
use App\Plugins\Paperless\Services\PaperlessIntake;
use App\Plugins\Support\Intake\{IntakeAccount, TokenIntakeController};

/** Paperless-Quelle des Cloud-Dokumenteingangs verbinden (MVP-1314/1317), Ablauf in {@see TokenIntakeController}. */
class PaperlessIntakeController extends TokenIntakeController {
    public function __construct(private readonly PaperlessClientFactory $clients, private readonly PaperlessIntake $intake) {}

    protected function provider(): CloudIntakeProvider {
        return CloudIntakeProvider::Paperless;
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
        return 'paperless::cloud-intake._connect_dialog';
    }
}
