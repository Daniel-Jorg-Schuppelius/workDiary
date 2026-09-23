<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollectableTypes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections;

use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission;
use App\Models\Communication\CommunicationNote;
use App\Models\Concerns\HasTags;
use App\Models\Document\Document;
use App\Models\Ideas\IdeaMap;
use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Learning\{LearningCourse, LearningPath};
use App\Models\Platform\User;
use App\Services\Content\ContentSubjectResolver;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\Gate;

/**
 * Die eine Stelle, die festlegt, was in eine Sammlung darf (MVP-809,
 * Feature 155) — und wer es darin sieht.
 *
 * Eine Sammlung verleiht keinen Zugriff. Jeder Typ bringt deshalb sein
 * eigenes Tor mit: das Tarifmodul, das Recht auf die Liste, den
 * Sichtbarkeits-Scope und zuletzt die Policy am einzelnen Inhalt. Einheiten
 * und Abschnitte von Kursen stehen bewusst nicht in der Liste — sie hängen am
 * Kurs, eine Sammlung von Bausteinen ordnet nichts.
 */
final class CollectableTypes {
    /**
     * `list` ist die Typansicht im Wissensbereich (MVP-821): ihre Route und die
     * Filter, die sie auswertet. `null` für Lerninhalte — sie haben ihre eigene
     * Sektion und stehen nicht in der Typleiste.
     *
     * @var array<string, array{class: class-string<Model>, module: string|null, icon: string, list: array{route: string, filters: list<string>}|null}>
     */
    public const TYPES = [
        'note' => ['class' => CommunicationNote::class, 'module' => null, 'icon' => 'sticky_note_2', 'list' => ['route' => 'communication-notes.index', 'filters' => ['q', 'tag', 'customer']]],
        'idea_map' => ['class' => IdeaMap::class, 'module' => 'module.ideas', 'icon' => 'emoji_objects', 'list' => ['route' => 'ideas.index', 'filters' => []]],
        'knowledge_article' => ['class' => KnowledgeArticle::class, 'module' => 'module.knowledge', 'icon' => 'school', 'list' => ['route' => 'knowledge.index', 'filters' => ['q', 'tag', 'collection']]],
        'document' => ['class' => Document::class, 'module' => 'module.documents', 'icon' => 'description', 'list' => ['route' => 'documents.index', 'filters' => ['q', 'tag', 'customer']]],
        'learning_course' => ['class' => LearningCourse::class, 'module' => 'module.lms', 'icon' => 'cast_for_education', 'list' => null],
        'learning_path' => ['class' => LearningPath::class, 'module' => 'module.lms', 'icon' => 'route', 'list' => null],
    ];

    /**
     * Die Achsen, die überall dasselbe bedeuten und deshalb beim Wechsel der
     * Ansicht erhalten bleiben — der Einstieg kennt sie alle.
     *
     * `type` gehört ausdrücklich **nicht** dazu: im Einstieg ist es der
     * Inhaltstyp, in der Dokumentenliste die Dokumentart. Mitgeschleppt würde
     * es dort das Falsche filtern und den Einstieg beim Rückweg in einen
     * Validierungsfehler laufen lassen. Ebenso bleiben Sortierung, Status und
     * Darstellung bei ihrer Liste.
     *
     * @var list<string>
     */
    public const SHARED_FILTERS = ['q', 'tag', 'collection', 'customer'];

    public function __construct(
        private readonly FeatureFlagResolver $modules,
        private readonly ContentSubjectResolver $subjects,
    ) {}

    /** @return list<string> */
    public function keys(): array {
        return array_keys(self::TYPES);
    }

    public function keyFor(Model $model): ?string {
        foreach (self::TYPES as $key => $type) {
            if ($model instanceof $type['class']) {
                return $key;
            }
        }

        return null;
    }

    public function keyForMorphType(string $morphType): ?string {
        foreach (self::TYPES as $key => $type) {
            if ((new $type['class'])->getMorphClass() === $morphType) {
                return $key;
            }
        }

        return null;
    }

    /** @return class-string<Model>|null */
    public function classFor(string $key): ?string {
        return self::TYPES[$key]['class'] ?? null;
    }

    public function icon(string $key): string {
        return self::TYPES[$key]['icon'] ?? 'article';
    }

    /** Route der Typansicht — `null`, wo der Typ keine eigene Liste im Wissensbereich hat. */
    public function listRoute(string $key): ?string {
        return self::TYPES[$key]['list']['route'] ?? null;
    }

    /**
     * Filter, die diese Typansicht auswertet.
     *
     * @return list<string>
     */
    public function listFilters(string $key): array {
        return self::TYPES[$key]['list']['filters'] ?? [];
    }

    /**
     * Die aktiven Filter auf das eindampfen, was das Ziel auch anwendet. Ein
     * Filter, den die Zielliste nicht kennt, bliebe sonst in der Adresse
     * stehen, ohne zu wirken — die Liste zeigte mehr, als ihre Filterleiste
     * behauptet.
     *
     * @param  list<string>  $filters
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public function carry(array $filters, array $query): array {
        $carried = [];
        foreach ($filters as $name) {
            $value = $query[$name] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $carried[$name] = trim($value);
            }
        }

        return $carried;
    }

    public function label(string $key): string {
        return (string) __('collections.type.' . $key);
    }

    /** Spalte mit dem Anzeigetitel (Notizen haben einen Betreff). */
    public function titleColumn(string $key): string {
        return $key === 'note' ? 'subject' : 'title';
    }

    public function hasTags(string $key): bool {
        $class = $this->classFor($key);

        return $class !== null && in_array(HasTags::class, class_uses_recursive($class), true);
    }

    /**
     * Typen, die die Person sehen darf (Tarif und Listenrecht).
     *
     * @return list<string>
     */
    public function availableKeys(User $user): array {
        return array_values(array_filter($this->keys(), fn (string $key): bool => $this->typeAvailableTo($key, $user)));
    }

    /** Tarif und Listenrecht: darf die Person diesen Typ überhaupt sehen? */
    public function typeAvailableTo(string $key, User $user): bool {
        $module = self::TYPES[$key]['module'] ?? null;
        if (! array_key_exists($key, self::TYPES) || ($module !== null && ! $this->modules->isEnabled($module))) {
            return false;
        }

        return match ($key) {
            'note' => Gate::forUser($user)->allows('viewAny', CommunicationNote::class),
            'idea_map' => Gate::forUser($user)->allows('viewAny', IdeaMap::class),
            'knowledge_article' => Gate::forUser($user)->allows('viewAny', KnowledgeArticle::class),
            'document' => Gate::forUser($user)->allows('viewAny', Document::class),
            'learning_course' => Gate::forUser($user)->allows('viewAny', LearningCourse::class),
            // Lernpfade haben keine Policy; die Verwaltung hängt am Verwaltungsrecht.
            default => Gate::forUser($user)->allows(Permission::LearningManage->value),
        };
    }

    /**
     * Die für die Person sichtbaren Inhalte eines Typs.
     *
     * @param  list<int>  $ids
     * @return list<Model>
     */
    public function visible(string $key, User $user, array $ids): array {
        if ($ids === []) {
            return [];
        }

        $query = $this->scopedQuery($key, $user);
        if ($query === null) {
            return [];
        }
        // Bezugskette gleich mitladen (MVP-818) — die Listen zeigen sie, und
        // ohne das liefe jede Zeile in eine eigene Abfrage.
        $query->with($this->subjects->eagerLoad($query->getModel()::class));

        return $this->authorized($key, $user, $query->whereKey($ids)->get()->all());
    }

    /**
     * Die Policy am einzelnen Inhalt hat das letzte Wort (vertrauliche
     * Dokumente, geteilte Ideenkarten, Trainer-Bindung bei Kursen).
     *
     * @param  array<int, Model>  $models
     * @return list<Model>
     */
    public function authorized(string $key, User $user, array $models): array {
        return array_values(array_filter(
            $models,
            static fn (Model $model): bool => $key === 'learning_path' || Gate::forUser($user)->allows('view', $model),
        ));
    }

    public function isVisibleTo(Model $model, User $user): bool {
        $key = $this->keyFor($model);

        return $key !== null && $this->visible($key, $user, [(int) $model->getKey()]) !== [];
    }

    public function title(Model $model): string {
        return trim((string) ($model instanceof CommunicationNote ? $model->subject : $model->getAttribute('title')));
    }

    public function url(Model $model): string {
        $sqid = (string) Sqid::encode($model::class, (int) $model->getKey());

        return match (true) {
            // Notizen öffnen ihren Lesedialog in der zentralen Liste (wie aus der Recherche).
            $model instanceof CommunicationNote => route('communication-notes.index', ['note' => $sqid]),
            $model instanceof IdeaMap => route('ideas.show', $sqid),
            $model instanceof KnowledgeArticle => route('knowledge.show', $sqid),
            $model instanceof Document => route('documents.show', $sqid),
            $model instanceof LearningCourse => route('learning.courses.show', $sqid),
            $model instanceof LearningPath => route('learning.paths.show', $sqid),
            default => '#',
        };
    }

    /**
     * Sichtbarkeits-Scope je Typ — dieselben Regeln wie in den jeweiligen
     * Listen; `null`, wenn Tarif oder Listenrecht fehlen. Die Policy am
     * einzelnen Inhalt prüft danach {@see authorized()}.
     *
     * @return Builder<CommunicationNote>|Builder<IdeaMap>|Builder<Document>|Builder<LearningCourse>|Builder<LearningPath>|Builder<KnowledgeArticle>|null
     */
    public function scopedQuery(string $key, User $user): ?Builder {
        if (! $this->typeAvailableTo($key, $user)) {
            return null;
        }

        return match ($key) {
            'note' => CommunicationNote::query()->visibleTo($user),
            'idea_map' => IdeaMap::query()->visibleTo($user),
            'document' => Document::query()->visibleTo($user),
            'learning_course' => LearningCourse::query()->visibleTo($user),
            'learning_path' => LearningPath::query(),
            'knowledge_article' => KnowledgeArticle::query()
                ->when(
                    // Wie in der Artikelliste: Entwürfe anderer nur mit Freigaberecht.
                    ! $user->isAdmin() && ! $user->can(Permission::KnowledgePublish->value),
                    static fn (Builder $q) => $q->where(static fn (Builder $w) => $w
                        ->where('status', ArticleStatus::Published->value)
                        ->orWhere('created_by_user_id', $user->id)),
                ),
            default => null,
        };
    }
}
