<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\OpenIssue\OpenIssueEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $open_issue_id
 * @property OpenIssueEventType $event
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 */
class OpenIssueEvent extends Model {
    public $timestamps = false;

    protected $fillable = [
        'open_issue_id',
        'event',
        'actor_user_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'event' => OpenIssueEventType::class,
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<OpenIssue, $this> */
    public function openIssue(): BelongsTo {
        return $this->belongsTo(OpenIssue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
