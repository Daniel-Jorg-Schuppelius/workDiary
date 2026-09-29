<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_216000_neutral_service_article_refs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1026: Standardleistung über den Katalogschlüssel (`art:<id>`, `lex:<id>`)
 * statt über die Lexoffice-UUID — an Projekt-Abrechnungsregeln, Übergabe-
 * Positionen und in der Organisationseinstellung. Die Plugin-Kennung der
 * Regeln entfällt: sie war immer `lexoffice`. Nicht auflösbare UUIDs (Artikel
 * nie synchronisiert) verlieren den Bezug.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('project_billing_rules', function (Blueprint $table): void {
            $table->string('article_ref', 80)->nullable();
        });
        Schema::table('billing_transfer_positions', function (Blueprint $table): void {
            $table->string('article_ref', 80)->nullable();
        });

        foreach (['project_billing_rules' => 'lexoffice_article_id', 'billing_transfer_positions' => 'article_id'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->select(['id', 'organization_id', $column])
                ->chunkById(500, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            'article_ref' => $this->refFor((int) $row->organization_id, (string) $row->{$column}),
                        ]);
                    }
                });
        }

        $this->rewriteSettings(fn (int $organizationId, string $uuid): ?string => $this->refFor($organizationId, $uuid));

        // Erst der neue Index: der Fremdschlüssel auf project_id braucht in MySQL stets einen.
        Schema::table('project_billing_rules', function (Blueprint $table): void {
            $table->index(['project_id', 'applies_to_kind'], 'pbr_proj_kind_idx');
        });
        Schema::table('project_billing_rules', function (Blueprint $table): void {
            $table->dropIndex('pbr_proj_plugin_kind_idx');
        });
        Schema::table('project_billing_rules', function (Blueprint $table): void {
            $table->dropColumn(['plugin_id', 'lexoffice_article_id']);
        });
        Schema::table('billing_transfer_positions', function (Blueprint $table): void {
            $table->dropColumn('article_id');
        });
    }

    public function down(): void {
        Schema::table('project_billing_rules', function (Blueprint $table): void {
            $table->string('plugin_id', 50)->default('lexoffice');
            $table->string('lexoffice_article_id')->nullable();
            $table->index(['project_id', 'plugin_id', 'applies_to_kind'], 'pbr_proj_plugin_kind_idx');
        });
        Schema::table('project_billing_rules', function (Blueprint $table): void {
            $table->dropIndex('pbr_proj_kind_idx');
        });
        Schema::table('billing_transfer_positions', function (Blueprint $table): void {
            $table->string('article_id', 64)->nullable();
        });

        foreach (['project_billing_rules' => 'lexoffice_article_id', 'billing_transfer_positions' => 'article_id'] as $table => $column) {
            DB::table($table)->whereNotNull('article_ref')->orderBy('id')->select(['id', 'organization_id', 'article_ref'])
                ->chunkById(500, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            $column => $this->uuidFor((int) $row->organization_id, (string) $row->article_ref),
                        ]);
                    }
                });
        }

        $this->rewriteSettings(fn (int $organizationId, string $ref): ?string => $this->uuidFor($organizationId, $ref));

        Schema::table('project_billing_rules', function (Blueprint $table): void {
            $table->dropColumn('article_ref');
        });
        Schema::table('billing_transfer_positions', function (Blueprint $table): void {
            $table->dropColumn('article_ref');
        });
    }

    /** Lexoffice-UUID → `lex:<id>`, sonst über die Artikelzuordnung → `art:<id>`. */
    private function refFor(int $organizationId, string $uuid): ?string {
        $lexId = DB::table('lexoffice_articles')->where('organization_id', $organizationId)->where('external_id', $uuid)->value('id');
        if ($lexId !== null) {
            return 'lex:' . $lexId;
        }
        $articleId = DB::table('external_article_mappings')->where('organization_id', $organizationId)
            ->where('plugin_id', 'lexoffice')->where('external_id', $uuid)->whereNotNull('article_id')->value('article_id');

        return $articleId !== null ? 'art:' . $articleId : null;
    }

    private function uuidFor(int $organizationId, string $ref): ?string {
        [$prefix, $id] = explode(':', $ref, 2) + [1 => '0'];
        $uuid = match ($prefix) {
            'lex' => DB::table('lexoffice_articles')->where('organization_id', $organizationId)->where('id', (int) $id)->value('external_id'),
            'art' => DB::table('external_article_mappings')->where('organization_id', $organizationId)
                ->where('plugin_id', 'lexoffice')->where('article_id', (int) $id)->orderBy('id')->value('external_id'),
            default => null,
        };

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    /** @param  \Closure(int, string): ?string  $convert */
    private function rewriteSettings(\Closure $convert): void {
        foreach (DB::table('organizations')->whereNotNull('settings')->get(['id', 'settings']) as $row) {
            $settings = json_decode((string) $row->settings, true);
            $value = is_array($settings) ? ($settings['invoicing']['default_service_article'] ?? null) : null;
            if (! is_string($value) || $value === '') {
                continue;
            }
            $converted = $convert((int) $row->id, $value);
            if ($converted === null) {
                unset($settings['invoicing']['default_service_article']);
            } else {
                $settings['invoicing']['default_service_article'] = $converted;
            }
            unset($settings['invoicing']['default_service_plugin']);
            if ($settings['invoicing'] === []) {
                unset($settings['invoicing']);
            }
            DB::table('organizations')->where('id', $row->id)->update(['settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);
        }
    }
};
