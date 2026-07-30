<?php
/*
 * Created on   : Mon Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationResolveInboxCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{ExternalReference, IntegrationInboxItem, Organization};
use App\Services\Integration\{InboxActionService, MatchProfileRegistry};
use App\Services\Integration\Match\{EntityMatcher, MatchResult};
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Massen-Auflösung offener Zuordnungs-Inbox-Items über die generische
 * {@see EntityMatcher}-Engine des passenden
 * {@see \App\Services\Integration\Match\MatchProfile}: eindeutige Exact-Treffer
 * automatisch zuordnen (--auto-link), unsichere anreichern, fehlende optional
 * anlegen (--create).
 *
 * Sequenzielle Verarbeitung, damit ein frisch angelegter Datensatz für Folge-
 * Items zum Exact-Kandidaten wird (Selbst-Dedup); eine Hijack-Sperre verhindert
 * das Kapern eines bereits fremd gebundenen Kandidaten.
 */
class IntegrationResolveInboxCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'integration:resolve-inbox ' . self::ORGANIZATION_OPTION . '
        {--plugin= : nur Items dieser Quelle (z. B. lexoffice)}
        {--case=unmatched : Fall-Typ (unmatched|ambiguous)}
        {--auto-link : eindeutige Exact-Treffer automatisch zuordnen (mergen)}
        {--create : nach dem Matching verbleibende Items ohne Treffer als neuen Datensatz anlegen}
        {--dry-run : nichts schreiben, nur zählen}';

    protected $description = 'Löst offene Zuordnungs-Inbox-Items per generischer Match-Engine auf: eindeutige automatisch mergen, unsichere mit Kandidaten anreichern, fehlende optional anlegen.';

    public function handle(MatchProfileRegistry $registry, EntityMatcher $matcher, InboxActionService $actions): int {
        $plugin = (string) ($this->option('plugin') ?: '');
        $case = (string) ($this->option('case') ?: IntegrationInboxItem::CASE_UNMATCHED);
        $autoLink = (bool) $this->option('auto-link');
        $create = (bool) $this->option('create');
        $dry = (bool) $this->option('dry-run');

        $organizations = $this->organizationsToProcess();

        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $this->resolveForOrganization($org, $registry, $matcher, $actions, $plugin, $case, $autoLink, $create, $dry);
        }

        return self::SUCCESS;
    }

    private function resolveForOrganization(
        Organization $org,
        MatchProfileRegistry $registry,
        EntityMatcher $matcher,
        InboxActionService $actions,
        string $plugin,
        string $case,
        bool $autoLink,
        bool $create,
        bool $dry,
    ): void {
        $counters = ['linked' => 0, 'suggested' => 0, 'created' => 0, 'unmatched' => 0, 'skipped' => 0, 'failed' => 0];

        $query = IntegrationInboxItem::query()
            ->where('organization_id', $org->id)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->where('case_type', $case)
            ->when($plugin !== '', fn($q) => $q->where('plugin_id', $plugin))
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info("Organisation #{$org->id} ({$org->name}): keine offenen '{$case}'-Items.");

            return;
        }

        $this->info("Organisation #{$org->id} ({$org->name}): {$total} '{$case}'-Items"
            . ($dry ? ' [DRY-RUN]' : '') . ' …');

        // chunkById ist robust gegen Statuswechsel während der Iteration.
        $query->chunkById(200, function ($items) use ($org, $registry, $matcher, $actions, $autoLink, $create, $dry, &$counters): void {
            foreach ($items as $item) {
                try {
                    $this->resolveItem($item, $org, $registry, $matcher, $actions, $autoLink, $create, $dry, $counters);
                } catch (\Throwable $e) {
                    $counters['failed']++;
                    $this->warn("  Item #{$item->id} ({$item->display_title}): {$e->getMessage()}");
                }
            }
        });

        $this->line(sprintf(
            '  zugeordnet: %d, Vorschläge: %d, angelegt: %d, offen: %d, übersprungen: %d, Fehler: %d',
            $counters['linked'], $counters['suggested'], $counters['created'], $counters['unmatched'], $counters['skipped'], $counters['failed'],
        ));
    }

    /**
     * @param  array<string, int>  $counters
     */
    private function resolveItem(
        IntegrationInboxItem $item,
        Organization $org,
        MatchProfileRegistry $registry,
        EntityMatcher $matcher,
        InboxActionService $actions,
        bool $autoLink,
        bool $create,
        bool $dry,
        array &$counters,
    ): void {
        $profile = $registry->for((string) $item->target_type);
        if ($profile === null) {
            $counters['skipped']++;

            return;
        }

        $mapped = (array) ($item->mapped_snapshot ?? []);
        $result = $matcher->match($org, $profile, $mapped);

        $exact = $result->uniqueExact();
        if ($autoLink && $exact instanceof Model) {
            if ($this->wouldHijack($item, $exact)) {
                // Kandidat ist bereits an eine andere Fremd-ID gebunden → Mensch entscheidet.
                $this->markAmbiguous($item, $result, $dry);
                $counters['suggested']++;

                return;
            }
            if (! $dry) {
                $actions->assignTo($item, $exact);
            }
            $counters['linked']++;

            return;
        }

        if (! $result->isEmpty()) {
            $this->markAmbiguous($item, $result, $dry);
            $counters['suggested']++;

            return;
        }

        if ($create) {
            if (! $dry) {
                $actions->createFromItem($item);
            }
            $counters['created']++;

            return;
        }

        $counters['unmatched']++;
    }

    /**
     * Prüft, ob das Zuordnen den Kandidaten kapern würde: existiert bereits eine
     * ExternalReference derselben Quelle/Typ auf diesen Kandidaten mit ABWEICHENDER
     * Fremd-ID, ist die Zuordnung mehrdeutig und darf nicht automatisch erfolgen.
     */
    private function wouldHijack(IntegrationInboxItem $item, Model $candidate): bool {
        if ($item->external_id === null || $item->external_id === '') {
            return false;
        }

        return ExternalReference::query()
            ->forPlugin($item->organization_id, $item->plugin_id, $item->external_type)
            ->forReferenceable($candidate)
            ->where('external_id', '!=', $item->external_id)
            ->exists();
    }

    /**
     * Reichert ein Item mit den gefundenen Kandidaten an und markiert es als
     * ambiguous, damit die Inbox-UI Zuordnungs-Vorschläge anbietet.
     */
    private function markAmbiguous(IntegrationInboxItem $item, MatchResult $result, bool $dry): void {
        if ($dry) {
            return;
        }

        $best = $result->best();
        if ($best === null) {
            // Ohne Kandidaten gibt es nichts vorzuschlagen — nichts anfassen.
            return;
        }
        $item->update([
            'case_type' => IntegrationInboxItem::CASE_AMBIGUOUS,
            'referenceable_type' => $best['model']->getMorphClass(),
            'referenceable_id' => $best['model']->getKey(),
            'candidate_ids' => $this->candidatePayload($result->candidates()),
        ]);
    }

    /**
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $candidates
     * @return list<array{id: int, sqid: string, label: string, confidence: string, reasons: list<string>}>
     */
    private function candidatePayload(array $candidates): array {
        return array_map(static function (array $c): array {
            $model = $c['model'];

            return [
                'id' => (int) $model->getKey(),
                'sqid' => $model->getRouteKey(),
                'label' => (string) ($model->getAttribute('name') ?? ('#' . $model->getKey())),
                'confidence' => $c['confidence'],
                'reasons' => $c['reasons'],
            ];
        }, $candidates);
    }
}
