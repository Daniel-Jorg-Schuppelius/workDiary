<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentIssuers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Modules\ModuleRegistry;
use App\Services\Numbering\Contracts\DocumentIssuer;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Nummer eines Belegs beim Festschreiben (Phase 145, L1/L2): Vergibt ein
 * Programm den Kreis, überträgt es den Beleg und liefert seine Nummer; sonst
 * zieht workDiary eine Nummer, wenn der Beleg noch einen Entwurf trägt.
 */
final class DocumentIssuers {
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly NumberAuthority $authority,
        private readonly NumberSequenceService $numbers,
    ) {}

    /** @return list<DocumentIssuer> Programme, die Belege dieses Kreises nummerieren können */
    public function forScope(NumberScope $scope): array {
        $issuers = [];
        foreach ($this->modules->extensions(DocumentIssuer::class) as $class) {
            $issuer = app($class);
            if ($issuer instanceof DocumentIssuer && in_array($scope, $issuer->scopes(), true)) {
                $issuers[] = $issuer;
            }
        }

        return $issuers;
    }

    /** Vergibt workDiary den Kreis oder kann das vergebende Programm den Beleg übernehmen? */
    public function canIssue(int $organizationId, NumberScope $scope): bool {
        $source = $this->authority->sourceFor($organizationId, $scope);

        return $source === NumberAuthority::SOURCE_LOCAL
            || array_any($this->forScope($scope), static fn (DocumentIssuer $issuer): bool => $issuer->pluginId() === $source);
    }

    /**
     * Neue Nummer für einen Beleg, der festgeschrieben wird; null, wenn er
     * seine Nummer behält (keine Entwurfsnummer).
     *
     * @throws RuntimeException wenn das vergebende Programm nicht übertragen kann
     */
    public function numberOnIssue(Model $document, NumberScope $scope): ?string {
        if (! NumberSequenceService::isDraft((string) $document->getAttribute('number'))) {
            return null;
        }

        $organizationId = (int) $document->getAttribute('organization_id');
        $source = $this->authority->sourceFor($organizationId, $scope);
        if ($source === NumberAuthority::SOURCE_LOCAL) {
            return $this->numbers->next($organizationId, $scope);
        }
        foreach ($this->forScope($scope) as $issuer) {
            if ($issuer->pluginId() === $source) {
                return $issuer->issue($document, $scope)->number;
            }
        }

        throw new RuntimeException((string) __('numbering.issue.no_issuer', [
            'program' => $this->authority->sourceLabel($source),
            'scope' => $scope->label(),
        ]));
    }
}
