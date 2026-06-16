<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacturationTargetRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\TransferTarget;
use App\Services\Finance\Targets\{FacturationTargetRegistry, FileTarget, LexofficeTarget};
use Tests\TestCase;

/**
 * Adapter-Auflösung der Faktura-Übergabe (Feature 045, Teil B): `lexoffice`
 * → {@see LexofficeTarget}, `datev`/`file` laufen bewusst über den
 * {@see FileTarget} (CSV-Übergabepaket, bis der DATEV-Desktop-Adapter folgt).
 */
final class FacturationTargetRegistryTest extends TestCase {
    private FacturationTargetRegistry $registry;

    protected function setUp(): void {
        parent::setUp();
        $this->registry = app(FacturationTargetRegistry::class);
    }

    public function test_resolves_lexoffice_target(): void {
        $this->assertInstanceOf(
            LexofficeTarget::class,
            $this->registry->for(TransferTarget::Lexoffice),
        );
    }

    public function test_datev_and_file_resolve_to_file_target(): void {
        // `datev` läuft bis zum Desktop-Adapter bewusst über den FileTarget.
        $this->assertInstanceOf(FileTarget::class, $this->registry->for(TransferTarget::Datev));
        $this->assertInstanceOf(FileTarget::class, $this->registry->for(TransferTarget::File));
    }

    public function test_each_target_supports_only_its_own_channels(): void {
        $lexoffice = app(LexofficeTarget::class);
        $file = app(FileTarget::class);

        $this->assertTrue($lexoffice->supports(TransferTarget::Lexoffice));
        $this->assertFalse($lexoffice->supports(TransferTarget::Datev));
        $this->assertFalse($lexoffice->supports(TransferTarget::File));

        $this->assertTrue($file->supports(TransferTarget::Datev));
        $this->assertTrue($file->supports(TransferTarget::File));
        $this->assertFalse($file->supports(TransferTarget::Lexoffice));
    }
}
