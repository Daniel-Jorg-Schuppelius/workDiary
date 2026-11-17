<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactProjectionWriteRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Models\Customer\Customer;
use App\Services\Stammdaten\ContactDetailsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\WithOrganization;
use Tests\Support\ContactProjectionGuard;
use Tests\TestCase;

/**
 * Gate MVP-1700: Rechnungsanschrift, Land und Bankverbindung von Kunde und
 * Lieferant schreibt nur der Satellit (`ContactDetailsWriter`), die Spalten
 * `address_*`, `country`, `bank_*` zieht der Observer still nach. Tests\TestCase
 * bricht jeden Speichervorgang ab, der die Projektion aus app/ heraus ändert;
 * Tests, Factories und Seeder dürfen Altbestand ohne Satelliten anlegen.
 */
final class ContactProjectionWriteRuleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string ROOT = '/srv/app';

    public function test_a_write_from_app_is_reported_with_its_origin(): void {
        $trace = [
            ['file' => self::ROOT . '/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php', 'line' => 10],
            ['file' => self::ROOT . '/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php', 'line' => 20],
            ['file' => self::ROOT . '/app/Http/Controllers/Api/CustomerController.php', 'line' => 97],
            ['file' => self::ROOT . '/tests/Feature/Api/SomeTest.php', 'line' => 30],
        ];

        $this->assertSame('app/Http/Controllers/Api/CustomerController.php:97', ContactProjectionGuard::offendingOrigin($trace, self::ROOT));
    }

    public function test_tests_factories_seeders_and_demo_data_may_write_the_projection(): void {
        $vendor = ['file' => self::ROOT . '/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php', 'line' => 20];

        foreach (['tests/Feature/Api/SomeTest.php', 'database/factories/Customer/CustomerFactory.php', 'database/seeders/DemoSeeder.php', 'app/Services/Demo/DemoSeederService.php', 'app/Services/Club/Demo/ClubDemoSeeder.php'] as $file) {
            $trace = [$vendor, ['file' => self::ROOT . '/' . $file, 'line' => 1]];
            $this->assertNull(ContactProjectionGuard::offendingOrigin($trace, self::ROOT), $file);
        }
    }

    /** Die Ausnahmen treffen echte Dateien, sonst wären sie Ballast. */
    public function test_every_allowed_pattern_matches_a_file(): void {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = 'app/' . str_replace('\\', '/', substr((string) $file->getPathname(), strlen(base_path('app')) + 1));
        }

        foreach (ContactProjectionGuard::ALLOWED as $pattern => $reason) {
            $this->assertNotSame([], preg_grep($pattern, $files), "Ausnahme {$pattern} trifft keine Datei mehr.");
            $this->assertGreaterThan(20, mb_strlen($reason));
        }
    }

    public function test_the_guard_stops_a_direct_write_from_app(): void {
        $this->setUpOrganization();
        $customer = Customer::query()->create(['organization_id' => $this->organization->id, 'name' => 'Projektion GmbH', 'currency' => 'EUR']);
        $customer->address_city = 'Musterstadt';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/app\/Http\/Controllers\/Api\/CustomerController\.php:97.*address_city/');

        ContactProjectionGuard::check($customer, base_path(), [['file' => base_path('app/Http/Controllers/Api/CustomerController.php'), 'line' => 97]]);
    }

    /**
     * Gegenprobe zum Observer: der Schreibweg über den Satelliten läuft durch
     * app/ und ändert die Projektion trotzdem ohne Abbruch, weil der Observer
     * still speichert.
     */
    public function test_the_satellite_write_path_passes_the_guard(): void {
        $this->setUpOrganization();
        $customer = Customer::query()->create(['organization_id' => $this->organization->id, 'name' => 'Satellit GmbH', 'currency' => 'EUR']);

        app(ContactDetailsWriter::class)->writeInline($customer, ['address_city' => 'Musterstadt', 'bank_iban' => 'DE89370400440532013000']);

        $customer->refresh();
        $this->assertSame('Musterstadt', $customer->address_city);
        $this->assertSame('DE89370400440532013000', $customer->bank_iban);
        $this->assertSame([], ContactProjectionGuard::dirtyProjection($customer));
    }
}
