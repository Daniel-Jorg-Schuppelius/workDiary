<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNotice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Tenders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine veröffentlichte Bekanntmachung des Bundes-Bekanntmachungsservice.
 *
 * Organisationsübergreifend: Dieselbe Ausschreibung interessiert mehrere
 * Mandanten. Die Zuordnung, wen sie betrifft, steckt in
 * {@see TenderNoticeMatch}.
 */
class TenderNotice extends Model {
    protected $table = 'tender_notices';

    protected $fillable = [
        'notice_id', 'version', 'ocid', 'title', 'summary', 'buyer_name',
        'procedure_method', 'cpv_codes', 'nuts_code', 'estimated_value',
        'currency', 'published_on', 'submission_deadline', 'url', 'payload',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'cpv_codes' => 'array',
        'payload' => 'array',
        'published_on' => 'date',
        'submission_deadline' => 'datetime',
        'estimated_value' => 'decimal:2',
    ];

    /** @return HasMany<TenderNoticeMatch, $this> */
    public function matches(): HasMany {
        return $this->hasMany(TenderNoticeMatch::class);
    }
}
