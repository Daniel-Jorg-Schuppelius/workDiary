<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskPullContactsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Console;

use App\Plugins\SevDesk\Services\SevDeskContactImportService;
use App\Plugins\Support\ContactImport\AccountingContactImportCommand;

/** Kontakt-Rückweg aus sevDesk bei „Buchhaltung führt“ (MVP-1502). */
class SevDeskPullContactsCommand extends AccountingContactImportCommand {
    protected $signature = 'sevdesk:pull-contacts ' . self::ORGANIZATION_OPTION;

    protected $description = 'Übernimmt Kunden und Lieferanten aus sevDesk, wenn die Buchhaltung die Stammdaten führt (MVP-1502).';

    protected function importer(): SevDeskContactImportService {
        return app(SevDeskContactImportService::class);
    }
}
