<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VoucherReaderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Schema;

/**
 * Liefert den {@see VoucherReader} einer Organisation — oder `null`, wenn für
 * sie keine freigegebene InvoicePlane-Verbindung existiert (Feature 086:
 * ohne Preflight/Bridge bleibt der Zugriff aus).
 *
 * Default-Implementierung ist {@see NullVoucherReaderFactory}: Ohne
 * Pilotinstanz gibt es keinen Reader — und damit auch keinen erfundenen Beleg.
 */
interface VoucherReaderFactory {
    public function for(int $organizationId): ?VoucherReader;
}
