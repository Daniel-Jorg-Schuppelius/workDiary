<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRef.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Model;

/**
 * Verweis auf einen Beleg im Belegverbund (MVP-1157): lokale Belege über den
 * Morph-Alias, Belege eines Programms über `<plugin-id>.<art>` und dessen ID.
 */
final readonly class DocumentRef {
    public function __construct(
        public string $type,
        public string $id,
    ) {}

    public static function forModel(Model $model): self {
        return new self($model->getMorphClass(), (string) $model->getKey());
    }

    public static function external(string $pluginId, string $kind, string $externalId): self {
        return new self($pluginId . '.' . $kind, $externalId);
    }

    public function key(): string {
        return $this->type . ':' . $this->id;
    }

    /**
     * Lokaler Beleg dieser Klasse?
     *
     * @param  class-string  $modelClass
     */
    public function is(string $modelClass): bool {
        return MorphMap::is($this->type, $modelClass);
    }

    public function isExternal(string $pluginId, ?string $kind = null): bool {
        return $kind === null ? str_starts_with($this->type, $pluginId . '.') : $this->type === $pluginId . '.' . $kind;
    }
}
