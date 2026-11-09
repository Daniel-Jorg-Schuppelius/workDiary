<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillPullContactsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Console;

use App\Plugins\Easybill\Services\EasybillContactImportService;
use App\Plugins\Support\ContactImport\AccountingContactImportCommand;

/** Kontakt-Rückweg aus easybill bei „Buchhaltung führt“ (MVP-1502). */
class EasybillPullContactsCommand extends AccountingContactImportCommand {
    protected $signature = 'easybill:pull-contacts ' . self::ORGANIZATION_OPTION;

    protected $description = 'Übernimmt Kunden und Lieferanten aus easybill, wenn die Buchhaltung die Stammdaten führt (MVP-1502).';

    protected function importer(): EasybillContactImportService {
        return app(EasybillContactImportService::class);
    }
}
