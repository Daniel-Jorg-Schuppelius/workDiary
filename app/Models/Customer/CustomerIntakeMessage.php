<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeMessage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Enums\Customer\IntakeMessageKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nachricht am Kundeneingang (MVP-1075): Rückfrage, Kundenantwort oder interne
 * Notiz. Nach dem Absenden aus Nachweisgründen nicht editierbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_intake_id
 * @property int|null $author_user_id
 * @property IntakeMessageKind $kind
 * @property string $body
 * @property Carbon|null $created_at
 */
class CustomerIntakeMessage extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'customer_intake_id', 'author_user_id', 'kind', 'body'];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => IntakeMessageKind::class,
    ];

    /** @return BelongsTo<CustomerIntake, $this> */
    public function intake(): BelongsTo {
        return $this->belongsTo(CustomerIntake::class, 'customer_intake_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
