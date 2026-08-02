<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJobEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Disposal;

use App\Enums\Disposal\DisposalJobEventType;
use App\Models\Concerns\AppendOnly;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only-Ereignis der Nachweiskette einer Entsorgungsakte
 * (Feature 100) — Muster protocol_events: nie ändern, nie löschen.
 * Mandantengrenze transitiv über disposal_jobs.
 *
 * @property int $id
 * @property int $disposal_job_id
 * @property DisposalJobEventType $event
 * @property int $actor_user_id
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 */
class DisposalJobEvent extends Model {
    use AppendOnly;

    public $timestamps = false;

    protected $fillable = ['disposal_job_id', 'event', 'actor_user_id', 'payload', 'created_at'];

    /** @var array<string, string> */
    protected $casts = [
        'event' => DisposalJobEventType::class,
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<DisposalJob, $this> */
    public function job(): BelongsTo {
        return $this->belongsTo(DisposalJob::class, 'disposal_job_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
