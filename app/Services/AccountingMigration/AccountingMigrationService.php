<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AccountingMigration;

use App\Enums\Migration\{AccountingMigrationStatus, MigrationDataArea, MigrationProvider};
use App\Models\Migration\{AccountingMigrationEvent, AccountingMigrationItem, AccountingMigrationRun};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lebenszyklus eines Buchhaltungswechsels (MVP-653, Issue #86):
 * Planung → Analyse (Dry-Run) → Zuordnung → Doppelbetrieb → Umschaltung am
 * Stichtag → Prüfung → Abschluss.
 *
 * Verbindliche Grundsätze (aus dem Issue), die hier durchgesetzt werden:
 *  - je Organisation höchstens EIN offener Lauf;
 *  - ein Dry-Run verändert kein Fremdsystem (er schreibt ausschließlich
 *    lokale Migrationspositionen);
 *  - finalisierte Belege werden nie nachgebaut — sie bleiben Historie
 *    ({@see AccountingMigrationItem::STATUS_HISTORIC});
 *  - die Umschaltung ist blockiert, solange Konflikte oder unklare
 *    Schreibausgänge bestehen;
 *  - jeder Schritt ist auditiert (Hash-Kette) und idempotent.
 */
class AccountingMigrationService {
    public function __construct(private readonly MigrationAnalyzer $analyzer) {}

    /**
     * Neuen Lauf planen. Ein bereits offener Lauf derselben Organisation
     * verhindert die Anlage — zwei parallele Wechsel sind fachlich nie
     * korrekt.
     *
     * @param  array<int, MigrationDataArea>  $areas
     */
    public function plan(Organization $organization, array $areas, ?CarbonImmutable $cutoverOn, User $actor, MigrationProvider $source = MigrationProvider::Lexoffice, MigrationProvider $target = MigrationProvider::OrgaMax): AccountingMigrationRun {
        if ($this->openRunFor($organization) !== null) {
            throw new RuntimeException((string) __('Es läuft bereits ein Buchhaltungswechsel für diese Organisation.'));
        }
        if ($areas === []) {
            throw new RuntimeException((string) __('Mindestens ein Datenbereich muss gewählt werden.'));
        }
        if ($source === $target) {
            throw new RuntimeException((string) __('Quelle und Ziel müssen unterschiedliche Systeme sein.'));
        }

        return DB::transaction(function () use ($organization, $areas, $cutoverOn, $actor, $source, $target): AccountingMigrationRun {
            $run = AccountingMigrationRun::create([
                'organization_id' => $organization->id,
                'source_plugin' => $source->value,
                'target_plugin' => $target->value,
                'status' => AccountingMigrationStatus::Draft,
                'data_areas' => array_values(array_unique(array_map(
                    static fn (MigrationDataArea $area): string => $area->value,
                    $areas,
                ))),
                'cutover_on' => $cutoverOn?->toDateString(),
                'dry_run_only' => true,
                'responsible_user_id' => $actor->id,
            ]);

            $this->recordEvent($run, 'planned', [
                'areas' => $run->data_areas,
                'cutover_on' => $run->cutover_on?->toDateString(),
                'source' => $source->value,
                'target' => $target->value,
            ], $actor);

            return $run->refresh();
        });
    }

    /** Offener (nicht abgeschlossener/abgebrochener) Lauf der Organisation. */
    public function openRunFor(Organization $organization): ?AccountingMigrationRun {
        return AccountingMigrationRun::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotIn('status', [AccountingMigrationStatus::Completed->value, AccountingMigrationStatus::Cancelled->value])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Analyse-/Dry-Run: liest beide Systeme, legt Migrationspositionen an
     * und aktualisiert die Zählwerke. Schreibt NIE in ein Fremdsystem.
     */
    public function analyze(AccountingMigrationRun $run, User $actor): AccountingMigrationRun {
        $this->assertNotFinal($run);
        if ($run->status === AccountingMigrationStatus::Draft) {
            $this->transition($run, AccountingMigrationStatus::Analyzing, $actor);
        }

        $counters = $this->analyzer->run($run);
        $run->forceFill(['counters' => $counters])->save();

        $this->recordEvent($run, 'analyzed', ['counters' => $counters], $actor);

        // Nach der Analyse ist die Zuordnung dran (Konflikte entscheiden).
        if ($run->status === AccountingMigrationStatus::Analyzing) {
            $this->transition($run, AccountingMigrationStatus::Mapping, $actor);
        }

        return $run->refresh();
    }

    /**
     * Konfliktentscheidung: der Datensatz wird verknüpft, übersprungen oder
     * als Historie markiert. Immer mit Akteur und Zeitpunkt.
     */
    public function decideItem(AccountingMigrationItem $item, string $status, User $actor, ?string $note = null): AccountingMigrationItem {
        $allowed = [
            AccountingMigrationItem::STATUS_MATCHED,
            AccountingMigrationItem::STATUS_SKIPPED,
            AccountingMigrationItem::STATUS_HISTORIC,
            AccountingMigrationItem::STATUS_CONFLICT,
        ];
        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException((string) __('Unzulässige Entscheidung.'));
        }

        $item->forceFill([
            'status' => $status,
            'note' => $note,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ])->save();

        $run = $item->run;
        if ($run !== null) {
            $this->recordEvent($run, 'item_decided', [
                'item_id' => (int) $item->id,
                'area' => $item->data_area->value,
                'status' => $status,
            ], $actor);
        }

        return $item->refresh();
    }

    /**
     * Freigabe zum Doppelbetrieb: ab hier dürfen beide Verbindungen aktiv
     * sein. Voraussetzung ist ein konfliktfreier Stand und ein Stichtag.
     *
     * @return array<int, string> Blocker (leer = freigegeben)
     */
    public function startParallelRun(AccountingMigrationRun $run, User $actor): array {
        $blockers = $this->cutoverBlockers($run);
        if ($run->cutover_on === null) {
            $blockers[] = (string) __('Es ist kein Stichtag festgelegt.');
        }
        if ($blockers !== []) {
            $this->block($run, implode(' ', $blockers), $actor);

            return $blockers;
        }

        $this->transition($run, AccountingMigrationStatus::Ready, $actor);
        $this->transition($run, AccountingMigrationStatus::ParallelRun, $actor);
        $this->recordEvent($run, 'parallel_run_started', ['cutover_on' => $run->cutover_on?->toDateString()], $actor);

        return [];
    }

    /**
     * Umschaltung: setzt die Fakturahoheit der Organisation auf das
     * Zielsystem und stempelt den Stichtag auf die Kunden. Blockiert,
     * solange Konflikte oder unklare Schreibausgänge bestehen.
     *
     * @return array<int, string> Blocker (leer = umgeschaltet)
     */
    public function cutover(AccountingMigrationRun $run, User $actor): array {
        $blockers = $this->cutoverBlockers($run);
        if ($blockers !== []) {
            $this->block($run, implode(' ', $blockers), $actor);

            return $blockers;
        }

        DB::transaction(function () use ($run, $actor): void {
            $organization = $run->organization;
            $billingMode = $run->target()->billingMode();
            if ($organization !== null) {
                // Org-weite Fakturahoheit auf das Zielsystem des Laufs.
                $settings = (array) ($organization->settings ?? []);
                $settings['billing_mode'] = $billingMode->value;
                $organization->forceFill(['settings' => $settings])->save();

                // Stichtag je Kunde inkl. gesperrtem Quellsystem — die Sperre
                // ist damit richtungsunabhängig (siehe CutoverGuard).
                $cutoverOn = $run->cutover_on ?? now();
                \App\Models\Customer::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->update([
                        'billing_cutover_on' => $cutoverOn->toDateString(),
                        'billing_cutover_from' => $run->source()->value,
                    ]);
            }

            $run->forceFill(['cutover_at' => now(), 'dry_run_only' => false])->save();
            $this->transition($run, AccountingMigrationStatus::Cutover, $actor);
            $this->transition($run, AccountingMigrationStatus::Verifying, $actor);
            $this->recordEvent($run, 'cutover_executed', [
                'cutover_on' => $run->cutover_on?->toDateString(),
                'billing_mode' => $billingMode->value,
                'blocked_source' => $run->source()->value,
            ], $actor);
        });

        return [];
    }

    /**
     * Abschluss: nur ohne offene Konflikte und ohne unüberwachte offene
     * Altbelege. Erzeugt den Abschlussnachweis in der Ereigniskette.
     *
     * @return array<int, string> Blocker (leer = abgeschlossen)
     */
    public function complete(AccountingMigrationRun $run, User $actor): array {
        $blockers = $this->completionBlockers($run);
        if ($blockers !== []) {
            $this->block($run, implode(' ', $blockers), $actor);

            return $blockers;
        }

        $run->forceFill([
            'completed_by' => $actor->id,
            'completed_at' => now(),
            'blocked_reason' => null,
        ])->save();
        $this->transition($run, AccountingMigrationStatus::Completed, $actor);
        $this->recordEvent($run, 'completed', ['counters' => $run->counters], $actor);

        return [];
    }

    public function cancel(AccountingMigrationRun $run, User $actor, ?string $reason = null): AccountingMigrationRun {
        $this->transition($run, AccountingMigrationStatus::Cancelled, $actor);
        $this->recordEvent($run, 'cancelled', ['reason' => $reason], $actor);

        return $run->refresh();
    }

    /**
     * Blocker der Umschaltung: offene Konflikte, fehlgeschlagene Schreib-
     * versuche und noch nicht entschiedene Positionen.
     *
     * @return array<int, string>
     */
    public function cutoverBlockers(AccountingMigrationRun $run): array {
        $blockers = [];

        $conflicts = $run->items()->where('status', AccountingMigrationItem::STATUS_CONFLICT)->count();
        if ($conflicts > 0) {
            $blockers[] = (string) __(':n ungeklärte Zuordnungen blockieren die Umschaltung.', ['n' => $conflicts]);
        }

        $failed = $run->items()->where('status', AccountingMigrationItem::STATUS_FAILED)->count();
        if ($failed > 0) {
            $blockers[] = (string) __(':n Schreibvorgänge mit unklarem Ausgang müssen geklärt werden.', ['n' => $failed]);
        }

        $pending = $run->items()->where('status', AccountingMigrationItem::STATUS_PENDING)->count();
        if ($pending > 0) {
            $blockers[] = (string) __(':n Datensätze sind noch nicht entschieden.', ['n' => $pending]);
        }

        return $blockers;
    }

    /**
     * Blocker des Abschlusses: alles aus der Umschaltung plus offene
     * Altbelege, die noch im Quellsystem beglichen werden müssen.
     *
     * @return array<int, string>
     */
    public function completionBlockers(AccountingMigrationRun $run): array {
        $blockers = $this->cutoverBlockers($run);

        if ($run->cutover_at === null) {
            $blockers[] = (string) __('Die Umschaltung wurde noch nicht ausgeführt.');
        }

        $open = $this->analyzer->openSourceDocuments($run);
        if ($open > 0) {
            $blockers[] = (string) __(':n offene Altbelege sind im Quellsystem noch nicht ausgeglichen.', ['n' => $open]);
        }

        return $blockers;
    }

    /** Statusübergang gemäß Statusmaschine (mit Ereignis). */
    public function transition(AccountingMigrationRun $run, AccountingMigrationStatus $target, ?User $actor = null): AccountingMigrationRun {
        $current = $run->status;
        if ($current === $target) {
            return $run;
        }
        if (! $current->canTransitionTo($target)) {
            throw new RuntimeException(sprintf('Unzulässiger Statusübergang %s → %s.', $current->value, $target->value));
        }

        $run->forceFill(['status' => $target->value])->save();
        $this->recordEvent($run, 'status_changed', ['from' => $current->value, 'to' => $target->value], $actor);

        return $run->refresh();
    }

    private function block(AccountingMigrationRun $run, string $reason, User $actor): void {
        $run->forceFill(['blocked_reason' => mb_substr($reason, 0, 1000)])->save();
        if ($run->status->canTransitionTo(AccountingMigrationStatus::Blocked)) {
            $this->transition($run, AccountingMigrationStatus::Blocked, $actor);
        }
        $this->recordEvent($run, 'blocked', ['reason' => mb_substr($reason, 0, 500)], $actor);
    }

    private function assertNotFinal(AccountingMigrationRun $run): void {
        if ($run->status->isFinal()) {
            throw new RuntimeException((string) __('Der Lauf ist bereits abgeschlossen.'));
        }
    }

    /**
     * Ereignis in die Hash-Kette schreiben (Auditspur des Wechsels).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(AccountingMigrationRun $run, string $event, array $payload = [], ?User $actor = null): AccountingMigrationEvent {
        return AccountingMigrationEvent::create([
            'organization_id' => $run->organization_id,
            'accounting_migration_run_id' => $run->id,
            'event' => $event,
            'actor_user_id' => $actor?->id,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
