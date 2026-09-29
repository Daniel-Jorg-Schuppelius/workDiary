<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_215000_neutral_article_refs_in_reselling.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1025: Abos verweisen über den Katalogschlüssel (`art:<id>`, `lex:<id>`)
 * statt über `article_id` + `lexoffice_article_id`; die Abo-Einstufung eines
 * Artikels liegt in einer eigenen Reselling-Tabelle statt als Spalte im
 * Artikelstamm und in der Lexoffice-Tabelle.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_article_classifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('article_ref', 80);
            $table->string('role', 16);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'article_ref'], 'resale_art_class_org_ref_uq');
        });

        Schema::table('resale_subscriptions', function (Blueprint $table): void {
            $table->string('article_ref', 80)->nullable();
            $table->index(['organization_id', 'article_ref'], 'resale_subs_org_article_ref_idx');
        });

        DB::table('resale_subscriptions')->where(static fn ($q) => $q->whereNotNull('article_id')->orWhereNotNull('lexoffice_article_id'))
            ->orderBy('id')->select(['id', 'article_id', 'lexoffice_article_id'])
            ->chunkById(500, static function ($rows): void {
                foreach ($rows as $row) {
                    $ref = $row->article_id !== null ? 'art:' . $row->article_id : 'lex:' . $row->lexoffice_article_id;
                    DB::table('resale_subscriptions')->where('id', $row->id)->update(['article_ref' => $ref]);
                }
            });

        $now = now();
        foreach (['articles' => 'art', 'lexoffice_articles' => 'lex'] as $table => $prefix) {
            DB::table($table)->whereNotNull('resale_role')->whereNotNull('organization_id')->orderBy('id')->select(['id', 'organization_id', 'resale_role'])
                ->chunkById(500, static function ($rows) use ($prefix, $now): void {
                    DB::table('resale_article_classifications')->insertOrIgnore($rows->map(static fn (object $row): array => [
                        'organization_id' => $row->organization_id,
                        'article_ref' => $prefix . ':' . $row->id,
                        'role' => $row->resale_role,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });
        }

        Schema::table('resale_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('article_id');
            $table->dropConstrainedForeignId('lexoffice_article_id');
        });
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('resale_role');
        });
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->dropColumn('resale_role');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->string('resale_role', 16)->nullable();
        });
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('resale_role', 16)->nullable();
        });
        Schema::table('resale_subscriptions', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('lexoffice_article_id')->nullable()->constrained('lexoffice_articles')->nullOnDelete();
        });

        foreach (DB::table('resale_subscriptions')->whereNotNull('article_ref')->get(['id', 'article_ref']) as $row) {
            [$prefix, $id] = explode(':', (string) $row->article_ref, 2) + [1 => null];
            $column = ['art' => 'article_id', 'lex' => 'lexoffice_article_id'][$prefix] ?? null;
            if ($column !== null) {
                DB::table('resale_subscriptions')->where('id', $row->id)->update([$column => (int) $id]);
            }
        }
        foreach (DB::table('resale_article_classifications')->get(['article_ref', 'role']) as $row) {
            [$prefix, $id] = explode(':', (string) $row->article_ref, 2) + [1 => null];
            $table = ['art' => 'articles', 'lex' => 'lexoffice_articles'][$prefix] ?? null;
            if ($table !== null) {
                DB::table($table)->where('id', (int) $id)->update(['resale_role' => $row->role]);
            }
        }

        Schema::table('resale_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('resale_subs_org_article_ref_idx');
            $table->dropColumn('article_ref');
        });
        Schema::dropIfExists('resale_article_classifications');
    }
};
