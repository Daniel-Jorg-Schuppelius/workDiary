<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxDismissedCall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fritzbox\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sperrmarke eines verworfenen Anrufs (Entscheidung 2026-10-06): der Fall im
 * Integrations-Eingang fällt nach der Aufbewahrungsfrist dem Aufräumlauf zum
 * Opfer, die Anrufliste der Box und alte CSV-Exporte liefern den Anruf aber
 * beliebig lange erneut. Die Marke bleibt dauerhaft und verhindert das
 * erneute Vormerken — ohne Personenbezug: nur ein HMAC des Anrufschlüssels
 * (App-Schlüssel, je Organisation), kein Klartext, keine Rufnummer, kein
 * Benutzer. Deshalb gehört sie weder in die Auskunft noch ins Offboarding;
 * die harte Löschung der Organisation räumt sie über `organization_id` ab.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $call_hash
 * @property Carbon $dismissed_at
 */
class FritzboxDismissedCall extends Model {
    use BelongsToOrganization;

    protected $table = 'fritzbox_dismissed_calls';

    public $timestamps = false;

    protected $fillable = ['organization_id', 'call_hash', 'dismissed_at'];

    /** @var array<string, string> */
    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    /**
     * Nicht umkehrbar und je Organisation verschieden: derselbe Anruf in zwei
     * Mandanten ergibt zwei Marken, die sich nicht verknüpfen lassen.
     */
    public static function hashFor(int $organizationId, string $callKey): string {
        return CryptoHelper::createHmac($organizationId . '|' . $callKey, (string) config('app.key'));
    }

    /** Idempotent: eine zweite Marke desselben Anrufs ändert nichts. */
    public static function mark(int $organizationId, string $callKey, ?CarbonInterface $dismissedAt = null): void {
        self::query()->withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organizationId, 'call_hash' => self::hashFor($organizationId, $callKey)],
            ['dismissed_at' => $dismissedAt ?? now()],
        );
    }

    public static function covers(int $organizationId, string $callKey): bool {
        return self::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('call_hash', self::hashFor($organizationId, $callKey))
            ->exists();
    }
}
