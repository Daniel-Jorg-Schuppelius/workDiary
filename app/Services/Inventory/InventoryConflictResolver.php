<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Integration\ExternalConflictStatus;
use App\Models\Article\ArticleVariant;
use App\Models\Audit\AuditLog;
use App\Models\Integration\PendingExternalConflict;
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Modules\ModuleRegistry;
use App\Services\Inventory\Contracts\ArticleConflictHandler;
use App\Support\MorphMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Request};
use RuntimeException;

/**
 * Löst kompensationspflichtige Inventory-Outbox-Konflikte auf (Feature 048,
 * MVP-072). Eine lokal gebuchte Bewegung, deren externe Spiegelung endgültig
 * fehlgeschlagen ist, wird hier fachlich ausgeglichen — entweder durch
 * bewusstes Beibehalten des lokalen Standes oder durch eine **Gegenbuchung**
 * (niemals per DB-Rollback). Der Konflikt wird damit geschlossen.
 *
 * Artikelkonflikte eines Fremdsystems (lokal geänderter Artikel, abweichender
 * Fremdstand) haben drei Wege: lokal behalten (der Artikel bleibt zum Push
 * vorgemerkt), den Stand des Fremdsystems übernehmen (über den
 * {@see ArticleConflictHandler} des meldenden Plugins) oder verwerfen.
 */
class InventoryConflictResolver {
    public const SCALE = 4;

    public function __construct(private readonly InventoryLedger $ledger, private readonly ModuleRegistry $registry) {}

    /**
     * Behält den lokalen Stand bei und schließt den Konflikt: beim Bestand ohne
     * Gegenbuchung (externe Differenz akzeptiert), beim Artikel bleibt die
     * lokale Änderung zum nächsten Push vorgemerkt.
     */
    public function keepLocal(PendingExternalConflict $conflict, ?int $userId = null): void {
        $this->guard($conflict, ExternalConflictStatus::ResolvedLocal, [PendingExternalConflict::TYPE_INVENTORY_OUTBOX, PendingExternalConflict::TYPE_ARTICLE]);
        $this->close($conflict, ExternalConflictStatus::ResolvedLocal, $userId);
    }

    /**
     * Übernimmt den Stand des Fremdsystems in den lokalen Artikel — das Plugin,
     * das den Konflikt gemeldet hat, holt und schreibt ihn. Scheitert das
     * Fremdsystem, bleibt der Konflikt offen.
     *
     * @throws RuntimeException Ohne zuständigen Handler oder wenn das Plugin scheitert.
     */
    public function adoptRemote(PendingExternalConflict $conflict, ?int $userId = null): void {
        $this->guard($conflict, ExternalConflictStatus::ResolvedRemote, [PendingExternalConflict::TYPE_ARTICLE]);

        $handler = null;
        foreach ($this->registry->extensions(ArticleConflictHandler::class) as $class) {
            $candidate = app($class);
            if ($candidate instanceof ArticleConflictHandler && $candidate->supports($conflict)) {
                $handler = $candidate;
                break;
            }
        }
        if ($handler === null) {
            throw new RuntimeException((string) __('inventory.conflict.error.no_handler', ['plugin' => $conflict->plugin_id]));
        }

        $handler->adoptRemote($conflict);
        $this->close($conflict, ExternalConflictStatus::ResolvedRemote, $userId);
    }

    /**
     * Schließt einen Artikelkonflikt ohne Abgleich: beide Stände bleiben, wie
     * sie sind. Weicht der Artikel beim nächsten Abgleich weiter ab, meldet
     * das Fremdsystem-Plugin einen neuen Konflikt.
     */
    public function dismiss(PendingExternalConflict $conflict, ?int $userId = null): void {
        $this->guard($conflict, ExternalConflictStatus::Dismissed, [PendingExternalConflict::TYPE_ARTICLE]);
        $this->close($conflict, ExternalConflictStatus::Dismissed, $userId);
    }

    /**
     * Gleicht die lokal gebuchte Bewegung durch eine betragsgleiche Gegenbuchung
     * im selben Bestandszustand aus und schließt den Konflikt.
     *
     * @throws RuntimeException Wenn die zugrunde liegende Bewegung fehlt.
     */
    public function compensate(PendingExternalConflict $conflict, ?int $userId = null): StockMovement {
        $this->guard($conflict, ExternalConflictStatus::Compensated);

        return DB::transaction(function () use ($conflict, $userId): StockMovement {
            $movement = StockMovement::query()->withoutGlobalScopes()->find($conflict->referenceable_id);
            if (! $movement instanceof StockMovement) {
                throw new RuntimeException('Zur Kompensation fehlt die lokale Bewegung.');
            }

            $variant = ArticleVariant::query()->withoutGlobalScopes()->find($movement->article_variant_id);
            $warehouse = Warehouse::query()->withoutGlobalScopes()->find($movement->warehouse_id);
            if (! $variant instanceof ArticleVariant || ! $warehouse instanceof Warehouse) {
                throw new RuntimeException('Zur Kompensation fehlt Variante oder Lagerort.');
            }

            $qtyBase = (string) $movement->qty_base;
            if (! is_numeric($qtyBase)) {
                throw new RuntimeException('Ungültiger Bewegungswert für die Kompensation.');
            }

            // Gegenbuchung: negierter Delta-Wert im selben Zustand hebt die
            // ursprüngliche Wirkung auf. Idempotenz über einen abgeleiteten Key.
            $reversal = $this->ledger->correction(
                $variant,
                $warehouse,
                $movement->stock_state,
                bcmul($qtyBase, '-1', self::SCALE),
                $movement->ownership_type,
                idempotencyKey: 'compensate:' . $movement->id,
                actorUserId: $userId,
            );

            $this->close($conflict, ExternalConflictStatus::Compensated, $userId);

            return $reversal;
        });
    }

    /** @param list<string> $types Konfliktarten, für die es diesen Weg gibt. */
    private function guard(PendingExternalConflict $conflict, ExternalConflictStatus $target, array $types = [PendingExternalConflict::TYPE_INVENTORY_OUTBOX]): void {
        if (! in_array($conflict->conflict_type, $types, true)) {
            throw new RuntimeException((string) __('inventory.conflict.error.wrong_type'));
        }
        if (! $conflict->status->canTransitionTo($target)) {
            throw new RuntimeException((string) __('inventory.conflict.error.already_resolved'));
        }
    }

    /** Eine Stelle für Stand, Person, Zeitpunkt und Protokoll jeder Entscheidung (ohne Schnappschuss-Inhalte). */
    private function close(PendingExternalConflict $conflict, ExternalConflictStatus $status, ?int $userId): void {
        $conflict->forceFill([
            'status' => $status,
            'resolved_by' => $userId,
            'resolved_at' => Carbon::now(),
        ])->save();

        AuditLog::create([
            'organization_id' => $conflict->organization_id,
            'user_id' => $userId,
            'event' => 'integration.conflict_resolved',
            'auditable_type' => MorphMap::stableKey($conflict::class),
            'auditable_id' => $conflict->getKey(),
            'changes' => [
                'status' => $status->value,
                'conflict_type' => $conflict->conflict_type,
                'plugin_id' => $conflict->plugin_id,
                'external_id' => $conflict->external_id,
                'diff_fields' => $conflict->diff_fields,
            ],
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
