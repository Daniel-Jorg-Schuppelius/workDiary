<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetBlockService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset;

use App\Enums\Asset\AssetBlockReason;
use App\Models\{Asset, AssetBlock, AssetBlockException, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Gemeinsames Asset-Sperr-/Freigabemodell (Entscheidung D12). Verleih,
 * Disposition und Prüfwesen schreiben Sperren mit Grund + Quelle hierher
 * und lesen denselben Status. Ausnahmefreigaben sind befristet, begründet
 * (mindestens 20 Zeichen) und auditiert.
 */
class AssetBlockService {
    public const MIN_EXCEPTION_REASON_LENGTH = 20;

    public function block(
        Asset $asset,
        AssetBlockReason $reason,
        ?User $actor = null,
        ?string $note = null,
        ?Model $source = null,
        ?\DateTimeInterface $until = null,
    ): AssetBlock {
        $block = AssetBlock::create([
            'organization_id' => $asset->organization_id,
            'asset_id' => $asset->id,
            'reason' => $reason,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'note' => $note,
            'blocked_from' => now(),
            'blocked_until' => $until,
            'created_by' => $actor?->id,
        ]);

        $asset->audit('asset.blocked', [
            'reason' => $reason->value,
            'block_id' => $block->id,
            'source' => $block->source_type,
        ]);

        return $block;
    }

    public function release(AssetBlock $block, ?User $actor = null, ?string $note = null): AssetBlock {
        if ($block->released_at !== null) {
            return $block;
        }

        $block->forceFill([
            'released_at' => now(),
            'released_by' => $actor?->id,
            'release_note' => $note,
        ])->save();

        $block->asset?->audit('asset.unblocked', [
            'reason' => $block->reason->value,
            'block_id' => $block->id,
        ]);

        return $block;
    }

    /**
     * Befristete, zweckgebundene Ausnahmefreigabe mit Pflichtbegründung.
     */
    public function grantException(
        AssetBlock $block,
        User $actor,
        string $context,
        string $reasonText,
        \DateTimeInterface $validUntil,
    ): AssetBlockException {
        if (mb_strlen(trim($reasonText)) < self::MIN_EXCEPTION_REASON_LENGTH) {
            throw new \InvalidArgumentException((string) __('Ausnahmefreigaben benötigen eine Begründung von mindestens :min Zeichen.', ['min' => self::MIN_EXCEPTION_REASON_LENGTH]));
        }

        $exception = AssetBlockException::create([
            'organization_id' => $block->organization_id,
            'asset_block_id' => $block->id,
            'context' => $context,
            'reason_text' => trim($reasonText),
            'valid_until' => $validUntil,
            'granted_by' => $actor->id,
        ]);

        $block->asset?->audit('asset.blockExceptionGranted', [
            'block_id' => $block->id,
            'context' => $context,
            'valid_until' => $exception->valid_until->toDateString(),
        ]);

        return $exception;
    }

    public function revokeException(AssetBlockException $exception, User $actor): AssetBlockException {
        if ($exception->revoked_at !== null) {
            return $exception;
        }

        $exception->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
        ])->save();

        $exception->block?->asset?->audit('asset.blockExceptionRevoked', [
            'block_id' => $exception->asset_block_id,
            'context' => $exception->context,
        ]);

        return $exception;
    }

    /** @return Collection<int, AssetBlock> */
    public function activeBlocks(Asset $asset): Collection {
        return AssetBlock::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->with('exceptions')
            ->get();
    }

    /**
     * Aktive Sperren ohne gültige Ausnahmefreigabe für den Kontext.
     *
     * @return Collection<int, AssetBlock>
     */
    public function effectiveBlocks(Asset $asset, string $context): Collection {
        return $this->activeBlocks($asset)
            ->reject(fn (AssetBlock $block): bool => $block->activeExceptionFor($context) !== null)
            ->values();
    }

    public function isBlocked(Asset $asset, ?string $context = null): bool {
        return $context === null
            ? $this->activeBlocks($asset)->isNotEmpty()
            : $this->effectiveBlocks($asset, $context)->isNotEmpty();
    }
}
