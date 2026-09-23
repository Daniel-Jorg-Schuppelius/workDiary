<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchTerm.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Search;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Wortverzeichnis des Suchindex je Organisation (Feature 153, MVP-772):
 * Grundlage der Tippfehler-Toleranz. Abgeleitet, jederzeit neu aufbaubar.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $term
 */
class SearchTerm extends Model {
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'term'];
}
