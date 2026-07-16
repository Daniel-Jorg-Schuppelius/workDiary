<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeAiProviderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Exceptions\AiProviderCallException;

/**
 * Container-Ersatz für {@see AiProviderFactory} in Tests (Muster
 * FakePluginHttp): liefert für jede Verbindung denselben
 * {@see FakeAiProvider}; einzelne Verbindungen lassen sich gezielt als
 * „fehlgeschlagen" markieren, um Health-Tracking und Fallback-Kette zu
 * testen.
 */
class FakeAiProviderFactory extends AiProviderFactory {
    /** @var list<int> */
    private array $failingConnectionIds = [];

    private function __construct(private readonly FakeAiProvider $provider) {}

    public static function install(): FakeAiProvider {
        $provider = new FakeAiProvider();
        app()->instance(AiProviderFactory::class, new self($provider));

        return $provider;
    }

    public static function current(): self {
        $factory = app(AiProviderFactory::class);
        assert($factory instanceof self);

        return $factory;
    }

    public function failFor(AiProviderConnection $connection): void {
        $this->failingConnectionIds[] = (int) $connection->id;
    }

    public function make(AiProviderConnection $connection): AiProviderInterface {
        if (in_array((int) $connection->id, $this->failingConnectionIds, true)) {
            throw AiProviderCallException::transport($connection->provider->value, 'Simulierter Transportfehler.');
        }

        return $this->provider;
    }
}
