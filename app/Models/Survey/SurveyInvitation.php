<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyInvitation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Survey;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Customer;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Einladung zu einer Umfrage (Feature 090): signierter Einmal-Link mit
 * Ablauf; der Klartext-Token wird nie gespeichert (Muster
 * IsmsAuditPackageToken).
 *
 * Bei **anonymen** Umfragen bleibt `responded_at` leer — nur der Status
 * wechselt. Ein Join zur Antwort hat damit kein Zeitfeld, über das er
 * laufen könnte.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $survey_id
 * @property int|null $customer_id
 * @property string $email
 * @property string $context_kind
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $sent_at
 * @property string $status
 * @property Carbon|null $responded_at
 */
class SurveyInvitation extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_CREATED = 'created';

    public const STATUS_SENT = 'sent';

    public const STATUS_RESPONDED = 'responded';

    /** Die gehashte Kennung nie serialisieren. */
    protected $hidden = ['token_hash'];

    protected $fillable = [
        'organization_id', 'survey_id', 'customer_id', 'email',
        'context_kind', 'token_hash', 'expires_at', 'sent_at', 'status',
        'responded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public static function hashToken(string $token): string {
        return CryptoHelper::hash($token);
    }

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo {
        return $this->belongsTo(Survey::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    public function isUsable(): bool {
        return $this->status !== self::STATUS_RESPONDED && $this->expires_at->isFuture();
    }
}
