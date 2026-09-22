<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignatureLinkPurpose.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zweck eines öffentlichen Links (Feature 157): Unterzeichnen einer
 * Anforderung oder Abruf des Ergebnisses. Ein verbrauchtes Signaturtoken
 * wird nie zum Abruflink — dafür gibt es einen eigenen Link.
 */
enum SignatureLinkPurpose: string implements HasLabel {
    use HasOptions;

    case Sign = 'sign';
    case Download = 'download';

    public function label(): string {
        return (string) __('contract-signing.link_purpose.' . $this->value);
    }
}
