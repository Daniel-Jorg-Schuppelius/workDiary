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

use App\Enums\Asset\{AssetBlockReason, AssetStatus};
use App\Models\Asset\{Asset, AssetBlock, AssetBlockException};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Gemeinsames Asset-Sperr-/Freigabemodell (Entscheidung D12). Verleih,
 * Disposition und Prüfwesen schreiben Sperren mit Grund + Quelle hierher
 * und lesen denselben Status. Ausnahmefreigaben sind befristet, begründet
 * (mindestens 20 Zeichen) und auditiert.
 */
class AssetBlockService {
    public const int MIN_EXCEPTION_REASON_LENGTH = 20;

    public function __construct(private readonly AssetHealthService $health) {}

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
        $this->health->sync($asset);

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

        $asset = $block->asset;
        if ($asset !== null) {
            $asset->audit('asset.unblocked', [
                'reason' => $block->reason->value,
                'block_id' => $block->id,
            ]);
            $this->restoreStatusAfterRelease($asset, $block);
            $this->health->sync($asset);
        }

        return $block;
    }

    /**
     * Der wählbare Status „gesperrt“/„in Reparatur“ ist kein zweites
     * Sperrmodell: er trägt genau eine Sperre mit dem Asset als Quelle
     * (MVP-1574). Verlässt das Asset den Status, endet die Sperre.
     */
    public function syncStatusBlock(Asset $asset, ?User $actor = null): void {
        $reason = self::statusBlockReason($asset->status);
        $existing = AssetBlock::query()->fromStatus($asset)->active()->get();

        foreach ($existing as $block) {
            if ($block->reason !== $reason) {
                $this->release($block, $actor, (string) __('asset.block.status_left'));
            }
        }

        if ($reason === null || $existing->contains(fn (AssetBlock $block): bool => $block->reason === $reason)) {
            return;
        }

        // „Gesperrt“ aus einem blockierenden Defekt trägt der Defekt selbst.
        if ($asset->status === AssetStatus::Blocked && $asset->defects()->blocking()->exists()) {
            return;
        }

        $this->block($asset, $reason, $actor, (string) __('asset.block.from_status', ['status' => $asset->status->label()]), $asset);
    }

    public static function statusBlockReason(AssetStatus $status): ?AssetBlockReason {
        return match ($status) {
            AssetStatus::Blocked => AssetBlockReason::Manual,
            AssetStatus::InRepair => AssetBlockReason::Maintenance,
            default => null,
        };
    }

    /** Wird die Sperre des Status aufgehoben, kehrt das Asset in den Betrieb zurück. */
    private function restoreStatusAfterRelease(Asset $asset, AssetBlock $block): void {
        $fromStatus = $block->source_id === $asset->id && $block->source_type === $asset->getMorphClass();

        if (! $fromStatus
            || self::statusBlockReason($asset->status) !== $block->reason
            || $asset->defects()->blocking()->exists()) {
            return;
        }

        $from = $asset->status;
        $asset->status = AssetStatus::Active;
        $asset->save();
        $asset->audit('asset.statusChanged', ['from' => $from->value, 'to' => AssetStatus::Active->value]);
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
