<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileAcknowledgement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lesebestätigung eines Personalakten-Dokuments durch die betroffene Person
 * (MVP-987) — je Dokumentversion, eine neue Version verlangt eine neue Bestätigung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_id
 * @property int $document_version_id
 * @property int $user_id
 * @property Carbon $acknowledged_at
 */
class PersonnelFileAcknowledgement extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'document_id', 'document_version_id', 'user_id', 'acknowledged_at'];

    /** @var array<string, string> */
    protected $casts = ['acknowledged_at' => 'datetime'];
}
