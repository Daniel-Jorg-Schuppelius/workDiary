<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetUsageGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset;

use App\Exceptions\AssetNotUsableException;
use App\Models\Asset;

/**
 * Einsatzprüfung über Modulgrenzen (D12): Verleih, Disposition und
 * Prüfwesen fragen VOR Reservierung, Übergabe oder Einplanung denselben
 * Guard. Gesperrte oder blockierend defekte Assets führen zu einem
 * strukturierten Fehler statt zu stiller Weiterverwendung.
 */
class AssetUsageGuard {
    public function __construct(private readonly AssetBlockService $blocks) {}

    /**
     * @throws AssetNotUsableException
     */
    public function ensureUsable(Asset $asset, string $context): void {
        $obstacle = $this->firstObstacle($asset, $context);

        if ($obstacle === null) {
            return;
        }

        $asset->audit('asset.useBlockedByGuard', [
            'context' => $context,
            'block_id' => $obstacle->block?->id,
            'reason' => $obstacle->block?->reason->value ?? 'blocking_defect',
        ]);

        throw $obstacle;
    }

    /**
     * Stille Prüfung (z. B. Verfügbarkeitskalender) — ohne Audit-Eintrag.
     */
    public function isUsable(Asset $asset, string $context): bool {
        return $this->firstObstacle($asset, $context) === null;
    }

    private function firstObstacle(Asset $asset, string $context): ?AssetNotUsableException {
        $block = $this->blocks->effectiveBlocks($asset, $context)->first();

        if ($block !== null) {
            return new AssetNotUsableException(
                (string) __(':asset ist gesperrt (:reason) und kann nicht verwendet werden.', [
                    'asset' => $asset->name,
                    'reason' => $block->reason->label(),
                ]),
                $block,
            );
        }

        if ($asset->defects()->blocking()->exists()) {
            return new AssetNotUsableException(
                (string) __(':asset hat einen blockierenden Defekt und kann nicht verwendet werden.', [
                    'asset' => $asset->name,
                ]),
            );
        }

        return null;
    }
}
