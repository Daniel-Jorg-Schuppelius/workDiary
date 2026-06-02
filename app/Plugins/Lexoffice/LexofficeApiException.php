<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Übersetzt fehlgeschlagene Lexoffice-API-Antworten in verständliche,
 * lokalisierte Fehlermeldungen. Insbesondere `403 Forbidden` deutet fast immer
 * auf eine fehlende Berechtigung des API-Schlüssels (bzw. einen Tarif ohne die
 * jeweilige Ressource) hin – das soll dem Anwender klar gesagt werden, statt
 * den rohen JSON-Body durchzureichen.
 */
class LexofficeApiException extends RuntimeException {
    /**
     * Baut aus einer fehlgeschlagenen Antwort eine sprechende Exception.
     *
     * @param  string  $resource  Menschlich lesbarer Ressourcenname, z. B. „Artikel"
     * @param  string  $scope     Benötigte Lexoffice-Berechtigung, z. B. „Artikel abrufen"
     */
    public static function fromResponse(Response $response, string $resource, string $scope): self {
        $status = $response->status();

        $message = match ($status) {
            401 => __('Lexoffice lehnt den API-Schlüssel ab (401). Bitte prüfe, ob der hinterlegte Schlüssel gültig ist.'),
            403 => __('Lexoffice verweigert den Zugriff auf :resource (403). Dem API-Schlüssel fehlt die Berechtigung „:scope" (oder der Tarif enthält diese Ressource nicht).', [
                'resource' => $resource,
                'scope' => $scope,
            ]),
            429 => __('Lexoffice hat das Anfragelimit erreicht (429). Bitte versuche es in einigen Minuten erneut.'),
            default => __('Lexoffice-Anfrage für :resource fehlgeschlagen (:status).', [
                'resource' => $resource,
                'status' => $status,
            ]),
        };

        return new self($message);
    }
}
