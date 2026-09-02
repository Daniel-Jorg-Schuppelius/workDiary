<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBooking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\LearningBookingStatus;
use App\Models\{Article, Customer, ExternalParticipant, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kursbuchung (Feature 149, MVP-744).
 *
 * Preis und Artikel sind ab der Zusage **eingefroren** — eine spätere
 * Preisänderung am Artikel verteuert eine erteilte Zusage nicht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_course_id
 * @property int|null $user_id
 * @property int|null $external_participant_id
 * @property int|null $customer_id
 * @property LearningBookingStatus $status
 * @property int $seats
 * @property int|null $article_id
 * @property string|null $unit_price
 * @property CurrencyCode|null $currency
 * @property Carbon $requested_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by_user_id
 * @property string|null $decision_note
 * @property int|null $learning_enrollment_id
 * @property bool $is_billable
 * @property Carbon|null $billed_at
 * @property-read LearningCourse|null $course
 */
class LearningBooking extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_course_id',
        'user_id',
        'external_participant_id',
        'customer_id',
        'status',
        'seats',
        'article_id',
        'unit_price',
        'currency',
        'requested_at',
        'decided_at',
        'decided_by_user_id',
        'decision_note',
        'learning_enrollment_id',
        'is_billable',
        'billed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LearningBookingStatus::class,
        'seats' => 'integer',
        'unit_price' => 'decimal:2',
        'currency' => CurrencyCode::class,
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'billed_at' => 'datetime',
        'is_billable' => 'boolean',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ExternalParticipant, $this> */
    public function externalParticipant(): BelongsTo {
        return $this->belongsTo(ExternalParticipant::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** Anzeigename des Buchenden, unabhängig von der Subjektart. */
    public function bookerName(): string {
        return $this->user->name ?? $this->externalParticipant->name ?? $this->customer->name ?? '';
    }

    /** Offener Rechnungsposten: zugesagt, bepreist, noch nicht fakturiert. */
    public function isOpenForBilling(): bool {
        return $this->is_billable && $this->billed_at === null;
    }
}
