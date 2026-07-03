<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidEncoder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use CommonToolkit\Helper\Data\CryptoHelper;
use InvalidArgumentException;
use Sqids\Sqids;

/**
 * Zentraler Encoder für opake URL-IDs.
 *
 * Wandelt numerische Primärschlüssel pro Modell deterministisch in kurze,
 * nicht-enumerierbare Strings (Sqids) und zurück. Pro Modell-Klasse wird
 * ein eigener Sqids-Instanz-Cache mit einem aus `salt + class` abgeleiteten
 * Alphabet aufgebaut, damit der gleiche Integer nie bei zwei Modellen die
 * gleiche Sqid ergibt.
 */
final class SqidEncoder {
    /** @var array<class-string, Sqids> */
    private array $encoders = [];

    public function __construct(
        private readonly string $salt,
        private readonly int $minLength,
        private readonly string $alphabet,
        /** @var list<string> */
        private readonly array $blocklist = [],
    ) {
        if (mb_strlen($this->alphabet) < 5) {
            throw new InvalidArgumentException('Sqids alphabet must contain at least 5 characters.');
        }
    }

    /**
     * Encode a model PK into a Sqid for the given model class.
     *
     * @param  class-string  $modelClass
     */
    public function encode(string $modelClass, int $id): string {
        if ($id <= 0) {
            throw new InvalidArgumentException('Sqid encoding requires a positive integer id.');
        }

        return $this->forModel($modelClass)->encode([$id]);
    }

    /**
     * Decode a Sqid back to its numeric PK for the given model class.
     * Returns null if the input is malformed, empty or does not roundtrip.
     *
     * @param  class-string  $modelClass
     */
    public function decode(string $modelClass, string $sqid): ?int {
        $sqid = trim($sqid);
        if ($sqid === '') {
            return null;
        }

        $encoder = $this->forModel($modelClass);
        $decoded = $encoder->decode($sqid);

        if (count($decoded) !== 1) {
            return null;
        }

        $id = $decoded[0];
        if ($id <= 0) {
            return null;
        }

        // Roundtrip-Check: verhindert, dass eine Sqid eines fremden Modells
        // (anderes Alphabet) zufällig dekodierbar wirkt.
        if ($encoder->encode([$id]) !== $sqid) {
            return null;
        }

        return $id;
    }

    /**
     * @param  class-string  $modelClass
     */
    private function forModel(string $modelClass): Sqids {
        if (! isset($this->encoders[$modelClass])) {
            $this->encoders[$modelClass] = new Sqids(
                alphabet: $this->permuteAlphabet($modelClass),
                minLength: $this->minLength,
                blocklist: $this->blocklist,
            );
        }

        return $this->encoders[$modelClass];
    }

    /**
     * Erzeugt eine deterministische Permutation des Alphabets aus
     * `salt + modelClass`. Zwei Modelle erhalten so unterschiedliche
     * Alphabete; identische PKs ergeben unterschiedliche Sqids.
     *
     * @param  class-string  $modelClass
     */
    private function permuteAlphabet(string $modelClass): string {
        $seed = CryptoHelper::hash($this->salt . '|' . $modelClass);
        $chars = mb_str_split($this->alphabet);
        $count = count($chars);

        // Fisher–Yates Shuffle mit Hash-basierter Pseudozufalls-Sequenz.
        $stream = '';
        for ($i = 0; mb_strlen($stream) < $count * 8; $i++) {
            $stream .= CryptoHelper::hash($seed . '|' . $i);
        }

        for ($i = $count - 1; $i > 0; $i--) {
            $slice = substr($stream, $i * 8, 8);
            $rand = hexdec($slice);
            $j = (int) ($rand % ($i + 1));
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
