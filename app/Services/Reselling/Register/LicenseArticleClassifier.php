<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseArticleClassifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Reselling\ResaleArticleClassification;
use App\Services\Reselling\Marketplace\ProductNameMatcher;

/**
 * Ist ein Artikel ein Abo-Produkt? Gilt für jede Katalogquelle gleichermaßen
 * (Artikelstamm, Lexoffice, …; MVP-1025): die Einstufung des Betreibers
 * ({@see ResaleArticleClassification}, am Katalogschlüssel) gewinnt; ohne
 * Einstufung entscheidet die Produkterkennung über den Artikelnamen. Einzige
 * Stelle für diese Frage — Vorschlagslauf, Import, Belegspiegel, Preisprüfung
 * und Positionen-ohne-Abo fragen hier. Scoped gebunden: der Einstufungs-Cache
 * lebt je Request bzw. Job, Änderungen an Einstufungen leeren ihn.
 */
final class LicenseArticleClassifier {
    /** @var array<int, array<string, ResaleArticleRole>> Organisation → Katalogschlüssel → Einstufung */
    private array $roles = [];

    public function __construct(private readonly ProductNameMatcher $matcher = new ProductNameMatcher()) {}

    public function flush(): void {
        $this->roles = [];
    }

    /** Ohne Artikel (kein Katalogschlüssel) nie ein Abo-Produkt. */
    public function isLicense(int $organizationId, ?string $articleKey, ?string $name): bool {
        if ($articleKey === null) {
            return false;
        }

        return match ($this->roleOf($organizationId, $articleKey)) {
            ResaleArticleRole::Excluded => false,
            ResaleArticleRole::License => true,
            null => $this->matcher->looksLikeMicrosoftProduct((string) $name),
        };
    }

    public function roleOf(int $organizationId, string $articleKey): ?ResaleArticleRole {
        return $this->roles($organizationId)[$articleKey] ?? null;
    }

    /**
     * Alle Einstufungen der Organisation, einmal je Instanz geladen.
     *
     * @return array<string, ResaleArticleRole>
     */
    public function roles(int $organizationId): array {
        if (! isset($this->roles[$organizationId])) {
            $this->roles[$organizationId] = [];
            foreach (ResaleArticleClassification::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->get(['article_ref', 'role']) as $row) {
                $this->roles[$organizationId][$row->article_ref] = $row->role;
            }
        }

        return $this->roles[$organizationId];
    }

    /** Automatische Einstufung ohne Betreiber-Override (für die Anzeige). */
    public function detected(string $name): ResaleArticleRole {
        return $this->matcher->looksLikeMicrosoftProduct($name) ? ResaleArticleRole::License : ResaleArticleRole::Excluded;
    }
}
