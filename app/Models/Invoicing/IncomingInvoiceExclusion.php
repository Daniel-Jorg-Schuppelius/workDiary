<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceExclusion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Enums\Billing\DocumentDirection;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Ausschlussregel des Rechnungseingangs (Feature 163, MVP-1208): Belege dieser
 * Gegenpartei sind keine Rechnungen — Testbelege, Werbung — und werden beim
 * Eingang abgelehnt abgelegt. Angelegt nur ausdrücklich im Zuordnen-Dialog.
 *
 * @property int $id
 * @property int $organization_id
 * @property DocumentDirection $direction
 * @property string $party_name
 * @property string $party_name_key
 * @property string|null $note
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class IncomingInvoiceExclusion extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** @var list<string> */
    protected $fillable = ['organization_id', 'direction', 'party_name', 'party_name_key', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'direction' => DocumentDirection::class,
    ];

    /** Vergleichsschlüssel eines Gegenparteinamens: Kleinschreibung, Leerraum auf ein Leerzeichen. */
    public static function keyFor(?string $name): ?string {
        $key = StringHelper::normalizeColumnName($name);

        return $key === '' ? null : mb_substr($key, 0, 191);
    }
}
