<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

/**
 * Feldtypen des Plugin-Settings-Schemas (Review 2026-08, W5b) — vorher als
 * String-Liste doppelt hartkodiert in DoctorCommand und PluginContractTest.
 */
enum FieldType: string {
    case Text = 'text';

    case Password = 'password';

    case Select = 'select';

    case Boolean = 'boolean';

    case Number = 'number';

    case Url = 'url';

    case Textarea = 'textarea';

    /** @return list<string> */
    public static function values(): array {
        return array_map(static fn(self $t): string => $t->value, self::cases());
    }

    /**
     * Laravel-Validierungsregeln für diesen Typ (`required` ergänzt der Controller).
     *
     * @return list<string>
     */
    public function rules(): array {
        return match ($this) {
            self::Boolean => ['boolean'],
            self::Number => ['numeric'],
            self::Url => ['string', 'url', 'max:1000'],
            self::Textarea => ['string', 'max:10000'],
            default => ['string', 'max:1000'],
        };
    }
}
