<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation;

use App\Models\Organization;
use App\Services\Finance\ProcedureDocumentation\Sections\{
    AccountingProfileSection,
    BackupSection,
    ImmutabilitySection,
    InterfacesSection,
    NumberingSection,
    RetentionSection,
    RolesSection,
    SystemSection
};
use CommonToolkit\Helper\Data\JsonHelper;
use RuntimeException;

/**
 * Generierter Systemteil der GoBD-Verfahrensdokumentation (Feature 134,
 * MVP-699): reiht die {@see ProcedureSection}-Abschnitte zu einer
 * strukturierten, rein aus Strings bestehenden Datenstruktur, die als
 * Live-Vorschau dient und beim Veröffentlichen als Snapshot eingefroren wird.
 * Schlüssel mit Secret-Mustern lehnt der Builder ab (Defense in depth zu den
 * $hidden-/encrypted-Regeln der Modelle).
 */
final class ProcedureDocumentationBuilder {
    public const SCHEMA = 'workdiary.procedure-documentation/v1';

    private const SECRET_KEY_PATTERN = '/secret|token|passw|api[_-]?key|apikey|private[_-]?key|credential|envelope/i';

    public function __construct(
        private readonly SystemSection $system,
        private readonly NumberingSection $numbering,
        private readonly RolesSection $roles,
        private readonly ImmutabilitySection $immutability,
        private readonly BackupSection $backup,
        private readonly InterfacesSection $interfaces,
        private readonly RetentionSection $retention,
        private readonly AccountingProfileSection $accounting,
    ) {}

    /** @return list<ProcedureSection> */
    public function sections(): array {
        return [
            $this->system,
            $this->accounting,
            $this->numbering,
            $this->roles,
            $this->immutability,
            $this->backup,
            $this->interfaces,
            $this->retention,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Organization $organization, bool $verifyChains = false): array {
        $context = new SectionContext($verifyChains);
        $sections = [];
        foreach ($this->sections() as $section) {
            $sections[] = array_merge([
                'key' => $section->key(),
                'title' => $section->title(),
            ], $section->build($organization, $context));
        }

        $payload = [
            'schema' => self::SCHEMA,
            'generated_at' => now()->toIso8601String(),
            'chains_verified' => $verifyChains,
            'organization' => [
                'name' => (string) $organization->name,
                'sqid' => (string) $organization->getAttribute('sqid'),
            ],
            'sections' => $sections,
        ];
        $this->assertNoSecretKeys($payload, '');

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public function toJson(array $payload): string {
        return JsonHelper::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<array-key, mixed> $node */
    private function assertNoSecretKeys(array $node, string $path): void {
        foreach ($node as $key => $value) {
            $current = $path === '' ? (string) $key : $path . '.' . $key;
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
                throw new RuntimeException('Verfahrensdokumentation: Schlüssel mit Secret-Muster in der Ausgabe (' . $current . ').');
            }
            if (is_array($value)) {
                $this->assertNoSecretKeys($value, $current);
            }
        }
    }
}
