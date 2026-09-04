<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Contracts\HasLabel;

/**
 * Ausgang eines Import-Laufs im Reselling-Register (Feature 152).
 */
enum ImportStatus: string implements HasLabel {
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('resale.import.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Done ? 'success' : 'error';
    }
}
