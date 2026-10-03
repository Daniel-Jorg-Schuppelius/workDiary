<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMasterdataClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\OpenMasterdata;

use APIToolkit\API\Authentication\OAuth2\{OAuth2PasswordAuthentication, Psr16TokenStore};
use App\Models\Supplier\SupplierCatalogSource;
use App\Plugins\Support\PluginHttpFactory;
use App\Services\Procurement\OpenMasterdata\Exceptions\OpenMasterdataException;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

/**
 * Open Masterdata Product API 1.1 (MVP-1072): ein Artikel je Abfrage über
 * Großhandelsnummer, GTIN oder Hersteller und Herstellernummer, gewählte
 * Datenpakete. Anmeldung per OAuth2-Passwort-Grant beim Großhändler; der
 * Token liegt im Cache je Quelle und Anmeldung.
 */
class OpenMasterdataClient {
    public const SERVICE_ID = 'open-masterdata';

    public const PACKAGES = ['basic', 'additional', 'prices', 'descriptions', 'logistics', 'sparepartlists', 'pictures', 'documents'];

    /** HTTP-Status mit vollständigem Produkt: Alternativ- bzw. Nachfolgeartikel. */
    public const STATUS_ALTERNATIVE = 950;

    public const STATUS_FOLLOWUP = 951;

    public const STATUS_AMBIGUOUS = 952;

    public function __construct(private readonly PluginHttpFactory $http) {}

    /** @param list<string> $packages */
    public function bySupplierPid(SupplierCatalogSource $source, string $supplierPid, array $packages = self::PACKAGES): OpenMasterdataProduct {
        return $this->fetch($source, '/product/bySupplierPID', ['supplierPid' => $supplierPid], $packages);
    }

    /** @param list<string> $packages */
    public function byGtin(SupplierCatalogSource $source, string $gtin, array $packages = self::PACKAGES): OpenMasterdataProduct {
        return $this->fetch($source, '/product/byGTIN', ['gtin' => $gtin], $packages);
    }

    /** @param list<string> $packages */
    public function byManufacturerData(SupplierCatalogSource $source, string $manufacturerId, string $manufacturerIdType, string $manufacturerPid, array $packages = self::PACKAGES): OpenMasterdataProduct {
        return $this->fetch($source, '/product/byManufacturerData', [
            'manufacturerId' => $manufacturerId,
            'manufacturerIdType' => $manufacturerIdType,
            'manufacturerPid' => $manufacturerPid,
        ], $packages);
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<string>  $packages
     */
    private function fetch(SupplierCatalogSource $source, string $path, array $query, array $packages): OpenMasterdataProduct {
        $config = OpenMasterdataConfig::fromSource($source) ?? throw new OpenMasterdataException(OpenMasterdataException::NOT_CONFIGURED);
        $packages = array_values(array_intersect($packages, self::PACKAGES)) ?: self::PACKAGES;
        if ($config->customerId !== '') {
            $query['customerId'] = $config->customerId;
        }

        $url = $config->baseUrl . $path . '?' . http_build_query($query) . '&' . $this->packageQuery($packages, $config->packageMode);
        $client = $this->http->coreClient(self::SERVICE_ID, $config->baseUrl);
        $client->setAuthentication($this->authentication($source, $config));

        try {
            $response = $client->getResponse($url);
        } catch (InvalidArgumentException) {
            // Fassung 1.x meldet Ersatzartikel als HTTP 950/951 — außerhalb von
            // 1xx–5xx; PSR-7 weist den Status beim Aufbau der Antwort ab. Eine
            // andere Ursache gibt es für den fertig gebauten GET nicht; ab
            // Fassung 11 steht der Status im Antwortkörper.
            throw new OpenMasterdataException(OpenMasterdataException::REPLACED);
        } catch (Throwable $e) {
            report($e);

            throw new OpenMasterdataException(OpenMasterdataException::FAILED);
        }

        $status = $response->status();
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        // Ab Open Masterdata 11 steht der Artikelstatus auch im Körper.
        $bodyStatus = isset($body['status']) && is_numeric($body['status']) ? (int) $body['status'] : null;

        $effective = $bodyStatus ?? $status;
        if (in_array($effective, [200, self::STATUS_ALTERNATIVE, self::STATUS_FOLLOWUP], true) && ($status === 200 || $status === self::STATUS_ALTERNATIVE || $status === self::STATUS_FOLLOWUP)) {
            if (! isset($body['supplierPid']) && ! isset($body['basic'])) {
                throw new OpenMasterdataException(OpenMasterdataException::INVALID_RESPONSE, $status);
            }

            return new OpenMasterdataProduct($body, $effective === 200 ? 200 : $effective);
        }

        throw new OpenMasterdataException(match (true) {
            $effective === 404 => OpenMasterdataException::NOT_FOUND,
            $effective === self::STATUS_AMBIGUOUS => OpenMasterdataException::AMBIGUOUS,
            $status === 401, $status === 403 => OpenMasterdataException::UNAUTHORIZED,
            $status === 429 => OpenMasterdataException::RATE_LIMITED,
            default => OpenMasterdataException::FAILED,
        }, $status);
    }

    private function authentication(SupplierCatalogSource $source, OpenMasterdataConfig $config): OAuth2PasswordAuthentication {
        $grant = $this->http->passwordGrant(self::SERVICE_ID, $config->clientId, $config->clientSecret, $config->tokenUrl);
        // PSR-16-Schlüssel ohne reservierte Zeichen; der Abdruck bindet den Token an Zugang und Anmeldung.
        $store = new Psr16TokenStore(Cache::store(), 'omd-token-' . $source->id . '-' . substr((string) CryptoHelper::hash($config->tokenUrl . '|' . $config->loginName() . '|' . $config->clientId), 0, 32));
        // Einzelne Großhändler führen den Passwort-Flow unter grant_type=client_credentials.
        $extra = $config->grantType === OpenMasterdataConfig::GRANT_CLIENT_CREDENTIALS ? ['grant_type' => OpenMasterdataConfig::GRANT_CLIENT_CREDENTIALS] : [];

        return new OAuth2PasswordAuthentication($grant, $config->loginName(), $config->password, $store, $config->scope !== '' ? [$config->scope] : [], 60, [], $extra);
    }

    /** @param list<string> $packages */
    private function packageQuery(array $packages, string $mode): string {
        if ($mode === OpenMasterdataConfig::PACKAGES_EXPLODED) {
            return implode('&', array_map(static fn (string $package): string => 'datapackage=' . rawurlencode($package), $packages));
        }

        return 'datapackage=' . rawurlencode(implode('|', $packages));
    }
}
