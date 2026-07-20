<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetAssignmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\{DefectSeverity, DefectStatus};
use App\Exceptions\AssetValidationException;
use App\Models\{Asset, AssetAssignment, AssetDefect, DiaryEntry, Team, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ausgabe-/Rückgabe-Workflow (Checkout) und Defekt-/Sperrstatus (Feature 009).
 *
 * Verfügbarkeit & Sperre werden NICHT über neue AssetStatus-Enum-Werte
 * abgebildet, sondern aus den Tabellen `asset_assignments` (offene Zuweisung =
 * ausgegeben) und `asset_defects` (offener blockierender Defekt = gesperrt)
 * abgeleitet. Zur Kompatibilität mit der bestehenden Index-/Detail-Darstellung
 * wird der vorhandene Asset.status zusätzlich — soweit die Statusmaschine es
 * zulässt — auf die bereits existierenden Werte loanOut (ausgegeben) bzw.
 * blocked (gesperrt) gespiegelt. Es werden keine neuen Enum-Werte erfunden.
 */
class AssetAssignmentService {
    /** Defekt-Statusmaschine: zulässige Folgestatus je Ausgangsstatus. */
    private const DEFECT_TRANSITIONS = [
        'open' => ['inRepair', 'resolved', 'writtenOff'],
        'inRepair' => ['resolved', 'writtenOff', 'open'],
        'resolved' => ['open'],
        'writtenOff' => [],
    ];

    public function __construct(
        private readonly AssetStatusMachine $statusMachine,
        private readonly AssetUsageGuard $usageGuard,
    ) {}

    // ── Ausgabe / Rückgabe ─────────────────────────────────────────────────

    public function checkOut(
        Asset $asset,
        User $actor,
        ?User $targetUser = null,
        ?Team $targetTeam = null,
        ?Carbon $expectedReturnAt = null,
        ?DiaryEntry $diaryEntry = null,
        ?string $conditionOut = null,
        ?string $note = null,
    ): AssetAssignment {
        if ($targetUser === null && $targetTeam === null) {
            throw AssetValidationException::assignmentTargetRequired();
        }

        // Blockierender Defekt zuerst (eigene, spezifische Fehlermeldung).
        if ($this->isBlocked($asset)) {
            throw AssetValidationException::blockedByDefect();
        }

        // Vollaudit 2026-07 (H2/H3): D12-Sperrmodell greift auch beim internen
        // Checkout — Compliance-/Verleih-Sperren (asset_blocks, z. B.
        // inspection_overdue/inspection_failed) blocken die Ausgabe;
        // Ausnahmefreigaben (Kontext 'usage') laufen über den Guard. Bewusst
        // VOR der Transaktion: der Guard schreibt eine Audit-Spur, die bei einem
        // Rollback (Exception) sonst verloren ginge (wie im Rental-Pfad).
        $this->usageGuard->ensureUsable($asset, 'checkout');

        return DB::transaction(function () use ($asset, $actor, $targetUser, $targetTeam, $expectedReturnAt, $diaryEntry, $conditionOut, $note): AssetAssignment {
            // Asset-Zeile sperren und Verfügbarkeit/offene Zuweisung INNERHALB der Transaktion prüfen. Vorher lagen
            // die Guards außerhalb → zwei parallele Checkouts gaben dasselbe Asset doppelt aus (kein partieller Unique-Index).
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($this->isBlocked($asset)) {
                throw AssetValidationException::blockedByDefect();
            }
            if (! $this->isStatusAvailable($asset)) {
                throw AssetValidationException::notAvailableForCheckout();
            }
            if ($this->openAssignment($asset) !== null) {
                throw AssetValidationException::alreadyCheckedOut();
            }

            /** @var AssetAssignment $assignment */
            $assignment = $asset->assignments()->create([
                'organization_id' => (int) $asset->organization_id,
                'assigned_to_user_id' => $targetUser?->id,
                'assigned_to_team_id' => $targetTeam?->id,
                'diary_entry_id' => $diaryEntry?->id,
                'checked_out_at' => Carbon::now(),
                'checked_out_by_user_id' => $actor->id,
                'expected_return_at' => $expectedReturnAt,
                'condition_out' => $conditionOut,
                'note' => $note,
            ]);

            $this->syncStatusTo($asset, AssetStatus::LoanOut);

            $assignment->audit('assetAssignment.checkedOut', [
                'asset_id' => $asset->id,
                'assigned_to_user_id' => $targetUser?->id,
                'assigned_to_team_id' => $targetTeam?->id,
                'diary_entry_id' => $diaryEntry?->id,
                'actor_id' => $actor->id,
            ]);
            $asset->audit('asset.checkedOut', ['assignment_id' => $assignment->id, 'actor_id' => $actor->id]);

            return $assignment->refresh();
        });
    }

    public function checkIn(AssetAssignment $assignment, User $actor, ?string $conditionIn = null): AssetAssignment {
        if ($assignment->returned_at !== null) {
            throw AssetValidationException::assignmentAlreadyReturned();
        }

        return DB::transaction(function () use ($assignment, $actor, $conditionIn): AssetAssignment {
            $assignment->returned_at = Carbon::now();
            $assignment->returned_by_user_id = $actor->id;
            if ($conditionIn !== null && trim($conditionIn) !== '') {
                $assignment->condition_in = $conditionIn;
            }
            $assignment->save();

            $asset = $assignment->asset()->first();
            if ($asset instanceof Asset) {
                // Nach Rückgabe wieder verfügbar — sofern kein blockierender Defekt besteht (sonst bleibt Status blocked).
                if ($this->isBlocked($asset)) {
                    $this->syncStatusTo($asset, AssetStatus::Blocked);
                } else {
                    $this->syncStatusTo($asset, AssetStatus::Active);
                }
                $asset->audit('asset.checkedIn', ['assignment_id' => $assignment->id, 'actor_id' => $actor->id]);
            }

            $assignment->audit('assetAssignment.checkedIn', [
                'asset_id' => $assignment->asset_id,
                'actor_id' => $actor->id,
            ]);

            return $assignment->refresh();
        });
    }

    // ── Defekte / Sperre ───────────────────────────────────────────────────

    /**
     * @param  array{severity?: string, title: string, description?: string|null, blocks_usage?: bool}  $data
     */
    public function reportDefect(Asset $asset, User $actor, array $data): AssetDefect {
        $severity = DefectSeverity::tryFrom((string) ($data['severity'] ?? '')) ?? DefectSeverity::Medium;
        $blocksUsage = (bool) ($data['blocks_usage'] ?? false);

        return DB::transaction(function () use ($asset, $actor, $severity, $blocksUsage, $data): AssetDefect {
            /** @var AssetDefect $defect */
            $defect = $asset->defects()->create([
                'organization_id' => (int) $asset->organization_id,
                'reported_by_user_id' => $actor->id,
                'reported_at' => Carbon::now(),
                'severity' => $severity->value,
                'title' => (string) $data['title'],
                'description' => $data['description'] ?? null,
                'status' => DefectStatus::Open->value,
                'blocks_usage' => $blocksUsage,
            ]);

            if ($blocksUsage) {
                $this->syncStatusTo($asset, AssetStatus::Blocked);
            }

            $defect->audit('assetDefect.reported', [
                'asset_id' => $asset->id,
                'severity' => $severity->value,
                'blocks_usage' => $blocksUsage,
                'actor_id' => $actor->id,
            ]);
            $asset->audit('asset.defectReported', ['defect_id' => $defect->id, 'blocks_usage' => $blocksUsage, 'actor_id' => $actor->id]);

            return $defect->refresh();
        });
    }

    public function markInRepair(AssetDefect $defect, User $actor): AssetDefect {
        return $this->transitionDefect($defect, $actor, DefectStatus::InRepair, null);
    }

    public function resolveDefect(AssetDefect $defect, User $actor, string $resolutionNote): AssetDefect {
        if (trim($resolutionNote) === '') {
            throw AssetValidationException::defectResolutionNoteRequired();
        }

        return $this->transitionDefect($defect, $actor, DefectStatus::Resolved, $resolutionNote);
    }

    public function writeOff(AssetDefect $defect, User $actor, string $resolutionNote): AssetDefect {
        if (trim($resolutionNote) === '') {
            throw AssetValidationException::defectResolutionNoteRequired();
        }

        return $this->transitionDefect($defect, $actor, DefectStatus::WrittenOff, $resolutionNote);
    }

    private function transitionDefect(AssetDefect $defect, User $actor, DefectStatus $to, ?string $resolutionNote): AssetDefect {
        $from = $defect->status;
        if ($from !== $to && ! in_array($to->value, self::DEFECT_TRANSITIONS[$from->value], true)) {
            throw AssetValidationException::invalidDefectTransition($from->value, $to->value);
        }

        return DB::transaction(function () use ($defect, $actor, $to, $resolutionNote, $from): AssetDefect {
            $defect->status = $to;
            if ($to->isClosed()) {
                $defect->resolved_at = Carbon::now();
                $defect->resolved_by_user_id = $actor->id;
                if ($resolutionNote !== null) {
                    $defect->resolution_note = $resolutionNote;
                }
            }
            $defect->save();

            $asset = $defect->asset()->first();
            if ($asset instanceof Asset) {
                // Sperre ggf. aufheben, wenn kein blockierender Defekt mehr offen ist
                // und das Asset nicht ausgegeben ist.
                if (! $this->isBlocked($asset) && $asset->status === AssetStatus::Blocked) {
                    $target = $this->openAssignment($asset) !== null ? AssetStatus::LoanOut : AssetStatus::Active;
                    $this->syncStatusTo($asset, $target);
                }
                $asset->audit('asset.defectUpdated', ['defect_id' => $defect->id, 'to' => $to->value, 'actor_id' => $actor->id]);
            }

            $defect->audit('assetDefect.statusChanged', [
                'from' => $from->value,
                'to' => $to->value,
                'actor_id' => $actor->id,
            ]);

            return $defect->refresh();
        });
    }

    // ── Verfügbarkeits-/Sperr-Ableitung ────────────────────────────────────

    /** Offene (noch nicht zurückgegebene) Zuweisung — oder null. */
    public function openAssignment(Asset $asset): ?AssetAssignment {
        return $asset->assignments()->whereNull('returned_at')->first();
    }

    /** Gesperrt, wenn mindestens ein offener blockierender Defekt existiert. */
    public function isBlocked(Asset $asset): bool {
        return $asset->defects()->blocking()->exists();
    }

    /** Aktuell ausgegeben? */
    public function isCheckedOut(Asset $asset): bool {
        return $this->openAssignment($asset) !== null;
    }

    /** Verfügbar für eine Ausgabe? */
    public function isAvailable(Asset $asset): bool {
        return ! $this->isBlocked($asset)
            && ! $this->isCheckedOut($asset)
            && $this->isStatusAvailable($asset);
    }

    /**
     * Endgültig nicht mehr einsatzfähige Status verbieten den Checkout.
     * inMaintenance/inRepair/reserved werden bewusst NICHT gesperrt — das deckt
     * der blockierende Defekt bzw. die offene Zuweisung ab.
     */
    private function isStatusAvailable(Asset $asset): bool {
        return ! in_array($asset->status, [
            AssetStatus::Decommissioned,
            AssetStatus::Replaced,
            AssetStatus::Lost,
        ], true);
    }

    /**
     * Spiegelt einen abgeleiteten Zustand auf den vorhandenen Asset.status,
     * sofern die Statusmaschine den Wechsel zulässt. Schlägt der Wechsel fehl
     * (z. B. aus inMaintenance heraus), bleibt der Status unverändert — die
     * Verfügbarkeit wird ohnehin primär aus den Tabellen abgeleitet.
     */
    private function syncStatusTo(Asset $asset, AssetStatus $target): void {
        if ($asset->status === $target) {
            return;
        }
        if (! $this->statusMachine->canTransition($asset->status, $target)) {
            return;
        }
        $asset->status = $target;
        $asset->save();
    }
}
