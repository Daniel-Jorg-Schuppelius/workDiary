<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapabilityRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins;

use App\Plugins\CapabilityRegistry;
use App\Plugins\Contracts\{ContactSyncer, PluginCapability, PluginCapabilityContract};
use InvalidArgumentException;
use Tests\TestCase;

/** Geöffnetes Capability-System (Review 2026-08, W5e / Entscheidung E-6). */
class CapabilityRegistryTest extends TestCase {
    public function test_core_enum_cases_are_always_known(): void {
        $registry = new CapabilityRegistry;

        $this->assertSame(PluginCapability::ContactSync, $registry->find('contact_sync'));
        $this->assertCount(count(PluginCapability::cases()), $registry->all());
    }

    public function test_external_capability_can_be_registered_and_found(): void {
        $registry = new CapabilityRegistry;
        $registry->register(new ExternalTestCapability);

        $found = $registry->find('external_probe');
        $this->assertInstanceOf(ExternalTestCapability::class, $found);
        $this->assertContains('external_probe', array_map(fn(PluginCapabilityContract $c) => $c->identifier(), $registry->all()));
    }

    public function test_duplicate_identifier_is_rejected(): void {
        $registry = new CapabilityRegistry;

        $this->expectException(InvalidArgumentException::class);
        $registry->register(new class implements PluginCapabilityContract {
            public function identifier(): string {
                return 'contact_sync'; // kollidiert mit dem Kern-Enum
            }
            public function label(): string {
                return 'Kollision';
            }
            public function interface(): string {
                return ContactSyncer::class;
            }
        });
    }
}

final class ExternalTestCapability implements PluginCapabilityContract {
    public function identifier(): string {
        return 'external_probe';
    }

    public function label(): string {
        return 'Externe Probe';
    }

    public function interface(): string {
        return ContactSyncer::class;
    }
}
