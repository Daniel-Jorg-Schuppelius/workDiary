<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormFieldsGoldenTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kompakte Kernformulare (Feature 165, MVP-1193, E4): Der Umbau in
 * aufklappbare Gruppen darf die gesendeten Felder nicht verändern. Die
 * Fixture wurde VOR dem Umbau aufgenommen; jede Abweichung ist zu begründen.
 */
class FormFieldsGoldenTest extends TestCase {
    use RefreshDatabase;

    private const FIXTURE = 'tests/Fixtures/forms/field-names.json';

    public function test_form_field_names_match_the_golden_fixture(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id, 'name' => 'Golden Kunde']);
        $this->be($admin);
        app()->instance('currentOrganization', $org);

        $snapshot = [
            'customers.create' => $this->fieldNames(route('customers.create', ['dialog' => 1])),
            'customers.edit' => $this->fieldNames(route('customers.edit', [$customer, 'dialog' => 1])),
            'diary.create' => $this->fieldNames(route('diary.create', ['dialog' => 1])),
        ];

        $path = base_path(self::FIXTURE);
        if (! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $this->assertFileExists($path);

            return;
        }

        /** @var array<string, list<string>> $golden */
        $golden = json_decode((string) file_get_contents($path), true);
        foreach ($snapshot as $form => $names) {
            $this->assertSame($golden[$form] ?? [], $names, "Formular {$form} sendet andere Felder als eingefroren.");
        }
    }

    /** @return list<string> */
    private function fieldNames(string $url): array {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('/\sname="([^"]+)"/', $html, $m);
        $names = array_values(array_unique(array_filter($m[1], static fn (string $n): bool => ! in_array($n, ['_token', '_method', '_dialog_url'], true))));
        sort($names);

        return $names;
    }
}
