<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VCardContactMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

use Sabre\VObject\Component\VCard;
use Sabre\VObject\{Parameter, Property};

/**
 * Übersetzt eine vCard (3.0/4.0, sabre/vobject) in das workDiary-Kundenschema
 * für den {@see \App\Services\Integration\IntegrationResolver} (Bauturbo A9).
 *
 * - `mapped`: Wertesatz im lokalen Customer-Feldschema (Matching-Grundlage).
 * - `raw`: verlustfreier Snapshot (alle Mails/Telefone samt Typen) für die
 *   Inbox-Anzeige — der Mensch sieht bei der Zuordnung die volle Karte.
 *
 * WORK-getypte Mail/Telefon/Adresse hat Vorrang vor der Dokumentreihenfolge.
 * Das Land wird nur übernommen, wenn es bereits als ISO-2-Code vorliegt —
 * vCards führen Länder als Freitext („Deutschland"), der das country-Feld
 * verschmutzen würde (Original bleibt im raw-Snapshot erhalten).
 */
class VCardContactMapper {
    /**
     * @return array{uid: string, mapped: array<string, mixed>, raw: array<string, mixed>}
     */
    public function map(VCard $card, string $fallbackUid): array {
        $uid = trim((string) ($card->UID ?? ''));
        if ($uid === '') {
            $uid = $fallbackUid;
        }

        $name = $this->displayName($card);
        $company = $this->company($card);
        $emails = $this->typedValues($card, 'EMAIL');
        $phones = $this->typedValues($card, 'TEL');
        $address = $this->address($card);
        $note = trim((string) ($card->NOTE ?? ''));

        $mapped = array_filter([
            'name' => $name !== '' ? $name : null,
            'company' => $company !== '' ? $company : null,
            'email' => $this->primary($emails),
            'phone' => $this->phoneByType($phones, ['voice', 'work'], exclude: ['cell', 'fax']),
            'mobile' => $this->phoneByType($phones, ['cell']),
            'fax' => $this->phoneByType($phones, ['fax']),
            'comment' => $note !== '' ? $note : null,
            'address_street' => $address['street'] ?? null,
            'address_zip' => $address['zip'] ?? null,
            'address_city' => $address['city'] ?? null,
            'country' => $this->isoCountry($address['country'] ?? ''),
        ], static fn($v) => $v !== null);

        $raw = array_filter([
            'uid' => $uid,
            'fn' => $name,
            'org' => $company,
            'emails' => $emails,
            'phones' => $phones,
            'address' => $address,
            'note' => $note !== '' ? $note : null,
        ], static fn($v) => $v !== null && $v !== '' && $v !== []);

        return ['uid' => $uid, 'mapped' => $mapped, 'raw' => $raw];
    }

    /** FN, sonst aus N (given + family) zusammengesetzt. */
    private function displayName(VCard $card): string {
        $fn = trim((string) ($card->FN ?? ''));
        if ($fn !== '') {
            return $fn;
        }

        $n = $card->N ?? null;
        if (! $n instanceof Property) {
            return '';
        }
        $parts = $n->getParts(); // [family, given, additional, prefix, suffix]

        return trim(((string) ($parts[1] ?? '')) . ' ' . ((string) ($parts[0] ?? '')));
    }

    /** Erste ORG-Komponente (Organisationsname ohne Abteilungs-Zusätze). */
    private function company(VCard $card): string {
        $org = $card->ORG ?? null;
        if (! $org instanceof Property) {
            return '';
        }
        $parts = $org->getParts();

        return trim((string) ($parts[0] ?? ''));
    }

    /**
     * Alle Werte einer Property samt (lowercase) TYPE-Parametern,
     * WORK-getypte zuerst (stabile Reihenfolge im Übrigen).
     *
     * @return list<array{value: string, types: list<string>}>
     */
    private function typedValues(VCard $card, string $property): array {
        $out = [];
        foreach ($card->select($property) as $prop) {
            if (! $prop instanceof Property) {
                continue;
            }
            $value = trim((string) $prop);
            if ($value === '') {
                continue;
            }
            $out[] = ['value' => $value, 'types' => $this->typeParams($prop)];
        }

        usort($out, static function (array $a, array $b): int {
            return (int) in_array('work', $b['types'], true) <=> (int) in_array('work', $a['types'], true);
        });

        return $out;
    }

    /**
     * Lowercase-TYPE-Parameter einer Property (vCard 3.0 wie 4.0).
     *
     * @return list<string>
     */
    private function typeParams(Property $prop): array {
        $param = $prop['TYPE'] ?? null;
        if (! $param instanceof Parameter) {
            return [];
        }

        return array_values(array_map(
            static fn($t): string => strtolower((string) $t),
            $param->getParts(),
        ));
    }

    /**
     * @param  list<array{value: string, types: list<string>}>  $values
     */
    private function primary(array $values): ?string {
        return $values[0]['value'] ?? null;
    }

    /**
     * Erste Telefonnummer, deren Typ auf `$wanted` passt (bzw. typlose Nummern
     * für die Standard-Rufnummer), unter Ausschluss von `$exclude`.
     *
     * @param  list<array{value: string, types: list<string>}>  $phones
     * @param  list<string>  $wanted
     * @param  list<string>  $exclude
     */
    private function phoneByType(array $phones, array $wanted, array $exclude = []): ?string {
        foreach ($phones as $phone) {
            if (array_intersect($exclude, $phone['types']) !== []) {
                continue;
            }
            if (array_intersect($wanted, $phone['types']) !== []) {
                return $phone['value'];
            }
        }

        // Fallback für die Standard-Rufnummer: erste Nummer ohne Spezial-Typ.
        if (in_array('voice', $wanted, true)) {
            foreach ($phones as $phone) {
                if (array_intersect($exclude, $phone['types']) === []) {
                    return $phone['value'];
                }
            }
        }

        return null;
    }

    /**
     * WORK-Adresse bevorzugt, sonst die erste. ADR-Komponenten laut RFC 6350:
     * [pobox, extended, street, locality, region, code, country].
     *
     * @return array{street?: string, city?: string, region?: string, zip?: string, country?: string}
     */
    private function address(VCard $card): array {
        $candidates = [];
        foreach ($card->select('ADR') as $adr) {
            if (! $adr instanceof Property) {
                continue;
            }
            $candidates[] = ['parts' => $adr->getParts(), 'work' => in_array('work', $this->typeParams($adr), true)];
        }
        if ($candidates === []) {
            return [];
        }

        usort($candidates, static fn(array $a, array $b): int => (int) $b['work'] <=> (int) $a['work']);
        $parts = $candidates[0]['parts'];
        $component = static fn(int $i): string => trim((string) ($parts[$i] ?? ''));

        return array_filter([
            'street' => $component(2),
            'city' => $component(3),
            'region' => $component(4),
            'zip' => $component(5),
            'country' => $component(6),
        ], static fn(string $v): bool => $v !== '');
    }

    /** Land nur als ISO-2-Code übernehmen (vCard-Länder sind oft Freitext). */
    private function isoCountry(string $country): ?string {
        $country = trim($country);

        return preg_match('/^[A-Za-z]{2}$/', $country) === 1 ? strtoupper($country) : null;
    }
}
