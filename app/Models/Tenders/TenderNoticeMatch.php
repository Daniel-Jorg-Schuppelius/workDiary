<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNoticeMatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Tenders;

use App\Models\Applications\ApplicationOpportunity;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Treffer eines Suchprofils auf eine Bekanntmachung — der Inbox-Eintrag.
 *
 * Zustände: `new` (ungesehen), `muted` (gesehen und verworfen), `converted`
 * (in einen Vergabevorgang übernommen). Ein verworfener Treffer bleibt als
 * Beleg erhalten, verschwindet aber aus der Inbox.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $tender_notice_id
 * @property int|null $tender_filter_profile_id
 * @property string $state
 * @property int|null $application_opportunity_id
 */
class TenderNoticeMatch extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const STATE_NEW = 'new';
    public const STATE_MUTED = 'muted';
    public const STATE_CONVERTED = 'converted';

    protected $table = 'tender_notice_matches';

    protected $fillable = [
        'organization_id', 'tender_notice_id', 'tender_filter_profile_id',
        'state', 'application_opportunity_id',
    ];

    /** @return BelongsTo<TenderNotice, $this> */
    public function notice(): BelongsTo {
        return $this->belongsTo(TenderNotice::class, 'tender_notice_id');
    }

    /** @return BelongsTo<TenderFilterProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(TenderFilterProfile::class, 'tender_filter_profile_id');
    }

    /** @return BelongsTo<ApplicationOpportunity, $this> */
    public function opportunity(): BelongsTo {
        return $this->belongsTo(ApplicationOpportunity::class, 'application_opportunity_id');
    }
}
