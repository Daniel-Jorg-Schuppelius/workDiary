<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NodeConversionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas;

use App\Models\{Customer, DiaryEntry, IdeaNode, IdeaNodeReference, KnowledgeArticle, Project, Task, User};
use App\Services\Knowledge\KnowledgeArticleService;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Gate};
use RuntimeException;

/**
 * Überführt beschlossene Ideen-Knoten in die führenden Arbeits- und
 * Wissensmodule (Feature 054, MVP-109) — ohne deren Statuslogik zu
 * duplizieren: Aufgabe/Kanban über `Task::create`, Projekt über
 * `Project::create`, Wissensartikel-Entwurf über den
 * {@see KnowledgeArticleService}. Der Ausgangsknoten bleibt unverändert;
 * die Rückreferenz ist idempotent (je Knoten höchstens EIN überführtes Ziel
 * je Zieltyp). Zielmodul-Gating und Ziel-Policies werden je Aktion einzeln
 * geprüft.
 */
class NodeConversionService {
    /** @var array<string, class-string<Model>> Whitelist verlinkbarer Ziele (Muster KnowledgeArticleLink). */
    public const LINKABLE_MAP = [
        'customer' => Customer::class,
        'project' => Project::class,
        'diary' => DiaryEntry::class,
    ];

    public function __construct(private readonly FeatureFlagResolver $features) {}

    /** Überführt den Knoten als (globale) Kanban-Aufgabe. */
    public function convertToTask(IdeaNode $node, User $actor): IdeaNodeReference {
        if (! $this->features->isEnabled('module.kanban')) {
            throw new RuntimeException((string) __('ideas.convert.error.module_disabled'));
        }
        Gate::forUser($actor)->authorize('create', Task::class);

        return $this->convert($node, $actor, Task::class, function () use ($node, $actor): Task {
            $map = $node->map()->firstOrFail();

            return Task::query()->create([
                'organization_id' => $node->organization_id,
                'project_id' => $map->project_id,
                'is_global' => $map->project_id === null,
                'title' => $node->title,
                'description' => $node->note,
                'created_by' => $actor->id,
            ]);
        });
    }

    /** Überführt den Knoten als neues Projekt (Slug/Defaults setzt Project::booted). */
    public function convertToProject(IdeaNode $node, User $actor): IdeaNodeReference {
        Gate::forUser($actor)->authorize('create', Project::class);

        return $this->convert($node, $actor, Project::class, function () use ($node): Project {
            $map = $node->map()->firstOrFail();

            return Project::query()->create([
                'organization_id' => $node->organization_id,
                'customer_id' => $map->customer_id,
                'name' => $node->title,
                'description' => $node->note,
            ]);
        });
    }

    /** Überführt den Knoten als Wissensartikel-ENTWURF (Status setzt der Service). */
    public function convertToKnowledgeArticle(IdeaNode $node, User $actor): IdeaNodeReference {
        if (! $this->features->isEnabled('module.knowledge')) {
            throw new RuntimeException((string) __('ideas.convert.error.module_disabled'));
        }
        Gate::forUser($actor)->authorize('create', KnowledgeArticle::class);

        return $this->convert($node, $actor, KnowledgeArticle::class, function () use ($node, $actor): KnowledgeArticle {
            return app(KnowledgeArticleService::class)->create($actor, [
                'title' => $node->title,
                'problem' => $node->note ?? $node->title,
                'solution' => '',
            ]);
        });
    }

    /** Verweist den Knoten auf einen bestehenden Kunden/Projekt/Auftrag (kind = linked). */
    public function linkTo(IdeaNode $node, Model $target, User $actor): IdeaNodeReference {
        if (! in_array($target::class, self::LINKABLE_MAP, true)) {
            throw new RuntimeException((string) __('ideas.convert.error.target_not_allowed'));
        }
        if ((int) $target->getAttribute('organization_id') !== (int) $node->organization_id) {
            throw new RuntimeException((string) __('ideas.convert.error.target_not_allowed'));
        }

        /** @var IdeaNodeReference */
        return $node->references()->firstOrCreate([
            'target_type' => $target->getMorphClass(),
            'kind' => IdeaNodeReference::KIND_LINKED,
        ], [
            'organization_id' => $node->organization_id,
            'target_id' => $target->getKey(),
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Gemeinsamer Überführungspfad: idempotent je (Knoten, Zieltyp) — ein
     * zweiter Versuch liefert die bestehende Referenz mit `wasRecentlyCreated
     * === false` (der Controller zeigt dann Hinweis + Link statt Duplikat).
     *
     * @param  class-string<Model>  $targetClass
     * @param  callable(): Model  $factory
     */
    private function convert(IdeaNode $node, User $actor, string $targetClass, callable $factory): IdeaNodeReference {
        $morph = (new $targetClass())->getMorphClass();

        $existing = $node->references()
            ->where('target_type', $morph)
            ->where('kind', IdeaNodeReference::KIND_CONVERTED)
            ->first();
        if ($existing instanceof IdeaNodeReference) {
            return $existing;
        }

        return DB::transaction(function () use ($node, $actor, $morph, $factory): IdeaNodeReference {
            $target = $factory();

            /** @var IdeaNodeReference $reference */
            $reference = $node->references()->create([
                'organization_id' => $node->organization_id,
                'target_type' => $morph,
                'target_id' => $target->getKey(),
                'kind' => IdeaNodeReference::KIND_CONVERTED,
                'created_by' => $actor->id,
            ]);
            $node->audit('idea_node.converted', ['target_type' => $morph, 'target_id' => (int) $target->getKey()]);

            return $reference;
        });
    }
}
