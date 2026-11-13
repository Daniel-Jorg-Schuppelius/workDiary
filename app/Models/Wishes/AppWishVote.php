<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishVote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Wishes;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Platform\User;
use Database\Factories\Wishes\AppWishVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Stimme je Person und Wunsch (Feature 206, MVP-1658). Bewusst ohne
 * Audit: gezählt wird je Wunsch, wer wie oft abstimmt, wird nicht
 * ausgewertet und nirgends angezeigt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $app_wish_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AppWishVote extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<AppWishVoteFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'app_wish_id',
        'user_id',
    ];

    /** @return BelongsTo<AppWish, $this> */
    public function wish(): BelongsTo {
        return $this->belongsTo(AppWish::class, 'app_wish_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
