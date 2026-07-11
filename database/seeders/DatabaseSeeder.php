<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatabaseSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\{Organization, User};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder {
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void {
        $this->call(OrganizationSeeder::class);
        $this->call(PermissionsSeeder::class);
        $this->call(MaterialSeeder::class);
        $this->call(ActivityCategorySeeder::class);
        $this->call(EntryTypeSeeder::class);
        $this->call(ExpenseCategorySeeder::class);
        $this->call(ClassificationSeeder::class);
        $this->call(PerDiemRateSeeder::class);
        $this->call(PerDiemForeignRateSeeder::class);
        $this->call(InvoiceMailTemplateSeeder::class);
        $this->call(CrisisDeadlineTemplatesSeeder::class); // Feature 070 (D9): globale Meldefristen-Defaults
        $this->call(SustainabilityDefaultsSeeder::class); // Feature 071 (D8): Faktoren-Set + VSME-1.0-Matrix
        $this->call(TaxRulesSeeder::class); // Phase 23 (MVP-238): versionierter Steuerkatalog (DE voll, AT/CH)
        $this->call(AssetComplianceCatalogSeeder::class); // Feature 075 (P1): Prüfprofil-Vorlagen + Normen-Referenzmatrix

        // Demo-/Test-Benutzer werden ausschließlich in lokalen bzw. Test-Umgebungen
        // angelegt. In Produktion würde dies sonst Faker (Dev-Dependency) benötigen
        // und unsichere Standard-Accounts erzeugen.
        if (app()->environment('local', 'testing')) {
            $this->seedDemoUsers();
        }
    }

    /**
     * Legt Demo-/Test-Benutzer an (nur für lokale und Test-Umgebungen).
     */
    private function seedDemoUsers(): void {
        $org = Organization::where('slug', 'default')->first();

        User::factory()->platformAdmin()->create([
            'name' => 'Administrator',
            'email' => 'admin@workdiary.local',
            'password' => Hash::make('admin'),
            'organization_id' => $org?->id,
        ]);

        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'organization_id' => $org?->id,
        ]);
    }
}
