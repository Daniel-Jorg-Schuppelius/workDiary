<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Knowledge;

use App\Enums\Knowledge\{ArticleStatus, ArticleVisibility};
use App\Models\{KnowledgeArticle, KnowledgeArticleFeedback, KnowledgeArticleLink, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\DB;

/**
 * Domain-Service Wissensbasis & Problemhistorie (Feature 011).
 *
 * Artikel-Lebenszyklus: draft → published → archived. Audit läuft über
 * den Auditable-Trait (created/updated/deleted) plus gezielte
 * audit()-Events für publish/archive/link/feedback. Eine eigene
 * Event-Tabelle gibt es bewusst nicht — der Lebenszyklus ist trivial.
 *
 * Feedback: genau eine Wertung pro User (Unique-Index); die Zähler
 * helpful_count/not_helpful_count sind denormalisiert und werden hier
 * in der Transaktion nachgeführt.
 */
class KnowledgeArticleService {
    /**
     * Legt einen Artikel an (Status default draft, Sichtbarkeit MVP-fest
     * `internal`). Tags laufen über die bestehende polymorphe Tag-Mechanik.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): KnowledgeArticle {
        $article = DB::transaction(function () use ($creator, $attributes): KnowledgeArticle {
            $article = KnowledgeArticle::query()->create([
                'organization_id' => $creator->organization_id,
                'title' => $attributes['title'],
                'slug' => KnowledgeArticle::uniqueSlug((string) $attributes['title']),
                'problem' => $attributes['problem'],
                'solution' => $attributes['solution'],
                'category' => $this->normalizeCategory($attributes['category'] ?? null),
                'status' => ArticleStatus::Draft->value,
                'visibility' => ArticleVisibility::Internal->value,
                'created_by_user_id' => $creator->id,
            ]);

            $this->syncTags($article, $attributes);

            return $article;
        });

        // Telemetry-Light (Feature 036): aggregierter Org-Tageszähler, fire-and-forget.
        app(\App\Services\Metrics\OperationsMetricsService::class)->increment('knowledge.created', (int) $article->organization_id);

        return $article;
    }

    /**
     * Aktualisiert Titel/Problem/Lösung/Kategorie/Tags. Der Slug bleibt
     * stabil (Verlinkungen/Lesezeichen), `updated`-Diff kommt automatisch
     * über den Auditable-Trait.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(KnowledgeArticle $article, User $actor, array $attributes): KnowledgeArticle {
        return DB::transaction(function () use ($article, $actor, $attributes): KnowledgeArticle {
            unset($actor);

            $article->update([
                'title' => $attributes['title'] ?? $article->title,
                'problem' => $attributes['problem'] ?? $article->problem,
                'solution' => $attributes['solution'] ?? $article->solution,
                'category' => array_key_exists('category', $attributes)
                    ? $this->normalizeCategory($attributes['category'])
                    : $article->category,
            ]);

            if (array_key_exists('tags', $attributes)) {
                $this->syncTags($article, $attributes);
            }

            return $article;
        });
    }

    /** Veröffentlicht den Artikel (setzt published_at beim ersten Mal). */
    public function publish(KnowledgeArticle $article, User $actor): KnowledgeArticle {
        if ($article->status === ArticleStatus::Published) {
            return $article;
        }

        return DB::transaction(function () use ($article, $actor): KnowledgeArticle {
            $article->update([
                'status' => ArticleStatus::Published->value,
                'published_at' => $article->published_at ?? now(),
            ]);
            $article->audit('knowledge.published', ['actor_user_id' => $actor->id]);

            return $article;
        });
    }

    /** Archiviert (Artikel bleibt einsehbar, fällt aus Suche/Vorschlägen). */
    public function archive(KnowledgeArticle $article, User $actor): KnowledgeArticle {
        if ($article->status === ArticleStatus::Archived) {
            return $article;
        }

        return DB::transaction(function () use ($article, $actor): KnowledgeArticle {
            $article->update(['status' => ArticleStatus::Archived->value]);
            $article->audit('knowledge.archived', ['actor_user_id' => $actor->id]);

            return $article;
        });
    }

    /** Soft-Delete (nur Admin, siehe Policy). */
    public function delete(KnowledgeArticle $article, User $actor): void {
        DB::transaction(function () use ($article, $actor): void {
            // Fachliches Event VOR dem Delete, damit es gemeinsam mit dem
            // Auditable-`deleted` in der Hash-Kette landet.
            $article->audit('knowledge.deleted', ['actor_user_id' => $actor->id]);
            $article->delete();
        });
    }

    /**
     * Wertung „Hat geholfen / Hat nicht geholfen". Genau eine Wertung pro
     * User; eine erneute Wertung mit anderem Ergebnis wechselt die Stimme,
     * mit gleichem Ergebnis ist sie ein No-op. Zähler werden denormalisiert
     * aus der Pivot-Tabelle neu gezählt (immer konsistent).
     */
    public function feedback(KnowledgeArticle $article, User $user, bool $helpful): KnowledgeArticle {
        return DB::transaction(function () use ($article, $user, $helpful): KnowledgeArticle {
            /** @var KnowledgeArticleFeedback|null $existing */
            $existing = $article->feedback()->where('user_id', $user->id)->first();

            if ($existing !== null && (bool) $existing->helpful === $helpful) {
                return $article;
            }

            if ($existing !== null) {
                $existing->update(['helpful' => $helpful]);
            } else {
                $article->feedback()->create([
                    'user_id' => $user->id,
                    'helpful' => $helpful,
                ]);
            }

            $article->forceFill([
                'helpful_count' => $article->feedback()->where('helpful', true)->count(),
                'not_helpful_count' => $article->feedback()->where('helpful', false)->count(),
            ])->save();

            return $article->refresh();
        });
    }

    /**
     * Verknüpft den Artikel mit einem Auftrag/Asset/Kunden/Protokoll
     * („hat hier geholfen"). Idempotent (Unique-Index knowledge_link_uq).
     */
    public function linkTo(KnowledgeArticle $article, Model $subject, User $actor): KnowledgeArticleLink {
        return DB::transaction(function () use ($article, $subject, $actor): KnowledgeArticleLink {
            /** @var KnowledgeArticleLink|null $existing */
            $existing = $article->links()
                ->where('linkable_type', $subject->getMorphClass())
                ->where('linkable_id', $subject->getKey())
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            /** @var KnowledgeArticleLink $link */
            $link = $article->links()->create([
                'linkable_type' => $subject->getMorphClass(),
                'linkable_id' => $subject->getKey(),
                'created_by_user_id' => $actor->id,
            ]);

            $article->audit('knowledge.linked', [
                'actor_user_id' => $actor->id,
                'linkable_type' => $subject->getMorphClass(),
                'linkable_id' => $subject->getKey(),
            ]);

            return $link;
        });
    }

    /** Löst eine Verknüpfung wieder. */
    public function unlink(KnowledgeArticleLink $link, User $actor): void {
        DB::transaction(function () use ($link, $actor): void {
            /** @var KnowledgeArticle $article */
            $article = $link->article()->firstOrFail();
            $article->audit('knowledge.unlinked', [
                'actor_user_id' => $actor->id,
                'linkable_type' => $link->linkable_type,
                'linkable_id' => $link->linkable_id,
            ]);
            $link->delete();
        });
    }

    /**
     * Einfache Kontext-Vorschläge (Problemhistorie, Feature 011 MVP):
     * veröffentlichte Artikel, deren Titel/Problem Wörter aus dem
     * Kontext-Text enthalten ODER die mindestens einen Tag mit dem
     * Subjekt teilen. Bewusst LIKE-Scoring im PHP-Nachgang — KEINE
     * Volltext-Engine (Out-of-Scope).
     *
     * @param  Model  $subject  DiaryEntry/Asset etc. (für Tag-Abgleich + Ausschluss bereits verknüpfter)
     * @param  list<string>  $texts  Kontext-Texte (z. B. Auftragstitel + Beschreibung)
     * @return Collection<int, KnowledgeArticle>
     */
    public function suggestFor(Model $subject, array $texts, int $limit = 5): Collection {
        $words = $this->significantWords($texts);

        $tagIds = method_exists($subject, 'tags')
            ? $subject->tags()->pluck('tags.id')->all()
            : [];

        if ($words === [] && $tagIds === []) {
            return new Collection();
        }

        $query = KnowledgeArticle::query()
            ->published()
            // Bereits verknüpfte Artikel sind keine „Vorschläge" mehr.
            ->whereDoesntHave('links', function (Builder $q) use ($subject): void {
                $q->where('linkable_type', $subject->getMorphClass())
                    ->where('linkable_id', $subject->getKey());
            })
            ->where(function (Builder $q) use ($words, $tagIds): void {
                foreach ($words as $word) {
                    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $word) . '%';
                    $q->orWhere('title', 'like', $like)->orWhere('problem', 'like', $like);
                }
                if ($tagIds !== []) {
                    $q->orWhereHas('tags', function (Builder $tq) use ($tagIds): void {
                        $tq->whereIn('tags.id', $tagIds);
                    });
                }
            })
            ->with('tags')
            ->limit(50);

        /** @var Collection<int, KnowledgeArticle> $candidates */
        $candidates = $query->get();

        return $candidates
            ->sortByDesc(fn(KnowledgeArticle $a): array => [
                $this->score($a, $words, $tagIds),
                $a->helpful_count,
            ])
            ->take($limit)
            ->values();
    }

    /**
     * Treffer-Score: Wortvorkommen in Titel (doppelt) + Problem + Tag-Übereinstimmungen.
     *
     * @param  list<string>  $words
     * @param  list<int>  $tagIds
     */
    private function score(KnowledgeArticle $article, array $words, array $tagIds): int {
        $score = 0;
        $title = mb_strtolower($article->title);
        $problem = mb_strtolower($article->problem);
        foreach ($words as $word) {
            $w = mb_strtolower($word);
            if (str_contains($title, $w)) {
                $score += 2;
            }
            if (str_contains($problem, $w)) {
                $score += 1;
            }
        }
        if ($tagIds !== []) {
            $score += $article->tags->whereIn('id', $tagIds)->count() * 2;
        }

        return $score;
    }

    /**
     * Zerlegt Kontext-Texte in signifikante Wörter (≥ 4 Zeichen, dedupliziert,
     * max. 8 — hält die LIKE-Query klein).
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    private function significantWords(array $texts): array {
        $words = [];
        foreach ($texts as $text) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                if (mb_strlen($word) >= 4) {
                    $words[mb_strtolower($word)] = $word;
                }
            }
        }

        return array_slice(array_values($words), 0, 8);
    }

    private function normalizeCategory(mixed $category): ?string {
        $category = trim((string) $category);

        return $category === '' ? null : Str::limit($category, 80, '');
    }

    /**
     * Tags aus Komma-getrenntem Eingabefeld über die bestehende
     * polymorphe Tag-Mechanik synchronisieren.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function syncTags(KnowledgeArticle $article, array $attributes): void {
        $names = array_values(array_filter(array_map(
            static fn($name): string => trim((string) $name),
            explode(',', (string) ($attributes['tags'] ?? '')),
        ), static fn(string $name): bool => $name !== ''));

        $article->syncTagsFromInput([], $names);
    }
}
