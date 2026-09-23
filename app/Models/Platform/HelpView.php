<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpView.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Anonyme Hilfe-Telemetrie ohne User-ID.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $topic
 * @property string $locale
 * @property bool|null $was_helpful
 */
class HelpView extends Model {
    protected $table = 'help_views';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'topic',
        'locale',
        'was_helpful',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'was_helpful' => 'boolean',
        'created_at' => 'datetime',
    ];
}
