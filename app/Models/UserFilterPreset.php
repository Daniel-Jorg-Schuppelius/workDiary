<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFilterPreset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $scope
 * @property string $name
 * @property array<string, mixed> $query
 * @property bool $is_default
 * @property int $sort_order
 */
class UserFilterPreset extends Model {
    protected $fillable = [
        'user_id',
        'scope',
        'name',
        'query',
        'is_default',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'query' => 'array',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
