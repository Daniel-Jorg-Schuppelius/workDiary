<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiUsagePeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Ai;

use App\Enums\Ai\AiFamily;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * Monatsverbrauch je Organisation und Provider-Familie (Feature 025,
 * MVP-399): LLM in Token, Übersetzung in Zeichen. Grundlage des
 * Budget-Gates ({@see \App\Services\Ai\AiBudgetService}) und des
 * Verbrauchsberichts (MVP-411).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $period YYYY-MM
 * @property AiFamily $family
 * @property int $used_units
 */
class AiUsagePeriod extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'period',
        'family',
        'used_units',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'family' => AiFamily::class,
        'used_units' => 'integer',
    ];
}
