<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasPartyDisplayLabel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Anzeigename einer Partei: Firma, sonst Name (Audit 2026-08, W2.5).
 *
 * Der Ausdruck `company ?: name` stand an ~40 Stellen inline — von der
 * Rechnungserzeugung über DATEV-/DATANORM-/XRechnungs-Exporte bis zu
 * Auswahllisten. Der Accessor ist **byte-identisch** zum bisherigen Ausdruck
 * (insbesondere OHNE zusätzliches trim): payload-relevante Exporte hängen an
 * der exakten Zeichenkette, und Aufrufstellen, die bisher selbst trimmten,
 * tun das weiterhin.
 *
 * Bewusste Ausnahme: {@see \App\Services\Cti\CtiCallService} dreht die
 * Reihenfolge um (Name vor Firma) — im Anruf-Popup ist die Person die
 * hilfreichere Auskunft. Diese Stelle nutzt den Accessor deshalb nicht.
 * Ebenfalls außen vor: {@see \App\Models\Lead} (Firma ?: Ansprechpartner,
 * kein `name`-Feld).
 *
 * @property string|null $company
 * @property string|null $name
 */
trait HasPartyDisplayLabel {
    public function displayLabel(): string {
        return (string) ($this->company ?: $this->name);
    }
}
