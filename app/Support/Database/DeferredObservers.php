<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeferredObservers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Model;

/**
 * Meldet Modell-Beobachter erst an, wenn das Modell hochfährt. `Model::observe()` im Provider fährt
 * jedes Modell beim Start jeder Anfrage hoch (Traits, Attribute, Scopes) — rund 9 ms für knapp 60
 * Modelle, von denen eine Anfrage wenige braucht. Der Rückruf läuft nach `boot()` und `booted()`,
 * die Listener stehen also in derselben Reihenfolge wie bisher: erst die des Modells, dann die Beobachter.
 */
final class DeferredObservers {
    /** @var array<class-string<Model>, list<class-string>> Modell → Beobachter in Anmelde-Reihenfolge */
    private array $pending = [];

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $observer
     */
    public static function observe(string $model, string $observer): void {
        app(self::class)->defer($model, $observer);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $observer
     */
    private function defer(string $model, string $observer): void {
        if (self::isBooted($model)) {
            $model::observe($observer);

            return;
        }
        if (! isset($this->pending[$model])) {
            $attach = function () use ($model): void {
                $this->attach($model);
            };
            // whenBooted() ist geschützt; der Rückruf hängt nicht am Event-Dispatcher und läuft deshalb
            // auch, wenn das Modell unter withoutEvents() oder Event::fake() zum ersten Mal gebraucht wird.
            \Closure::bind(static function () use ($attach): void {
                static::whenBooted($attach);
            }, null, $model)();
        }
        $this->pending[$model][] = $observer;
    }

    /** @param  class-string<Model>  $model */
    private function attach(string $model): void {
        // Rückrufe sind statisch am Modell und überleben die Anwendung (Tests bauen je Test eine neue):
        // nur die Instanz der laufenden Anwendung meldet an.
        if (! app()->bound(self::class) || app(self::class) !== $this) {
            return;
        }
        foreach ($this->pending[$model] ?? [] as $observer) {
            $model::observe($observer);
        }
        unset($this->pending[$model]);
    }

    /** @param  class-string<Model>  $model */
    private static function isBooted(string $model): bool {
        return \Closure::bind(static fn(): bool => isset(self::$booted[$model]), null, Model::class)();
    }
}
