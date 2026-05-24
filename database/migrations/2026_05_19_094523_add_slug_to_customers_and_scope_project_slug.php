<?php
/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_19_094523_add_slug_to_customers_and_scope_project_slug.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

/**
 * Bringt Kunden einen eindeutigen Slug pro Organisation und stellt
 * Projekt-Slugs auf Eindeutigkeit pro Kunde um, damit gleichnamige
 * Projekte für unterschiedliche Kunden möglich sind. Die zusammen-
 * gesetzte URL "<kunde>/<projekt>" bleibt damit eindeutig.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });

        $this->backfillCustomerSlugs();

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['organization_id', 'slug'], 'customers_org_slug_unique');
        });

        // Globalen Unique-Index auf projects.slug aufheben (wird per-Kunde
        // unique) und durch zusammengesetzten Index ersetzen.
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique('projects_slug_unique');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unique(['customer_id', 'slug'], 'projects_customer_slug_unique');
        });
    }

    public function down(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique('projects_customer_slug_unique');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unique('slug', 'projects_slug_unique');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_org_slug_unique');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }

    private function backfillCustomerSlugs(): void {
        $customers = DB::table('customers')->select(['id', 'organization_id', 'name', 'slug'])->get();

        foreach ($customers as $customer) {
            if (! empty($customer->slug)) {
                continue;
            }

            $base = Str::slug((string) $customer->name) ?: 'kunde-' . $customer->id;
            $slug = $base;
            $i = 2;
            while (
                DB::table('customers')
                ->where('organization_id', $customer->organization_id)
                ->where('slug', $slug)
                ->where('id', '!=', $customer->id)
                ->exists()
            ) {
                $slug = $base . '-' . $i++;
            }

            DB::table('customers')->where('id', $customer->id)->update(['slug' => $slug]);
        }
    }
};
