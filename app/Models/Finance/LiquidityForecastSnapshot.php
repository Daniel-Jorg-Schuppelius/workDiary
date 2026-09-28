<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityForecastSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Festgehaltener Wochenstand der Liquiditätsvorschau (MVP-984) — Grundlage
 * des späteren Plan/Ist-Vergleichs mit den Kontobewegungen.
 *
 * @property int $id
 * @property int $organization_id
 * @property Carbon $taken_on
 * @property numeric-string $opening_balance
 * @property list<array{from: string, to: string, label: string, inflow: numeric-string, outflow: numeric-string, net: numeric-string}> $weeks
 */
class LiquidityForecastSnapshot extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'taken_on', 'opening_balance', 'weeks'];

    /** @var array<string, string> */
    protected $casts = [
        'taken_on' => 'date',
        'opening_balance' => 'decimal:2',
        'weeks' => 'array',
    ];
}
