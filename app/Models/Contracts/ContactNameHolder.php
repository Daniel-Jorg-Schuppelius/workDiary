<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNameHolder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Partei mit Ansprechpartner aus Anrede und Namensteilen (MVP-1136);
 * umgesetzt über {@see \App\Models\Concerns\HasStructuredContactName}.
 */
interface ContactNameHolder {
    /** Übernimmt den Ansprechpartner eines anderen Datensatzes samt Anrede. */
    public function copyContactNameFrom(Model $other): void;

    /** Ansprechpartner mit Anrede, z. B. „Frau Dr. Anna Müller“. */
    public function contactNameWithSalutation(): string;
}
