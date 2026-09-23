<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100600_move_knowledge_categories_to_collections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Freitext-Kategorie der Wissensartikel auflösen (MVP-814, Feature 155).
 *
 * Jeder vorhandene Kategoriewert wird eine Sammlung der obersten Ebene in
 * seiner Organisation (Schreibweisen, die sich nur in Groß-/Kleinschreibung
 * unterscheiden, landen in einer), der Artikel liegt danach darin. Eine schon
 * vorhandene gleichnamige Sammlung wird mitbenutzt. Der Helpdesk-Wert
 * `known_error` heißt als Sammlung „Known Errors“. Danach entfällt die Spalte:
 * Sammlung ordnet die Struktur, Schlagwort die Querachse.
 */
return new class extends Migration {
    private const ARTICLE_MORPH = 'App\Models\Knowledge\KnowledgeArticle';

    public function up(): void {
        if (! Schema::hasColumn('knowledge_articles', 'category')) {
            return;
        }

        $now = now();
        $collections = [];
        $articles = DB::table('knowledge_articles')
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->orderBy('id')
            ->get(['id', 'organization_id', 'category']);

        foreach ($articles as $article) {
            $title = trim((string) $article->category);
            if ($title === '') {
                continue;
            }
            $title = $title === 'known_error' ? 'Known Errors' : mb_substr($title, 0, 180);
            $organizationId = (int) $article->organization_id;
            $key = $organizationId . '|' . mb_strtolower($title);

            if (! isset($collections[$key])) {
                $existing = DB::table('collections')
                    ->where('organization_id', $organizationId)
                    ->whereNull('parent_id')
                    ->whereNull('archived_at')
                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                    ->value('id');
                $collections[$key] = $existing !== null ? (int) $existing : (int) DB::table('collections')->insertGetId([
                    'organization_id' => $organizationId,
                    'parent_id' => null,
                    'title' => $title,
                    'visibility' => 'organization',
                    'position' => (int) DB::table('collections')->where('organization_id', $organizationId)->whereNull('parent_id')->max('position') + 1,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $collectionId = $collections[$key];
            $exists = DB::table('collection_items')
                ->where('collection_id', $collectionId)
                ->where('collectable_type', self::ARTICLE_MORPH)
                ->where('collectable_id', $article->id)
                ->exists();
            if (! $exists) {
                DB::table('collection_items')->insert([
                    'organization_id' => $organizationId,
                    'collection_id' => $collectionId,
                    'collectable_type' => self::ARTICLE_MORPH,
                    'collectable_id' => $article->id,
                    'position' => (int) DB::table('collection_items')->where('collection_id', $collectionId)->max('position') + 1,
                    'added_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->dropIndex('knowledge_org_category_idx');
        });
        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }

    /** Stellt die Spalte wieder her; die Werte bleiben in den Sammlungen. */
    public function down(): void {
        if (Schema::hasColumn('knowledge_articles', 'category')) {
            return;
        }

        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->string('category', 80)->nullable()->after('solution');
            $table->index(['organization_id', 'category'], 'knowledge_org_category_idx');
        });
    }
};
