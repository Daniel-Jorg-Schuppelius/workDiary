<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetPositionRecorded.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Asset;

use App\Models\Asset\AssetPosition;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Position eines Geräts erfasst (MVP-975). Synchron: Fachmodule tragen Soll-Ort
 * und Abweichung sofort an der Position nach.
 */
final class AssetPositionRecorded {
    use Dispatchable;

    public function __construct(public readonly AssetPosition $position) {}
}
