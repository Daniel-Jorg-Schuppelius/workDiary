<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMemoryEntryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Ai;

use App\Enums\Ai\AiMemoryEntryType;
use App\Models\Ai\AiMemoryEntry;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMemoryEntry>
 */
class AiMemoryEntryFactory extends Factory {
    protected $model = AiMemoryEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'entry_type' => AiMemoryEntryType::Glossary,
            'term' => 'snap',
            'content' => 'Snap-Paketverwaltung der Ubuntu-Server, nicht Schnappschuss',
            'origin' => AiMemoryEntry::ORIGIN_MANUAL,
            'active' => true,
        ];
    }

    public function styleRule(string $rule = 'Positionstexte im Nominalstil'): static {
        return $this->state(fn (): array => [
            'entry_type' => AiMemoryEntryType::StyleRule,
            'term' => null,
            'content' => $rule,
        ]);
    }

    public function example(string $source = 'wartung server', string $target = 'Wartung der Serversysteme'): static {
        return $this->state(fn (): array => [
            'entry_type' => AiMemoryEntryType::Example,
            'term' => null,
            'source_text' => $source,
            'content' => $target,
        ]);
    }

    public function inactive(): static {
        return $this->state(fn (): array => ['active' => false]);
    }
}
