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

use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Ideas\IdeaNode;
use App\Models\Knowledge\{ContentReference, KnowledgeArticle};
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Services\Knowledge\KnowledgeArticleService;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;
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
 *
 * Seit MVP-813 (Feature 155) die allgemeine Stelle fürs Umwandeln: auch aus
 * einer Notiz wird hier ein Wissensartikel, der Verweis Notiz → Artikel
 * (`converted`) ist der Herkunftsvermerk.
 */
class NodeConversionService {
    /** @var array<string, class-string<Model>> Whitelist verlinkbarer Ziele. */
    public const LINKABLE_MAP = [
        'customer' => Customer::class,
        'project' => Project::class,
        'diary' => DiaryEntry::class,
    ];

    public function __construct(private readonly FeatureFlagResolver $features) {}

    /** Überführt den Knoten als (globale) Kanban-Aufgabe. */
    public function convertToTask(IdeaNode $node, User $actor): ContentReference {
        if (! $this->features->isEnabled('module.kanban')) {
            throw new RuntimeException((string) __('ideas.convert.error.module_disabled'));
        }
        Gate::forUser($actor)->authorize('create', Task::class);

        return $this->convert($node, $actor, Task::class, 'idea_node.converted', function () use ($node, $actor): Task {
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
    public function convertToProject(IdeaNode $node, User $actor): ContentReference {
        Gate::forUser($actor)->authorize('create', Project::class);

        return $this->convert($node, $actor, Project::class, 'idea_node.converted', function () use ($node): Project {
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
    public function convertToKnowledgeArticle(IdeaNode $node, User $actor): ContentReference {
        if (! $this->features->isEnabled('module.knowledge')) {
            throw new RuntimeException((string) __('ideas.convert.error.module_disabled'));
        }
        Gate::forUser($actor)->authorize('create', KnowledgeArticle::class);

        return $this->convert($node, $actor, KnowledgeArticle::class, 'idea_node.converted', function () use ($node, $actor): KnowledgeArticle {
            return app(KnowledgeArticleService::class)->create($actor, [
                'title' => $node->title,
                'problem' => $node->note ?? $node->title,
                'solution' => '',
            ]);
        });
    }

    /**
     * Überführt eine Notiz in einen Wissensartikel-Entwurf (MVP-813). Betreff
     * wird Titel, Text wird Problembeschreibung, Schlagwörter wandern mit.
     * Vertrauliche Notizen bleiben draußen — der Artikel wäre intern für alle
     * lesbar.
     */
    public function convertNoteToKnowledgeArticle(CommunicationNote $note, User $actor): ContentReference {
        if (! $this->features->isEnabled('module.knowledge')) {
            throw new RuntimeException((string) __('ideas.convert.error.module_disabled'));
        }
        Gate::forUser($actor)->authorize('view', $note);
        Gate::forUser($actor)->authorize('create', KnowledgeArticle::class);
        if ($note->confidential) {
            throw new RuntimeException((string) __('communication.convert.error.confidential'));
        }

        return $this->convert($note, $actor, KnowledgeArticle::class, 'communication.converted', function () use ($note, $actor): KnowledgeArticle {
            $subject = trim((string) $note->subject);
            $body = trim((string) $note->body);

            return app(KnowledgeArticleService::class)->create($actor, [
                'title' => Str::limit($subject !== '' ? $subject : Str::of($body)->before("\n")->toString(), 180, ''),
                'problem' => Str::limit($body !== '' ? $body : $subject, 10000, ''),
                'solution' => '',
                'tags' => implode(', ', $note->tags->pluck('name')->all()),
            ]);
        });
    }

    /** Verweist den Knoten auf einen bestehenden Kunden/Projekt/Auftrag (kind = linked). */
    public function linkTo(IdeaNode $node, Model $target, User $actor): ContentReference {
        if (! in_array($target::class, self::LINKABLE_MAP, true)) {
            throw new RuntimeException((string) __('ideas.convert.error.target_not_allowed'));
        }
        if ((int) $target->getAttribute('organization_id') !== (int) $node->organization_id) {
            throw new RuntimeException((string) __('ideas.convert.error.target_not_allowed'));
        }

        /** @var ContentReference */
        return $node->references()->firstOrCreate([
            'target_type' => $target->getMorphClass(),
            'kind' => ContentReference::KIND_LINKED,
        ], [
            'organization_id' => $node->organization_id,
            'target_id' => $target->getKey(),
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Gemeinsamer Überführungspfad: idempotent je (Quelle, Zieltyp) — ein
     * zweiter Versuch liefert die bestehende Referenz mit `wasRecentlyCreated
     * === false` (der Controller zeigt dann Hinweis + Link statt Duplikat).
     *
     * @param  IdeaNode|CommunicationNote  $source
     * @param  class-string<Model>  $targetClass
     * @param  callable(): Model  $factory
     */
    private function convert(Model $source, User $actor, string $targetClass, string $auditEvent, callable $factory): ContentReference {
        $morph = (new $targetClass())->getMorphClass();

        $existing = ContentReference::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('target_type', $morph)
            ->where('kind', ContentReference::KIND_CONVERTED)
            ->first();
        if ($existing instanceof ContentReference) {
            return $existing;
        }

        return DB::transaction(function () use ($source, $actor, $morph, $auditEvent, $factory): ContentReference {
            $target = $factory();

            $reference = ContentReference::query()->create([
                'organization_id' => (int) $source->getAttribute('organization_id'),
                'source_type' => $source->getMorphClass(),
                'source_id' => (int) $source->getKey(),
                'target_type' => $morph,
                'target_id' => (int) $target->getKey(),
                'kind' => ContentReference::KIND_CONVERTED,
                'created_by' => $actor->id,
            ]);
            $source->audit($auditEvent, ['target_type' => $morph, 'target_id' => (int) $target->getKey()]);

            return $reference;
        });
    }
}
