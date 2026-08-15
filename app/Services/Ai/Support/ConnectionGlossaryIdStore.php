<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConnectionGlossaryIdStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Support;

use App\Models\Ai\AiProviderConnection;
use TranslationToolkit\Contracts\Interfaces\GlossaryIdStoreInterface;

/**
 * Ablage der Provider-Glossar-ID (DeepL v3) an der Verbindung — ein Glossar
 * je Verbindung, damit die Organisation ihre eigene Terminologie behält.
 *
 * Der mitgeführte Fingerprint ist der eigentliche Kostenhebel: das Toolkit
 * überspringt den Remote-Sync, solange sich am Gedächtnis-Glossar nichts
 * geändert hat. Weil er an der Verbindung persistiert, gilt das auch über
 * Prozess- und Queue-Grenzen hinweg — sonst würde jeder Worker beim ersten
 * Auftrag erneut synchronisieren.
 */
final class ConnectionGlossaryIdStore implements GlossaryIdStoreInterface {
    private const ID_KEY = 'deepl_glossary_id';

    private const FINGERPRINT_KEY = 'deepl_glossary_fingerprint';

    public function __construct(private readonly AiProviderConnection $connection) {}

    public function get(): ?string {
        return $this->option(self::ID_KEY);
    }

    public function set(string $glossaryId): void {
        $this->store(self::ID_KEY, $glossaryId);
    }

    public function getSyncedFingerprint(): ?string {
        return $this->option(self::FINGERPRINT_KEY);
    }

    public function setSyncedFingerprint(string $fingerprint): void {
        $this->store(self::FINGERPRINT_KEY, $fingerprint);
    }

    private function option(string $key): ?string {
        $value = data_get($this->connection->options, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function store(string $key, string $value): void {
        /** @var array<string, mixed> $options */
        $options = (array) ($this->connection->options ?? []);
        $options[$key] = $value;

        $this->connection->forceFill(['options' => $options])->save();
    }
}
