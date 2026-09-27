<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranslatedMasterDataTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification\{Classification, Tag};
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureTemplate;
use App\Services\Classification\BranchProfileInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-912: übersetzte Klassifikationen, Prozedurnamen und Tags. */
final class TranslatedMasterDataTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_every_branch_profile_ships_translations_for_all_domains_and_procedure_names(): void {
        $missing = [];
        foreach (glob(database_path('data/branchprofiles/*.php')) ?: [] as $file) {
            $code = basename($file, '.php');
            $profile = require $file;
            $sidecarFile = database_path("data/branchprofiles/i18n/{$code}.php");
            $sidecar = is_file($sidecarFile) ? require $sidecarFile : [];
            foreach ($profile['classifications'] ?? [] as $domain => $rows) {
                foreach ((array) $rows as $row) {
                    $i18n = $row['label_i18n'] ?? $sidecar[$domain][$row['code']] ?? [];
                    if (array_diff(['en', 'es', 'fr', 'it'], array_keys($i18n)) !== []) {
                        $missing[] = "$code/$domain/{$row['code']}";
                    }
                }
            }
            foreach ($profile['procedure_templates'] ?? [] as $template) {
                if (isset($template['name']) && array_diff(['en', 'es', 'fr', 'it'], array_keys($template['name_i18n'] ?? [])) !== []) {
                    $missing[] = "$code/procedure/{$template['code']}";
                }
            }
        }
        $this->assertSame([], $missing);
    }

    public function test_installed_profile_shows_translated_labels_and_names(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        app(BranchProfileInstaller::class)->install($this->organization, 'it', $admin);

        $activity = Classification::query()->where('organization_id', $this->organization->id)->where('domain', ClassificationDomain::Activity->value)->where('code', 'configure')->firstOrFail();
        $this->assertSame('Configurer', $activity->displayLabel('fr'));
        $this->assertSame('Konfigurieren', $activity->displayLabel('de'));

        $template = ProcedureTemplate::query()->where('code', 'IT_NETWORK_CHANGE')->firstOrFail();
        $this->assertSame('Critical network/configuration change', $template->displayName('en'));
        $template->forceFill(['name_i18n' => null])->save();
        app(BranchProfileInstaller::class)->install($this->organization, 'it', $admin);
        $this->assertSame('Cambio crítico de red/configuración', $template->fresh()->displayName('es'), 'fehlende Übersetzung wird nachgetragen');
    }

    public function test_admin_dialogs_store_translations(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $tag = Tag::create(['name' => 'Notdienst', 'organization_id' => $this->organization->id]);

        $this->actingAs($admin)->get(route('tags.edit', $tag))->assertOk()->assertSee('name="name_i18n[en]"', false);
        $this->actingAs($admin)->put(route('tags.update', $tag), ['name' => 'Notdienst', 'name_i18n' => ['en' => 'Emergency service', 'de' => 'ignoriert', 'xx' => 'unbekannt', 'fr' => '']])->assertRedirect();
        $this->assertSame(['en' => 'Emergency service'], $tag->fresh()->name_i18n);
        app()->setLocale('en');
        $this->assertSame('Emergency service', $tag->fresh()->displayName());
        app()->setLocale('de');

        $classification = Classification::factory()->forOrganization($this->organization->id)->domain(ClassificationDomain::Result)->create(['code' => 'eigen', 'label' => 'Erledigt']);
        $this->actingAs($admin)->put(route('admin.classifications.update', $classification), ['label' => 'Erledigt', 'label_i18n' => ['it' => 'Fatto'], 'sort_order' => 10, 'active' => '1'])->assertRedirect();
        $this->assertSame(['it' => 'Fatto'], $classification->fresh()->label_i18n);
    }
}
