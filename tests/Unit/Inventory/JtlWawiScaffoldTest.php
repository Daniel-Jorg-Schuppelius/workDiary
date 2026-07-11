<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiScaffoldTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Inventory;

use App\Enums\Inventory\{ProviderCapability, StockMovementType, StockState};
use App\Models\{ArticleVariant, Organization, Warehouse};
use App\Plugins\JtlWawi\Api\JtlGatewayFactory;
use App\Plugins\JtlWawi\Services\{JtlMappingResolver, JtlStockReader, JtlWawiInventoryProvider, JtlWawiOutboxDispatcher};
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryLedger, ReadOnlyInventoryProvider, StockPosting};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Vertragsfläche des JTL-Wawi-Plugins (Feature 078, MVP-319/321) — löst den
 * MVP-073-Scaffold-Test ab: deklarierte Capabilities, Dispatcher-
 * Registrierung unter der Plugin-ID und der Read-only-Decorator.
 */
final class JtlWawiScaffoldTest extends TestCase {
    public function test_provider_declares_capabilities(): void {
        $provider = $this->makeProvider();

        $this->assertTrue($provider->supports(ProviderCapability::ReadStock));
        $this->assertTrue($provider->supports(ProviderCapability::CheckAvailability));
        $this->assertTrue($provider->supports(ProviderCapability::PostReceipt));
        $this->assertTrue($provider->supports(ProviderCapability::PostCorrection));
        $this->assertContains(ProviderCapability::ReceiveFinishedGood, $provider->capabilities());
        // Reservierungen verwaltet die führende Wawi selbst.
        $this->assertFalse($provider->supports(ProviderCapability::Reserve));
        $this->assertFalse($provider->supports(ProviderCapability::ReleaseReservation));
    }

    public function test_dispatcher_registers_under_plugin_id(): void {
        $dispatcher = new JtlWawiOutboxDispatcher(
            $this->createMock(JtlGatewayFactory::class),
            $this->createMock(JtlMappingResolver::class),
        );

        $this->assertSame('jtl_wawi', $dispatcher->pluginId());

        $resolver = new ExternalInventoryDispatcherResolver();
        $resolver->register($dispatcher);
        $this->assertSame($dispatcher, $resolver->for('jtl_wawi'));
    }

    public function test_read_only_decorator_hides_writes_and_blocks_post(): void {
        $readOnly = new ReadOnlyInventoryProvider($this->makeProvider());

        $this->assertTrue($readOnly->supports(ProviderCapability::ReadStock));
        $this->assertTrue($readOnly->supports(ProviderCapability::CheckAvailability));
        $this->assertFalse($readOnly->supports(ProviderCapability::PostReceipt));
        $this->assertFalse($readOnly->supports(ProviderCapability::PostConsumption));

        $this->expectException(RuntimeException::class);
        $readOnly->post(new StockPosting(
            new ArticleVariant(),
            new Warehouse(),
            StockState::Physical,
            '1.0000',
            StockMovementType::Receipt,
        ));
    }

    private function makeProvider(): JtlWawiInventoryProvider {
        return new JtlWawiInventoryProvider(
            new Organization(),
            $this->createMock(JtlStockReader::class),
            $this->createMock(InventoryLedger::class),
        );
    }
}
