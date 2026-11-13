<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Knowledge\{ContentReference, KnowledgePage};
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureTemplate;
use App\Models\Project\Project;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{MorphMap, Sqid};
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Verweise zwischen Wissensseiten und Fachobjekten (Feature 191, MVP-1667):
 * Kunde, Projekt, Asset, Auftrag, Ticket und Prozedurvorlage. Sie laufen über
 * die vorhandenen Verweise aus Feature 155 ({@see ContentReference}, Art
 * `linked`, Quelle die Seite) — wie die Problemhistorie der Wissensartikel.
 * Ein Verweis verleiht keinen Zugriff: beide Seiten erscheinen nur, wenn die
 * Person sie ohnehin öffnen darf.
 */
final class KnowledgePageLinks {
    /** @var array<string, array{class: class-string<Model>, route: string, title: string, icon: string}> */
    public const array TYPES = [
        'customer' => ['class' => Customer::class, 'route' => 'customers.show', 'title' => 'name', 'icon' => 'groups'],
        'project' => ['class' => Project::class, 'route' => 'projects.show', 'title' => 'name', 'icon' => 'folder_open'],
        'asset' => ['class' => Asset::class, 'route' => 'assets.show', 'title' => 'name', 'icon' => 'devices'],
        'order' => ['class' => DiaryEntry::class, 'route' => 'diary.show', 'title' => 'title', 'icon' => 'assignment'],
        'ticket' => ['class' => ServiceTicket::class, 'route' => 'service-tickets.show', 'title' => 'title', 'icon' => 'confirmation_number'],
        'procedure' => ['class' => ProcedureTemplate::class, 'route' => 'procedures.edit', 'title' => 'name', 'icon' => 'checklist'],
    ];

    /** Auswahl je Art im Dialog. */
    private const int PICKER_LIMIT = 100;

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly KnowledgePageAccess $access,
    ) {}

    public function keyFor(Model $subject): ?string {
        foreach (self::TYPES as $key => $type) {
            if ($subject instanceof $type['class']) {
                return $key;
            }
        }

        return null;
    }

    /** Verknüpfbares Objekt aus Art und Kennung — 404, wenn es fehlt oder die Person es nicht sehen darf. */
    public function findVisibleOrFail(string $type, string $sqid, User $viewer): Model {
        $class = self::TYPES[$type]['class'] ?? null;
        abort_if($class === null || ! $this->features->routeEnabled(self::TYPES[$type]['route']), 404);
        $id = Sqid::decode($class, $sqid);
        $subject = $id !== null ? $class::query()->find($id) : null;
        abort_if(! $subject instanceof Model || ! Gate::forUser($viewer)->allows('view', $subject), 404);

        return $subject;
    }

    public function link(KnowledgePage $page, Model $subject, User $actor): ContentReference {
        $key = $this->keyFor($subject);
        if ($key === null || (int) $subject->getAttribute('organization_id') !== (int) $page->organization_id) {
            throw ValidationException::withMessages(['target' => (string) __('knowledge_pages.links.error.not_allowed')]);
        }
        $attributes = [
            'source_type' => $page->getMorphClass(),
            'source_id' => $page->id,
            'target_type' => $subject->getMorphClass(),
            'target_id' => (int) $subject->getKey(),
            'kind' => ContentReference::KIND_LINKED,
        ];
        $existing = ContentReference::query()->where($attributes)->first();
        if ($existing instanceof ContentReference) {
            return $existing;
        }
        $reference = ContentReference::query()->create([...$attributes, 'organization_id' => $page->organization_id, 'created_by' => $actor->id]);
        $page->audit('knowledge_page.linked', ['type' => $key, 'id' => (int) $subject->getKey()]);

        return $reference;
    }

    public function unlink(KnowledgePage $page, ContentReference $reference): void {
        if ($reference->kind !== ContentReference::KIND_LINKED
            || ! MorphMap::is($reference->source_type, KnowledgePage::class)
            || (int) $reference->source_id !== (int) $page->id) {
            abort(404);
        }
        $reference->delete();
        $page->audit('knowledge_page.unlinked', ['target_id' => $reference->target_id]);
    }

    /**
     * Verknüpfte Objekte einer Seite, soweit die Person sie öffnen darf.
     *
     * @return list<array{reference: ContentReference, type: string, title: string, url: string, icon: string}>
     */
    public function subjectsOf(KnowledgePage $page, User $viewer): array {
        $rows = [];
        $references = ContentReference::query()
            ->where('source_type', $page->getMorphClass())
            ->where('source_id', $page->id)
            ->where('kind', ContentReference::KIND_LINKED)
            ->with('target')
            ->orderBy('id')
            ->get();
        foreach ($references as $reference) {
            $target = $reference->target;
            $key = $target instanceof Model ? $this->keyFor($target) : null;
            if ($key === null || ! $this->features->routeEnabled(self::TYPES[$key]['route']) || ! Gate::forUser($viewer)->allows('view', $target)) {
                continue;
            }
            $rows[] = [
                'reference' => $reference,
                'type' => $key,
                'title' => $this->title($target, $key),
                'url' => route(self::TYPES[$key]['route'], $target),
                'icon' => self::TYPES[$key]['icon'],
            ];
        }

        return $rows;
    }

    /**
     * Sichtbare Wissensseiten, die mit dem Objekt verknüpft sind.
     *
     * @return Collection<int, KnowledgePage>
     */
    public function pagesFor(Model $subject, User $viewer): Collection {
        $ids = ContentReference::query()
            ->where('source_type', MorphMap::alias(KnowledgePage::class))
            ->where('target_type', $subject->getMorphClass())
            ->where('target_id', $subject->getKey())
            ->where('kind', ContentReference::KIND_LINKED)
            ->pluck('source_id')
            ->all();
        if ($ids === []) {
            return new Collection();
        }

        return $this->access->scope(KnowledgePage::query(), $viewer)
            ->whereKey($ids)
            ->with('space:id,title')
            ->orderBy('title')
            ->get();
    }

    /**
     * Auswahl „Verknüpfen“ auf der Seite: je Art die zuletzt geänderten
     * sichtbaren Objekte, ohne bereits verknüpfte.
     *
     * @return list<array{type: string, label: string, items: list<array{value: string, title: string}>}>
     */
    public function pickerOptions(KnowledgePage $page, User $viewer): array {
        $taken = array_map(static fn (array $row): string => $row['type'] . ':' . $row['reference']->target_id, $this->subjectsOf($page, $viewer));
        $groups = [];
        foreach (self::TYPES as $key => $type) {
            if (! $this->features->routeEnabled($type['route'])) {
                continue;
            }
            $items = [];
            $candidates = $type['class']::query()
                ->where('organization_id', $page->organization_id)
                ->latest('updated_at')
                ->limit(self::PICKER_LIMIT * 3)
                ->get();
            foreach ($candidates as $candidate) {
                if (in_array($key . ':' . $candidate->getKey(), $taken, true) || ! Gate::forUser($viewer)->allows('view', $candidate)) {
                    continue;
                }
                $items[] = ['value' => $key . ':' . Sqid::encode($candidate::class, (int) $candidate->getKey()), 'title' => $this->title($candidate, $key)];
                if (count($items) >= self::PICKER_LIMIT) {
                    break;
                }
            }
            usort($items, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));
            if ($items !== []) {
                $groups[] = ['type' => $key, 'label' => (string) __('knowledge_pages.links.type.' . $key), 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Auswahl „Wissensseite verknüpfen“ am Objekt: sichtbare Seiten, die noch
     * nicht verknüpft sind.
     *
     * @return list<array{value: string, title: string}>
     */
    public function pageOptions(Model $subject, User $viewer): array {
        $linked = $this->pagesFor($subject, $viewer)->modelKeys();

        return array_values($this->access->scope(KnowledgePage::query(), $viewer)
            ->whereKeyNot($linked)
            ->orderBy('title')
            ->limit(300)
            ->get(['id', 'title'])
            ->map(static fn (KnowledgePage $page): array => ['value' => $page->sqid, 'title' => $page->title])
            ->all());
    }

    private function title(Model $subject, string $key): string {
        $title = trim((string) $subject->getAttribute(self::TYPES[$key]['title']));

        return $title !== '' ? $title : '#' . $subject->getKey();
    }
}
