<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Angebotsposition (Feature 066, MVP-170) — optional = Eventualposition,
 * accepted = Teilannahme-Entscheidung je Position.
 *
 * @property int $id
 * @property int $quote_id
 * @property bool $optional
 * @property bool|null $accepted
 * @property string|null $tax_rate
 */
class QuoteItem extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'quote_id', 'position', 'description',
        'quantity', 'unit', 'unit_price', 'tax_rate', 'tax_category', 'optional', 'accepted',
    ];

    /** @var array<string, string> */
    protected $casts = ['optional' => 'boolean', 'accepted' => 'boolean'];

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo {
        return $this->belongsTo(Quote::class);
    }
}
