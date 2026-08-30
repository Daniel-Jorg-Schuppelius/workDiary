<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Scorm;

use SimpleXMLElement;

/**
 * Parser für `imsmanifest.xml` (Feature 149, MVP-743).
 *
 * **Bewusst ohne Laravel-Abhängigkeit.** Manifest-Parsing ist fachneutral
 * und gehört perspektivisch in ein eigenes Paket (`php-elearning-toolkit`,
 * Klasse C). Diese Klasse kennt deshalb weder Container noch Modelle — ein
 * späterer Umzug ist reines Verschieben statt Neuschreiben.
 *
 * **Namespace-agnostisch**: Pakete unterscheiden sich in den Präfixen
 * (`adlcp` mit `_rootv1p2` bei SCORM 1.2, `_v1p3` bei 2004) und im
 * Attributnamen (`scormtype` vs. `scormType`). Wer auf feste Präfixe
 * parst, scheitert am ersten Paket eines anderen Autorenwerkzeugs.
 */
class ScormManifest {
    public const VERSION_12 = 'scorm_1_2';

    public const VERSION_2004 = 'scorm_2004';

    /**
     * @param  list<array{identifier: string, title: string, href: string|null, is_sco: bool}>  $items
     */
    private function __construct(
        public readonly string $version,
        public readonly string $title,
        public readonly ?string $launchHref,
        public readonly array $items,
    ) {}

    /** Aus dem XML-Text eines Manifests lesen. */
    public static function fromXml(string $xml): self {
        $previous = libxml_use_internal_errors(true);

        try {
            // Keine externen Entitäten: ein Manifest ist eine fremde Datei.
            $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOENT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($element === false) {
            throw new ScormPackageException(ScormPackageException::UNREADABLE, 'Das Manifest ist kein gültiges XML.');
        }

        $version = self::detectVersion($element);
        $resources = self::readResources($element);
        [$title, $items] = self::readOrganization($element, $resources);

        $launch = null;
        foreach ($items as $item) {
            if ($item['href'] !== null) {
                $launch = $item['href'];
                break;
            }
        }

        // Fällt die Organisation aus (manche Pakete führen nur Ressourcen),
        // gilt die erste startbare Ressource.
        if ($launch === null) {
            foreach ($resources as $resource) {
                if ($resource['href'] !== null) {
                    $launch = $resource['href'];
                    break;
                }
            }
        }

        return new self($version, $title, $launch, $items);
    }

    /** Name des Runtime-Objekts, das der Inhalt im Fenster sucht. */
    public function apiObjectName(): string {
        return $this->version === self::VERSION_2004 ? 'API_1484_11' : 'API';
    }

    /** Datenmodell-Schlüssel für den Abschlussstatus. */
    public function completionKey(): string {
        return $this->version === self::VERSION_2004 ? 'cmi.completion_status' : 'cmi.core.lesson_status';
    }

    private static function detectVersion(SimpleXMLElement $manifest): string {
        $schemaVersion = '';

        foreach ($manifest->xpath('//*[local-name()="schemaversion"]') ?: [] as $node) {
            $schemaVersion = trim((string) $node);
            break;
        }

        // „2004 3rd Edition“, „2004 4th Edition“, „CAM 1.3“ — alles 2004.
        if (str_contains($schemaVersion, '2004') || str_contains($schemaVersion, '1.3')) {
            return self::VERSION_2004;
        }

        if ($schemaVersion === '1.2') {
            return self::VERSION_12;
        }

        // Ohne verwertbare Angabe entscheidet der Namespace.
        foreach (self::namespaces($manifest) as $uri) {
            if (str_contains((string) $uri, 'adlcp_v1p3')) {
                return self::VERSION_2004;
            }
        }

        return self::VERSION_12;
    }

    /**
     * @return array<string, array{href: string|null, is_sco: bool}>
     */
    private static function readResources(SimpleXMLElement $manifest): array {
        $resources = [];

        foreach ($manifest->xpath('//*[local-name()="resource"]') ?: [] as $resource) {
            $identifier = trim((string) ($resource['identifier'] ?? ''));

            if ($identifier === '') {
                continue;
            }

            $href = trim((string) ($resource['href'] ?? ''));

            // `xml:base` verschiebt den Bezugspunkt — es steht am
            // <resources>-Block und/oder an der einzelnen <resource>. Wer es
            // übergeht, sucht die Einstiegsdatei im falschen Ordner.
            $base = self::xmlBase(($resource->xpath('..') ?: [null])[0]) . self::xmlBase($resource);

            $resources[$identifier] = [
                'href' => $href !== '' ? $base . $href : null,
                'is_sco' => self::isSco($resource),
            ];
        }

        return $resources;
    }

    /** `xml:base` eines Elements, normalisiert auf „endet mit /" oder leer. */
    private static function xmlBase(?SimpleXMLElement $element): string {
        if ($element === null) {
            return '';
        }

        $attributes = $element->attributes('http://www.w3.org/XML/1998/namespace');
        $base = trim((string) ($attributes['base'] ?? ''));

        if ($base === '') {
            return '';
        }

        return rtrim($base, '/') . '/';
    }

    /**
     * Namensräume des Dokuments — `getDocNamespaces()` liefert bei
     * kaputtem XML `false`, hier immer eine Liste.
     *
     * @return list<string>
     */
    private static function namespaces(SimpleXMLElement $element): array {
        $namespaces = $element->getDocNamespaces(true);

        return $namespaces === false ? [] : array_values(array_map('strval', $namespaces));
    }

    /**
     * `adlcp:scormtype` (1.2) bzw. `adlcp:scormType` (2004) — ohne diese
     * Angabe ist die Ressource ein Asset und meldet nie einen Status.
     */
    private static function isSco(SimpleXMLElement $resource): bool {
        foreach (self::namespaces($resource) as $uri) {
            foreach ($resource->attributes($uri) ?? [] as $name => $value) {
                if (strcasecmp((string) $name, 'scormtype') === 0) {
                    return strcasecmp(trim((string) $value), 'sco') === 0;
                }
            }
        }

        foreach ($resource->attributes() ?? [] as $name => $value) {
            if (strcasecmp((string) $name, 'scormtype') === 0) {
                return strcasecmp(trim((string) $value), 'sco') === 0;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{href: string|null, is_sco: bool}>  $resources
     * @return array{0: string, 1: list<array{identifier: string, title: string, href: string|null, is_sco: bool}>}
     */
    private static function readOrganization(SimpleXMLElement $manifest, array $resources): array {
        $organizations = $manifest->xpath('//*[local-name()="organizations"]') ?: [];
        $default = '';

        if ($organizations !== []) {
            $default = trim((string) ($organizations[0]['default'] ?? ''));
        }

        $chosen = null;
        foreach ($manifest->xpath('//*[local-name()="organization"]') ?: [] as $organization) {
            $identifier = trim((string) ($organization['identifier'] ?? ''));

            if ($chosen === null || ($default !== '' && $identifier === $default)) {
                $chosen = $organization;
            }

            if ($default !== '' && $identifier === $default) {
                break;
            }
        }

        if ($chosen === null) {
            return ['', []];
        }

        $title = '';
        foreach ($chosen->xpath('./*[local-name()="title"]') ?: [] as $node) {
            $title = trim((string) $node);
            break;
        }

        $items = [];
        foreach ($chosen->xpath('.//*[local-name()="item"]') ?: [] as $item) {
            $ref = trim((string) ($item['identifierref'] ?? ''));
            $resource = $resources[$ref] ?? null;

            $itemTitle = '';
            foreach ($item->xpath('./*[local-name()="title"]') ?: [] as $node) {
                $itemTitle = trim((string) $node);
                break;
            }

            $items[] = [
                'identifier' => trim((string) ($item['identifier'] ?? '')),
                'title' => $itemTitle,
                'href' => $resource['href'] ?? null,
                'is_sco' => (bool) ($resource['is_sco'] ?? false),
            ];
        }

        return [$title, $items];
    }
}
