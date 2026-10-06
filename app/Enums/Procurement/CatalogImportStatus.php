<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogImportStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Ergebnis eines Katalog-Importlaufs (Feature 050, MVP-091). */
enum CatalogImportStatus: string implements HasLabel {
    use HasOptions;

    case Success = 'success';
    case Error = 'error';

    public function label(): string {
        return (string) __('procurement.catalog.history.status_' . $this->value);
    }
}
