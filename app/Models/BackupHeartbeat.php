<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupHeartbeat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{ByteSizeCast, IpAddressCast};
use Illuminate\Database\Eloquent\Model;

/**
 * Heartbeat eines externen Backup-Jobs (MVP-046 §5).
 *
 * @property int $id
 * @property \Carbon\CarbonImmutable $occurred_at
 * @property \CommonToolkit\ValueObjects\ByteSize|null $size_bytes
 * @property string|null $manifest_hash
 * @property string|null $source
 * @property \CommonToolkit\ValueObjects\IpAddress|null $ip
 */
class BackupHeartbeat extends Model {
    protected $table = 'backup_heartbeats';

    protected $fillable = [
        'occurred_at',
        'size_bytes',
        'manifest_hash',
        'source',
        'ip',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'occurred_at' => 'immutable_datetime',
        'size_bytes' => ByteSizeCast::class,
        'ip' => IpAddressCast::class,
    ];
}
