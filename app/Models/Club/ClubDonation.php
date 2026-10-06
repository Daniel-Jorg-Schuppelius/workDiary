<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Club\ClubDonationKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zuwendung an den Verein (Feature 159, MVP-1003): von einem Mitglied oder einer
 * anderen Person. Nach der Bestätigung unveränderlich — eine bestätigte
 * Zuwendung darf nicht still eine andere Summe tragen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $club_member_id
 * @property string|null $donor_name
 * @property string|null $donor_address
 * @property ClubDonationKind $kind
 * @property Money $amount
 * @property CurrencyCode $currency
 * @property Carbon $received_on
 * @property bool $is_expense_waiver
 * @property string|null $note
 * @property int|null $club_donation_receipt_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClubDonation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'club_member_id',
        'donor_name',
        'donor_address',
        'kind',
        'amount',
        'currency',
        'received_on',
        'is_expense_waiver',
        'note',
        'club_donation_receipt_id',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => ClubDonationKind::class,
        'amount' => MoneyCast::class . ':currency,2',
        'currency' => CurrencyCode::class,
        'received_on' => 'date',
        'is_expense_waiver' => 'boolean',
    ];

    protected static function booted(): void {
        // Die Zuwendungsbestätigung ist ein Steuerbeleg: was sie bestätigt, ändert sich danach nicht mehr.
        static::updating(function (self $donation): void {
            if ($donation->getOriginal('club_donation_receipt_id') !== null) {
                throw new \RuntimeException('Bestätigte Zuwendungen sind unveränderlich.');
            }
        });
        static::deleting(function (self $donation): void {
            if ($donation->club_donation_receipt_id !== null) {
                throw new \RuntimeException('Bestätigte Zuwendungen dürfen nicht gelöscht werden.');
            }
        });
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubDonationReceipt, $this> */
    public function receipt(): BelongsTo {
        return $this->belongsTo(ClubDonationReceipt::class, 'club_donation_receipt_id');
    }

    public function isReceipted(): bool {
        return $this->club_donation_receipt_id !== null;
    }

    public function donorLabel(): string {
        return $this->member?->fullName() ?? (string) $this->donor_name;
    }

    /** Gruppierungsschlüssel für die Sammelbestätigung: Mitglied oder Name + Anschrift. */
    public function donorKey(): string {
        return $this->club_member_id !== null
            ? 'member:' . $this->club_member_id
            : 'donor:' . CryptoHelper::hash(mb_strtolower(trim((string) $this->donor_name)) . '|' . mb_strtolower(trim((string) $this->donor_address)));
    }
}
