<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNameFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\Stammdaten\ContactSalutation;
use Illuminate\Validation\Rule;

/**
 * Regeln für den Ansprechpartner einer Partei mit Anrede und Namensteilen
 * (MVP-1136); `contact_name` bildet das Modell daraus.
 */
trait ContactNameFields {
    /** @return array<string, list<mixed>> */
    protected function contactNameRules(): array {
        return [
            'contact_salutation' => ['nullable', Rule::enum(ContactSalutation::class)],
            'contact_title' => ['nullable', 'string', 'max:64'],
            'contact_first_name' => ['nullable', 'string', 'max:128'],
            'contact_middle_names' => ['nullable', 'string', 'max:128'],
            'contact_last_name' => ['nullable', 'string', 'max:128'],
        ];
    }
}
