<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchProfileRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\{Article, Customer, Project, Supplier};
use App\Services\Integration\Match\MatchProfile;
use App\Services\Integration\Profiles\{ArticleMatchProfile, CustomerMatchProfile, EventMatchProfile, ProjectMatchProfile, SupplierMatchProfile};

/**
 * Liefert das {@see MatchProfile} zu einer Ziel-Entität (Morph-Klasse). Neue
 * Entitäten (Supplier, Project, …) werden hier registriert — Phase 0 deckt
 * vorerst nur Kunden ab.
 */
class MatchProfileRegistry {
    /** @var array<string, class-string<MatchProfile>> */
    private array $map = [
        Customer::class => CustomerMatchProfile::class,
        Supplier::class => SupplierMatchProfile::class,
        Article::class => ArticleMatchProfile::class,
        // Feature 102 (C3-Übernahme): Kalender-Vorschläge → Event anlegen.
        \App\Models\Event::class => EventMatchProfile::class,
        // CSV-Projektzeiten (Feature 094): unaufgelöste Projektnamen aus der
        // Inbox heraus anlegen — danach bucht der idempotente Wiederholimport.
        Project::class => ProjectMatchProfile::class,
    ];

    public function for(string $targetType): ?MatchProfile {
        $class = $this->map[$targetType] ?? null;

        return $class !== null ? app($class) : null;
    }

    /** Lesbares Label der Ziel-Entität (für Filter/Anzeige). */
    public function label(string $targetType): string {
        return match ($targetType) {
            Customer::class => (string) __('Kunde'),
            Supplier::class => (string) __('Lieferant'),
            Article::class => (string) __('Artikel'),
            \App\Models\Project::class => (string) __('Projekt'),
            \App\Models\Asset::class => (string) __('Gerät'),
            \App\Models\Event::class => (string) __('Termin'),
            default => class_basename($targetType),
        };
    }

    /**
     * Alle registrierten Ziel-Typen (Morph-Klassen) → Label, für Filter-Dropdowns.
     *
     * @return array<string, string>
     */
    public function options(): array {
        $out = [];
        foreach (array_keys($this->map) as $type) {
            $out[$type] = $this->label($type);
        }

        return $out;
    }
}
