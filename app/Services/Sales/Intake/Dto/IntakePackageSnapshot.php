<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakePackageSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Dto;

use App\Enums\Sales\IntakeSensitivity;

/**
 * Eingefrorener Stand eines Prüfpakets an der Akte (Feature 200): Was beim
 * Öffnen galt — Bezeichnung, Entscheidungspflicht, Aufbewahrung,
 * Aktualisierung, enge Sichtbarkeit je Feld und Übernahme an den Kunden —
 * bleibt an der Akte, auch wenn die Organisation das Paket später ändert oder
 * abschaltet. Die GwG-Aufbewahrung hängt so nie an einer Einstellung von heute.
 *
 * @phpstan-type Transfer array{deadline_traits?: array<string, array<string, string>>, classifications?: array<string, string>, customer?: array<string, string>}
 * @phpstan-type SnapshotArray array{key: string, label: string, requires_decision: bool, retention: array{years: int, basis: string}|null, refresh_months: int|null, sensitive: array<string, string>, transfer: Transfer, class: bool}
 */
final readonly class IntakePackageSnapshot {
    /**
     * @param  array<string, IntakeSensitivity>  $sensitive  Feldschlüssel MIT Paketpräfix → enge Sichtbarkeit
     * @param  Transfer  $transfer  Feldschlüssel OHNE Paketpräfix
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $requiresDecision,
        public ?IntakeRetentionRule $retention,
        public ?int $refreshMonths,
        public array $sensitive,
        public array $transfer,
        public bool $isClass,
    ) {}

    public static function fromArray(mixed $raw): ?self {
        if (! is_array($raw) || ! is_string($raw['key'] ?? null) || $raw['key'] === '') {
            return null;
        }
        $sensitive = [];
        foreach ((array) ($raw['sensitive'] ?? []) as $field => $category) {
            $kind = is_string($category) ? IntakeSensitivity::tryFrom($category) : null;
            if (is_string($field) && $kind !== null) {
                $sensitive[$field] = $kind;
            }
        }
        $refresh = $raw['refresh_months'] ?? null;
        /** @var Transfer $transfer */
        $transfer = is_array($raw['transfer'] ?? null) ? $raw['transfer'] : [];

        return new self(
            key: $raw['key'],
            label: (string) ($raw['label'] ?? $raw['key']),
            requiresDecision: (bool) ($raw['requires_decision'] ?? false),
            retention: IntakeRetentionRule::fromArray($raw['retention'] ?? null),
            refreshMonths: is_numeric($refresh) && (int) $refresh > 0 ? (int) $refresh : null,
            sensitive: $sensitive,
            transfer: $transfer,
            isClass: (bool) ($raw['class'] ?? false),
        );
    }

    /** @return SnapshotArray */
    public function toArray(): array {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'requires_decision' => $this->requiresDecision,
            'retention' => $this->retention?->toArray(),
            'refresh_months' => $this->refreshMonths,
            'sensitive' => array_map(static fn (IntakeSensitivity $kind): string => $kind->value, $this->sensitive),
            'transfer' => $this->transfer,
            'class' => $this->isClass,
        ];
    }
}
