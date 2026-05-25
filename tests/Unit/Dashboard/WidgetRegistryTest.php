<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WidgetRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Dashboard;

use App\Dashboard\{Widget, WidgetRegistry};
use App\Models\User;
use Illuminate\Contracts\View\View;
use PHPUnit\Framework\TestCase;

class WidgetRegistryTest extends TestCase {
    public function test_register_and_find_widget(): void {
        $registry = new WidgetRegistry();
        $widget = $this->makeWidget('foo', null);
        $registry->register($widget);

        $this->assertSame($widget, $registry->find('foo'));
        $this->assertNull($registry->find('missing'));
        $this->assertCount(1, $registry->all());
    }

    public function test_register_overwrites_by_key(): void {
        $registry = new WidgetRegistry();
        $first = $this->makeWidget('dup', null);
        $second = $this->makeWidget('dup', null);
        $registry->register($first);
        $registry->register($second);

        $this->assertSame($second, $registry->find('dup'));
        $this->assertCount(1, $registry->all());
    }

    public function test_available_for_filters_by_ability(): void {
        $registry = new WidgetRegistry();
        $registry->register($this->makeWidget('public', null));
        $registry->register($this->makeWidget('restricted', 'manage.things'));

        $user = $this->createPartialMock(User::class, ['hasEffectivePermission', 'isAdmin']);
        $user->method('hasEffectivePermission')->willReturn(false);
        $user->method('isAdmin')->willReturn(false);

        $this->assertSame(['public'], $registry->availableFor($user)->keys()->all());
    }

    private function makeWidget(string $key, ?string $ability): Widget {
        return new class($key, $ability) extends Widget {
            public function __construct(private readonly string $widgetKey, private readonly ?string $ability) {}

            public function key(): string {
                return $this->widgetKey;
            }

            public function label(): string {
                return $this->widgetKey;
            }

            public function requiredAbility(): ?string {
                return $this->ability;
            }

            public function render(User $user): View|string {
                return $this->widgetKey;
            }
        };
    }
}
