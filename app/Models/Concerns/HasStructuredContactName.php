<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasStructuredContactName.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\Stammdaten\ContactSalutation;
use CommonToolkit\Helper\Data\PersonNameHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Ansprechpartner mit Anrede und Namensteilen (MVP-1136). `contact_name`
 * bleibt die Anzeige: Ändern sich die Teile, wird er daraus gebildet; wird
 * nur `contact_name` gesetzt (Import, API, Anonymisierung, Abgleich), zerlegt
 * der Namenszerleger ihn. So laufen beide Formen auf keinem Schreibweg
 * auseinander.
 *
 * Gehört zum Vertrag {@see \App\Models\Contracts\ContactNameHolder}.
 * Erwartet die Spalten `contact_name`, `contact_salutation` (Cast auf
 * {@see ContactSalutation}), `contact_title`, `contact_first_name`,
 * `contact_middle_names`, `contact_last_name`.
 */
trait HasStructuredContactName {
    /** Teile, aus denen `contact_name` gebildet wird (ohne Anrede). */
    public const CONTACT_NAME_PARTS = ['contact_title', 'contact_first_name', 'contact_middle_names', 'contact_last_name'];

    public static function bootHasStructuredContactName(): void {
        static::saving(static function (self $model): void {
            $model->syncContactName();
        });
    }

    public function syncContactName(): void {
        if ($this->isDirty(self::CONTACT_NAME_PARTS)) {
            $name = PersonNameHelper::join([
                'title' => $this->contact_title,
                'first_name' => $this->contact_first_name,
                'middle_names' => $this->contact_middle_names,
                'last_name' => $this->contact_last_name,
            ]);
            $this->contact_name = $name !== '' ? $name : null;

            return;
        }

        if ($this->isDirty('contact_name') && ! $this->isDirty('contact_salutation')) {
            $parts = PersonNameHelper::split($this->contact_name);
            $this->contact_salutation = ContactSalutation::tryFrom((string) $parts['salutation']);
            $this->contact_title = $parts['title'];
            $this->contact_first_name = $parts['first_name'];
            $this->contact_middle_names = $parts['middle_names'];
            $this->contact_last_name = $parts['last_name'];
        }
    }

    /** Übernimmt den Ansprechpartner eines anderen Datensatzes samt Anrede (Zusammenführen). */
    public function copyContactNameFrom(Model $other): void {
        foreach (['contact_salutation', ...self::CONTACT_NAME_PARTS] as $part) {
            $this->setAttribute($part, $other->getAttribute($part));
        }
    }

    /** Ansprechpartner mit Anrede, z. B. „Frau Dr. Anna Müller“. */
    public function contactNameWithSalutation(): string {
        $salutation = $this->contact_salutation instanceof ContactSalutation && $this->contact_salutation !== ContactSalutation::Diverse
            ? $this->contact_salutation->label()
            : '';

        return trim($salutation . ' ' . (string) $this->contact_name);
    }
}
