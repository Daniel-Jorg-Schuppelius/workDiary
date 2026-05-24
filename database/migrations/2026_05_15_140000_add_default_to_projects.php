<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_15_140000_add_default_to_projects.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

/**
 * Fügt jedem Projekt das Flag `is_default` hinzu und legt für bestehende
 * Kunden ohne Standardprojekt automatisch eines an. Pro Kunde gibt es
 * höchstens ein `is_default = true`-Projekt (Konsistenz wird im Model
 * erzwungen, weil partial unique indexes nicht überall verfügbar sind).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('status');
            $table->index(['customer_id', 'is_default']);
        });

        $this->backfillDefaults();
    }

    public function down(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }

    private function backfillDefaults(): void {
        $name = (string) config('project.default_project.name', 'Wartung');
        $color = (string) config('project.default_project.color', '#64748b');
        $billable = (bool) config('project.default_project.billable', true);
        $now = now();

        $customers = DB::table('customers')->select(['id', 'organization_id'])->get();

        foreach ($customers as $customer) {
            $hasDefault = DB::table('projects')
                ->where('customer_id', $customer->id)
                ->where('is_default', true)
                ->exists();
            if ($hasDefault) {
                continue;
            }

            $slug = $this->uniqueSlug($name . '-' . $customer->id);

            DB::table('projects')->insert([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'name' => $name,
                'slug' => $slug,
                'color' => $color,
                'status' => 'active',
                'is_default' => true,
                'billable' => $billable,
                'time_budget' => 0,
                'budget' => 0,
                'global_activities' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function uniqueSlug(string $base): string {
        $slug = Str::slug($base) ?: 'wartung';
        $candidate = $slug;
        $i = 2;
        while (DB::table('projects')->where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $i++;
        }

        return $candidate;
    }
};
