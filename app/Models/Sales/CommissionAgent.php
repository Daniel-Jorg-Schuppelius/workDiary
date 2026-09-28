<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionAgent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Externer Vermittler ohne Benutzerkonto als Provisionsempfänger (MVP-989).
 * Die Auszahlung läuft außerhalb, der Export nennt ihn neben den Beschäftigten.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $company
 * @property string|null $email
 * @property string|null $note
 * @property bool $is_active
 * @property int|null $created_by
 */
class CommissionAgent extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'company', 'email', 'note', 'is_active', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean'];

    public function displayName(): string {
        return $this->company !== null && $this->company !== '' ? $this->name . ' (' . $this->company . ')' : $this->name;
    }
}
