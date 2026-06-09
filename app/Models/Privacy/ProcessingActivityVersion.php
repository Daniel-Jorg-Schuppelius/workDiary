<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingActivityVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unveraenderlicher Snapshot einer Verarbeitungstaetigkeit zum Zeitpunkt der
 * Freigabe (Versionierung fuer Art.-30-Nachweis + stichtagsbezogenen Export).
 * Das `payload`-JSON traegt Datenkategorien, Rechtsgrundlagen, Empfaenger,
 * Drittlandtransfers, Aufbewahrungsregeln und TOM.
 *
 * @property array<string, mixed> $payload
 */
class ProcessingActivityVersion extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_processing_activity_versions';

    protected $fillable = [
        'organization_id',
        'activity_id',
        'version_no',
        'payload',
        'note',
        'created_by',
        'approved_by',
        'approved_at',
        'valid_from',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
        'valid_from' => 'date',
    ];

    /** @return BelongsTo<ProcessingActivity, $this> */
    public function activity(): BelongsTo {
        return $this->belongsTo(ProcessingActivity::class, 'activity_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
