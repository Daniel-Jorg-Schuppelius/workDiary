<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiScaffoldTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Inventory;

use App\Enums\Inventory\ProviderCapability;
use App\Models\{ArticleVariant, InventoryOutboxEntry, Warehouse};
use App\Services\Inventory\External\{JtlWawiDispatcher, JtlWawiInventoryProvider};
use App\Services\Inventory\ExternalInventoryDispatcherResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * JTL-Wawi-Scaffold (MVP-073): deklarierte Vertragsfläche und sicheres
 * Pilot-Verhalten (klare Ausnahme statt erfundener Werte/stillem Verlust).
 */
final class JtlWawiScaffoldTest extends TestCase {
    public function test_provider_declares_capabilities(): void {
        $provider = new JtlWawiInventoryProvider();

        $this->assertTrue($provider->supports(ProviderCapability::ReadStock));
        $this->assertTrue($provider->supports(ProviderCapability::PostReceipt));
        $this->assertContains(ProviderCapability::ReceiveFinishedGood, $provider->capabilities());
    }

    public function test_provider_data_methods_signal_pilot_pending(): void {
        $provider = new JtlWawiInventoryProvider();

        $this->expectException(RuntimeException::class);
        $provider->available(new ArticleVariant(), new Warehouse());
    }

    public function test_dispatcher_registers_under_plugin_id_and_signals_pilot(): void {
        $resolver = new ExternalInventoryDispatcherResolver();
        $dispatcher = new JtlWawiDispatcher();
        $resolver->register($dispatcher);

        $this->assertSame('jtl_wawi', $dispatcher->pluginId());
        $this->assertSame($dispatcher, $resolver->for('jtl_wawi'));

        $this->expectException(RuntimeException::class);
        $dispatcher->dispatch(new InventoryOutboxEntry());
    }
}
