<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleContractObligationSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Contract\ContractObligationKind;
use App\Enums\Reselling\RenewalMode;
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Organization;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Contract\ContractService;
use App\Support\DocumentLocale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Abo-Termine im Vertragskalender (Feature 152 ↔ 079): je Abo mit Vertrag
 * höchstens eine offene Marker-Obligation — Kündigungsfrist (gekündigt, Ende
 * bekannt: Ende − Kündigungsfrist des Vertrags) oder Verlängerungswarnung
 * (automatisch: Beginn der nächsten Periode − Kündigungsfrist). Kein zweiter
 * Fristenkatalog: Datum und Titel folgen dem Abo, erledigte Termine bleiben
 * erledigt, überfällige überlässt der Abgleich dem Fristen-Scan (versäumt);
 * Abos ohne Vertrag, ohne Planung oder ohne Termin schließen ihre offenen.
 *
 * Marker ist der Notiz-Anfang `resale:<abo-id>` — `contract_obligations`
 * hat keine Quellspalte, und der Marker überlebt Titeländerungen.
 */
final class ResaleContractObligationSync {
    public const MARKER_PREFIX = 'resale:';

    public const DEFAULT_NOTICE_DAYS = 30;

    public const WARN_DAYS_BEFORE = 14;

    public function __construct(private readonly PeriodPlanner $planner, private readonly ContractService $contracts) {}

    /**
     * @return array{created: int, updated: int, closed: int}
     */
    public function sync(Organization $organization, ?CarbonImmutable $reference = null): array {
        // Titel in der Org-Sprache: die Obligation ist ein gemeinsamer Datensatz, nicht die Sicht des Auslösers (Scheduler/CLI).
        return DocumentLocale::within(null, $organization, fn (): array => $this->run($organization, $reference ?? ResalePeriod::today()));
    }

    /**
     * @return array{created: int, updated: int, closed: int}
     */
    private function run(Organization $organization, CarbonImmutable $reference): array {
        $result = ['created' => 0, 'updated' => 0, 'closed' => 0];

        /** @var array<int, list<ContractObligation>> $open offene Marker-Obligationen je Abo */
        $open = [];
        /** @var array<int, list<ContractObligation>> $settled erledigte/versäumte je Abo */
        $settled = [];
        $marked = ContractObligation::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('note', 'like', self::MARKER_PREFIX . '%')
            ->orderBy('id')
            ->get();
        foreach ($marked as $obligation) {
            $subscriptionId = self::markedSubscriptionId($obligation);
            if ($subscriptionId === null) {
                continue;
            }
            if ($obligation->status === 'open') {
                $open[$subscriptionId][] = $obligation;
            } else {
                $settled[$subscriptionId][] = $obligation;
            }
        }

        $subscriptions = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('contract_id')
            ->planning()
            ->with(['contract', 'parent'])
            ->orderBy('id')
            ->get();
        foreach ($subscriptions as $subscription) {
            $contract = $subscription->contract;
            if ($contract === null || ! $contract->status->isOpen()) {
                continue; // offene Marker schließt der Rest unten
            }
            $expected = $this->expected($subscription, $contract, $reference);
            if ($expected === null) {
                continue;
            }
            $current = $open[$subscription->id] ?? [];
            unset($open[$subscription->id]);
            $live = [];
            foreach ($current as $obligation) {
                if ((int) $obligation->contract_id !== (int) $contract->id) {
                    $this->close($obligation); // Vertrag gewechselt: Termin am alten Vertrag ist hinfällig
                    $result['closed']++;
                } elseif ($obligation->due_on->toDateString() >= $reference->toDateString()) {
                    $live[] = $obligation;
                }
                // Überfällig und noch offen: der Fristen-Scan markiert versäumt — nicht umdatieren.
            }
            if ($live !== []) {
                $obligation = array_shift($live);
                foreach ($live as $duplicate) {
                    $this->close($duplicate);
                    $result['closed']++;
                }
                $obligation->fill(['kind' => $expected['kind'], 'due_on' => $expected['due_on']->toDateString(), 'title' => $expected['title']]);
                if ($obligation->isDirty()) {
                    $obligation->save();
                    $result['updated']++;
                }

                continue;
            }
            if ($expected['due_on']->lessThan($reference)) {
                continue; // verstrichener Termin: kein neuer, der sofort versäumt wäre
            }
            if ($this->alreadySettled($settled[$subscription->id] ?? [], $contract, $expected)) {
                continue; // erledigt/versäumt zu genau diesem Termin — nicht wieder öffnen
            }
            $this->contracts->addObligation($contract, [
                'kind' => $expected['kind']->value,
                'title' => $expected['title'],
                'due_on' => $expected['due_on']->toDateString(),
                'warn_days_before' => self::WARN_DAYS_BEFORE,
                'responsible_user_id' => $contract->responsible_user_id,
                'note' => self::marker($subscription),
            ]);
            $result['created']++;
        }

        // Rest: Abo ohne Vertrag, beendet/abgelöst, Vertrag zu, kein Termin mehr — oder Abo gelöscht.
        foreach ($open as $obligations) {
            foreach ($obligations as $obligation) {
                $this->close($obligation);
                $result['closed']++;
            }
        }

        return $result;
    }

    public static function marker(ResaleSubscription $subscription): string {
        return self::MARKER_PREFIX . $subscription->id;
    }

    /** Abo-ID aus dem Marker am Notiz-Anfang, sonst null. */
    public static function markedSubscriptionId(ContractObligation $obligation): ?int {
        $note = (string) $obligation->getAttribute('note');
        if (preg_match('/^' . preg_quote(self::MARKER_PREFIX, '/') . '(\d+)(?!\d)/', $note, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * Erwarteter Termin des Abos: gekündigt mit Ende → Kündigungsfrist zum
     * Ende; automatisch → Verlängerungswarnung vor der nächsten geplanten
     * Periode (Planungshorizont des `PeriodPlanner`). Kündigungsfrist vom
     * Vertrag, ohne Angabe 30 Tage.
     *
     * @return array{kind: ContractObligationKind, due_on: CarbonImmutable, title: string}|null
     */
    private function expected(ResaleSubscription $subscription, Contract $contract, CarbonImmutable $reference): ?array {
        $noticeDays = max(0, (int) ($contract->notice_period_days ?? self::DEFAULT_NOTICE_DAYS));
        if ($subscription->renewal === RenewalMode::Cancel) {
            $end = $subscription->ends_on;
            if ($end === null) {
                return null;
            }

            return [
                'kind' => ContractObligationKind::NoticeDeadline,
                'due_on' => $end->subDays($noticeDays),
                'title' => Str::limit((string) __('resale.contract.obligation.notice', ['label' => $subscription->label, 'date' => $end->format('d.m.Y')]), 255, ''),
            ];
        }
        $next = null;
        foreach ($this->planner->plan($subscription, $reference) as $slot) {
            if ($slot['starts_on']->greaterThan($reference)) {
                $next = $slot['starts_on'];
                break;
            }
        }
        if ($next === null) {
            return null;
        }

        return [
            'kind' => ContractObligationKind::RenewalWarning,
            'due_on' => $next->subDays($noticeDays),
            'title' => Str::limit((string) __('resale.contract.obligation.renewal', ['label' => $subscription->label, 'date' => $next->format('d.m.Y')]), 255, ''),
        ];
    }

    /**
     * @param  list<ContractObligation>  $settled
     * @param  array{kind: ContractObligationKind, due_on: CarbonImmutable, title: string}  $expected
     */
    private function alreadySettled(array $settled, Contract $contract, array $expected): bool {
        foreach ($settled as $obligation) {
            if ((int) $obligation->contract_id === (int) $contract->id
                && $obligation->kind === $expected['kind']
                && $obligation->due_on->toDateString() === $expected['due_on']->toDateString()) {
                return true;
            }
        }

        return false;
    }

    private function close(ContractObligation $obligation): void {
        $obligation->forceFill(['status' => 'done', 'done_at' => now()])->save();
        $obligation->contract?->audit('contract.obligationClosed', ['obligation_id' => $obligation->id, 'source' => 'resale']);
    }
}
