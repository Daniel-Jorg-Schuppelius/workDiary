<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeferredObserversTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Support\Database\DeferredObservers;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Beobachter melden sich erst an, wenn das Modell hochfährt (Phase 188): der Start einer Anfrage
 * fährt kein Modell mehr hoch, und die Listener stehen in derselben Reihenfolge wie bei `Model::observe()`.
 */
class DeferredObserversTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        DeferredObserverProbeLog::$entries = [];
        Model::clearBootedModels();
    }

    private function fire(Model $model, string $event): void {
        (fn() => $this->fireModelEvent($event))->call($model);
    }

    /** @param  class-string<Model>  $model */
    private function isBooted(string $model): bool {
        return \Closure::bind(static fn(): bool => isset(static::$booted[$model]), null, Model::class)();
    }

    public function test_registering_does_not_boot_the_model_and_observers_follow_the_models_own_listeners(): void {
        DeferredObservers::observe(DeferredObserverProbeModel::class, DeferredObserverProbeFirst::class);
        DeferredObservers::observe(DeferredObserverProbeModel::class, DeferredObserverProbeSecond::class);
        $this->assertFalse($this->isBooted(DeferredObserverProbeModel::class));

        $this->fire(new DeferredObserverProbeModel, 'saving');
        $this->assertTrue($this->isBooted(DeferredObserverProbeModel::class));
        $this->assertSame(['model', 'first', 'second'], DeferredObserverProbeLog::$entries);

        // Ist das Modell schon hochgefahren, meldet der Baustein sofort an.
        DeferredObserverProbeLog::$entries = [];
        DeferredObservers::observe(DeferredObserverProbeModel::class, DeferredObserverProbeThird::class);
        $this->fire(new DeferredObserverProbeModel, 'saving');
        $this->assertSame(['model', 'first', 'second', 'third'], DeferredObserverProbeLog::$entries);
    }

    public function test_observers_attach_when_the_model_first_boots_without_events(): void {
        DeferredObservers::observe(DeferredObserverProbeModel::class, DeferredObserverProbeFirst::class);
        Model::withoutEvents(static fn() => new DeferredObserverProbeModel);

        $this->fire(new DeferredObserverProbeModel, 'saving');
        $this->assertSame(['model', 'first'], DeferredObserverProbeLog::$entries);
    }

    public function test_a_callback_of_a_previous_application_does_not_register_again(): void {
        DeferredObservers::observe(DeferredObserverProbeModel::class, DeferredObserverProbeFirst::class);
        // Neue Anwendung (wie zwischen zwei Tests): der Rückruf der alten Instanz hängt noch am Modell.
        $this->app->forgetInstance(DeferredObservers::class);
        DeferredObservers::observe(DeferredObserverProbeModel::class, DeferredObserverProbeFirst::class);

        $this->fire(new DeferredObserverProbeModel, 'saving');
        $this->assertSame(['model', 'first'], DeferredObserverProbeLog::$entries);
    }
}

final class DeferredObserverProbeLog {
    /** @var list<string> */
    public static array $entries = [];
}

final class DeferredObserverProbeModel extends Model {
    protected static function booted(): void {
        self::saving(static function (): void {
            DeferredObserverProbeLog::$entries[] = 'model';
        });
    }
}

final class DeferredObserverProbeFirst {
    public function saving(DeferredObserverProbeModel $model): void {
        DeferredObserverProbeLog::$entries[] = 'first';
    }
}

final class DeferredObserverProbeSecond {
    public function saving(DeferredObserverProbeModel $model): void {
        DeferredObserverProbeLog::$entries[] = 'second';
    }
}

final class DeferredObserverProbeThird {
    public function saving(DeferredObserverProbeModel $model): void {
        DeferredObserverProbeLog::$entries[] = 'third';
    }
}
