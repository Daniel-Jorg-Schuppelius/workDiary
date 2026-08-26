<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullVoucherReaderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Schema;

/**
 * Standardbindung ohne Pilotinstanz (Feature 086, MVP-731): kein Reader, also
 * kein Abruf. Der {@see \App\Plugins\InvoicePlane\Services\InvoicePlaneVoucherPullService}
 * meldet dann „nicht eingerichtet" statt still nichts zu tun.
 */
class NullVoucherReaderFactory implements VoucherReaderFactory {
    public function for(int $organizationId): ?VoucherReader {
        return null;
    }
}
