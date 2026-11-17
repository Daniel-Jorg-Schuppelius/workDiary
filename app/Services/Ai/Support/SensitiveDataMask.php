<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SensitiveDataMask.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Support;

use App\Enums\Ai\AiMaskKind;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\{AiClassificationResult, AiExtractionResult, AiFindResult, AiTextResult, AiTranslationResult};
use Closure;
use CommonToolkit\Helper\Data\{BankHelper, EmailHelper, PhoneNumberHelper, TaxNumberHelper, VatNumberHelper};
use CommonToolkit\ValueObjects\{GermanTaxId, GermanTaxNumber, VatNumber};

/**
 * Maskierung einer KI-Anfrage (Feature 025, MVP-1671): ersetzt Werte, die
 * ohne Stammdaten erkennbar sind — IBAN, USt-IdNr., Steuernummer,
 * Steuer-Identifikationsnummer, E-Mail-Adresse, Telefonnummer — durch
 * Platzhalter wie `[IBAN-1]` oder `[EMAIL-2]`, bevor der Text den Anbieter
 * erreicht, und setzt die eigenen Platzhalter in der Antwort wieder ein.
 *
 * Eine Instanz gilt für genau eine Anfrage: Gleicher Wert ergibt in allen
 * Feldern der Anfrage denselben Platzhalter (Normalform je Art), gezählt
 * wird je Art. Die Zuordnung liegt nur im Speicher — nie in Log, Audit oder
 * Cache ({@see counts()} liefert nur Anzahlen).
 *
 * Erkannt wird zweistufig: ein Suchmuster findet Kandidaten (eng gefasst, mit
 * Grenzen gegen Beträge mit Nachkommastellen, Datumsangaben und längere
 * Ziffernfolgen), die Prüfung übernimmt das common-toolkit (Prüfziffern von
 * IBAN, USt-IdNr. und Steuer-ID; Formate von Steuernummer, E-Mail, Telefon).
 * Das Suchen in Freitext mit Fundstelle bietet das Toolkit außer für IBANs
 * nicht — Kandidat für eine Toolkit-Erweiterung.
 *
 * Die Kundennamen maskiert weiterhin der {@see CustomerNameMasker} vor dem
 * Aufbau der Anfrage; er braucht den Kundenstamm und ersetzt unumkehrbar.
 */
final class SensitiveDataMask {
    /** Platzhalter in Anfrage und Antwort — tolerant gegen Leerzeichen und Groß-/Kleinschreibung der Antwort. */
    public const string PLACEHOLDER_PATTERN = '/\[\s*(IBAN|VATID|TAXNO|TAXID|EMAIL|PHONE)\s*-\s*(\d{1,4})\s*\]/i';

    private const string EMAIL_PATTERN = '/(?<![A-Za-z0-9._%+\-])[A-Za-z0-9][A-Za-z0-9._%+\-]*@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,24}(?![A-Za-z0-9\-])/';

    /** Länderkürzel aller USt-IdNr.-Formate des Toolkits; `ATU` vor `AT`. */
    private const string VAT_PATTERN = '/(?<![A-Za-z0-9])(?:ATU|CHE|AT|BE|BG|CY|CZ|DE|DK|EE|EL|ES|FI|FR|GB|HR|HU|IE|IT|LT|LU|LV|MT|NL|NO|PL|PT|RO|SE|SI|SK|XI)[ .\-]?[0-9A-Z]+(?:[ .\-][0-9A-Z]+){0,4}/';

    /** Landesformate mit Schrägstrich: FF/BBB/UUUUP, FFF/BBB/UUUUP, FFF/BBBB/UUUP (NRW). */
    private const string TAX_NUMBER_SLASHED_PATTERN = '#(?<![\d/.,])(?:\d{2}/\d{3}/\d{5}|\d{3}/\d{3}/\d{5}|\d{3}/\d{4}/\d{4})(?![\d/]|[.,]\d)#';

    /**
     * Landesformate mit Leerzeichen (Hessen, Bremen, Schleswig-Holstein) und das
     * 13-stellige Bundesformat sind von Rufnummern und Artikelnummern nicht zu
     * unterscheiden — nur hinter einer Bezeichnung wie „Steuernummer“ oder „St.-Nr.“.
     */
    private const string TAX_NUMBER_LABELLED_PATTERN = '/(\b(?:Steuernummer|Steuer-Nr\.?|St\.? ?-? ?Nr\.?|StNr\.?)[ \t]*:?[ \t]*)(\d[\d \/]{8,18}\d)(?!\d|[.,]\d)/iu';

    /** International mit `+` oder `00`, optional „(0)“ vor der Vorwahl. */
    private const string PHONE_INTERNATIONAL_PATTERN = '/(?<![\w+])(?:\+|00)[1-9]\d{0,2}(?:[ \-\/]?\(0\))?(?:[ \-\/]?\d){5,14}(?!\d|[.,]\d)/';

    /** National mit Vorwahl ab 0, auch „(030) …“ — Trennzeichen nur Leerzeichen, Bindestrich, Schrägstrich (kein Punkt: Datum). */
    private const string PHONE_NATIONAL_PATTERN = '/(?<![\w+\/.,\-])(?:\(0[1-9]\d{1,4}\) ?|0[1-9]\d{1,4}[ \-\/]?)\d(?:[ \-]?\d){3,9}(?!\d|[.,]\d)/';

    /** Elf Ziffern, erste nicht 0, üblich als 2-3-3-3 gruppiert. */
    private const string TAX_ID_PATTERN = '/(?<![\w+\/.,\-])[1-9]\d(?: ?\d{3}){3}(?!\d|[.,]\d)/';

    /** Trennstellen, an denen ein überlanger Treffer gekürzt wird. */
    private const string CUT_PATTERN = '/[ .\/\-](?=[0-9A-Za-z(+])/';

    /**
     * Mindestlänge der Nummer hinter dem Länderkürzel: Rumänien erlaubt laut
     * Toolkit zwei Ziffern, „RO12“ in einem Fließtext ist aber selten eine USt-IdNr.
     */
    private const int VAT_MIN_NUMBER_LENGTH = 8;

    /** Höchstzahl der Suchrunden für IBANs ({@see maskIbans()}). */
    private const int IBAN_ROUNDS = 20;

    /** @var array<string, string> Platzhalter → Originalschreibweise (erste Fundstelle) */
    private array $originals = [];

    /** @var array<string, string> „Art|Normalform“ → Platzhalter */
    private array $byValue = [];

    /** @var array<string, int> Kürzel → zuletzt vergebene Nummer */
    private array $counters = [];

    /** @var array<string, true> Platzhalter, die schon im Eingabetext standen — nie vergeben, nie ersetzt */
    private array $reserved = [];

    public function mask(string $text): string {
        if (trim($text) === '') {
            return $text;
        }

        $this->reserveExisting($text);

        $text = $this->maskIbans($text);
        $text = $this->maskEmails($text);
        $text = $this->replaceValid($text, self::VAT_PATTERN, AiMaskKind::VatId, static function (string $part): ?string {
            $vat = VatNumber::tryFrom($part);

            return $vat !== null && strlen(VatNumberHelper::extractNumber($vat->getValue())) >= self::VAT_MIN_NUMBER_LENGTH ? $vat->getValue() : null;
        });
        $text = $this->replaceValid($text, self::TAX_NUMBER_SLASHED_PATTERN, AiMaskKind::TaxNumber, static fn (string $part): ?string => GermanTaxNumber::tryFrom($part)?->getValue());
        $text = $this->maskLabelledTaxNumbers($text);
        $text = $this->replaceValid($text, self::PHONE_INTERNATIONAL_PATTERN, AiMaskKind::Phone, static fn (string $part): ?string => PhoneNumberHelper::toE164(str_replace('(0)', '', $part)));
        $text = $this->replaceValid($text, self::PHONE_NATIONAL_PATTERN, AiMaskKind::Phone, static fn (string $part): ?string => PhoneNumberHelper::isGermanPhoneNumber($part) ? PhoneNumberHelper::toE164($part) : null);

        return $this->replaceValid($text, self::TAX_ID_PATTERN, AiMaskKind::TaxId, static fn (string $part): ?string => GermanTaxId::tryFrom($part)?->getValue());
    }

    /** Kopie der Anfrage, deren Freitexte maskiert sind (gemeinsame Zuordnung über alle Felder). */
    public function maskRequest(AiRequestInterface $request): AiRequestInterface {
        return $request->mapTexts(fn (string $text): string => $this->mask($text));
    }

    /** Setzt die eigenen Platzhalter wieder ein; fremde oder erfundene bleiben stehen. */
    public function restore(string $text): string {
        if ($this->originals === []) {
            return $text;
        }

        return preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn (array $m): string => $this->originals[self::placeholder(strtoupper($m[1]), (int) $m[2])] ?? $m[0],
            $text,
        ) ?? $text;
    }

    /**
     * Rückersetzung im Ergebnis, wo es Text weitergibt: Entwurf, Zusammenfassung
     * und Erklärung (Text), Übersetzung, Feldwerte einer Extraktion.
     * Klassifikation und Suche liefern Katalogwerte bzw. Verweise.
     */
    public function restoreResult(AiTextResult|AiClassificationResult|AiFindResult|AiTranslationResult|AiExtractionResult $result): AiTextResult|AiClassificationResult|AiFindResult|AiTranslationResult|AiExtractionResult {
        if ($this->originals === []) {
            return $result;
        }

        return match (true) {
            $result instanceof AiTextResult => new AiTextResult($this->restore($result->text), $result->usage),
            $result instanceof AiTranslationResult => new AiTranslationResult(
                $this->restore($result->text),
                $result->deterministicTerminology,
                $result->detectedSourceLanguage,
                $result->usage,
            ),
            $result instanceof AiExtractionResult => new AiExtractionResult(
                array_map(fn (?string $value): ?string => $value === null ? null : $this->restore($value), $result->values),
                $result->confidence,
                $result->usage,
            ),
            default => $result,
        };
    }

    public function isEmpty(): bool {
        return $this->originals === [];
    }

    /**
     * Anzahl verschiedener maskierter Werte je Art — ohne Klartext, für Audit und Anzeige.
     *
     * @return array<string, int>
     */
    public function counts(): array {
        $counts = [];
        foreach (array_keys($this->byValue) as $key) {
            $kind = explode('|', $key, 2)[0];
            $counts[$kind] = ($counts[$kind] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /** Enthält der Text einen Platzhalter dieser Maskierung (Prompt-Hinweis der Sprachmodelle)? */
    public static function containsPlaceholder(string $text): bool {
        return preg_match(self::PLACEHOLDER_PATTERN, $text) === 1;
    }

    /**
     * IBANs findet und prüft das Toolkit (Prüfziffer, Länderlänge, auch in
     * Vierergruppen); ersetzt wird jede Schreibweise mit und ohne Leerzeichen,
     * auch klein geschrieben (das Suchmuster des Toolkits kennt nur Großbuchstaben,
     * `strtoupper` ändert nur ASCII und damit keine Position).
     *
     * Mehrere Runden: Das leerzeichentolerante Suchmuster des Toolkits greift über
     * die IBAN hinaus in folgende Großbuchstaben-Wörter und verschluckt so eine
     * zweite IBAN („… 1100 AN AT61 …“). Ist die erste ersetzt, findet die nächste
     * Runde die zweite.
     */
    private function maskIbans(string $text): string {
        for ($round = 0; $round < self::IBAN_ROUNDS; $round++) {
            $ibans = BankHelper::extractIBANs(strtoupper($text), strict: true, spaceTolerant: true);
            if ($ibans === []) {
                break;
            }
            foreach ($ibans as $iban) {
                $pattern = '/(?<![0-9])' . implode(' ?', str_split($iban)) . '(?![0-9])/i';
                $text = preg_replace_callback(
                    $pattern,
                    fn (array $m): string => $this->allocate(AiMaskKind::Iban, $iban, $m[0]),
                    $text,
                ) ?? $text;
            }
        }

        return $text;
    }

    private function maskEmails(string $text): string {
        return preg_replace_callback(
            self::EMAIL_PATTERN,
            fn (array $m): string => EmailHelper::isEmail($m[0]) ? $this->allocate(AiMaskKind::Email, EmailHelper::normalize($m[0]), $m[0]) : $m[0],
            $text,
        ) ?? $text;
    }

    /** Steuernummer hinter ihrer Bezeichnung; die Bezeichnung bleibt stehen. */
    private function maskLabelledTaxNumbers(string $text): string {
        $normalize = static function (string $part): ?string {
            $digits = strlen(TaxNumberHelper::normalize($part));

            return $digits >= 10 && $digits <= 13 ? GermanTaxNumber::tryFrom($part)?->getValue() : null;
        };

        return preg_replace_callback(self::TAX_NUMBER_LABELLED_PATTERN, function (array $m) use ($normalize): string {
            $found = $this->longestValidPrefix($m[2], $normalize);
            if ($found === null) {
                return $m[0];
            }
            [$part, $normalized] = $found;

            return $m[1] . $this->allocate(AiMaskKind::TaxNumber, $normalized, $part) . substr($m[2], strlen($part));
        }, $text) ?? $text;
    }

    /**
     * Ersetzt Treffer, die die Prüfung bestehen. Ein Treffer darf über den Wert
     * hinausreichen (Folgewort wie „MWST“, eine zweite Nummer): Es gilt der
     * längste Anfang, der an einer Trennstelle endet, der Rest wird erneut
     * durchsucht. Besteht kein Anfang, wird hinter der ersten Gruppe weitergesucht.
     *
     * @param  Closure(string): ?string  $normalize  Normalform des gültigen Werts, sonst null
     */
    private function replaceValid(string $text, string $pattern, AiMaskKind $kind, Closure $normalize): string {
        return preg_replace_callback($pattern, function (array $m) use ($pattern, $kind, $normalize): string {
            $candidate = $m[0];
            $found = $this->longestValidPrefix($candidate, $normalize);
            if ($found === null) {
                $firstCut = $this->cutPoints($candidate)[0] ?? null;

                return $firstCut === null
                    ? $candidate
                    : substr($candidate, 0, $firstCut) . $this->replaceValid(substr($candidate, $firstCut), $pattern, $kind, $normalize);
            }
            [$part, $normalized] = $found;
            $rest = substr($candidate, strlen($part));

            return $this->allocate($kind, $normalized, $part) . ($rest === '' ? '' : $this->replaceValid($rest, $pattern, $kind, $normalize));
        }, $text) ?? $text;
    }

    /**
     * @param  Closure(string): ?string  $normalize
     * @return array{0: string, 1: string}|null  gültiger Anfang und seine Normalform
     */
    private function longestValidPrefix(string $candidate, Closure $normalize): ?array {
        foreach ([strlen($candidate), ...array_reverse($this->cutPoints($candidate))] as $cut) {
            $part = substr($candidate, 0, $cut);
            $normalized = $normalize($part);
            if ($normalized !== null && $normalized !== '') {
                return [$part, $normalized];
            }
        }

        return null;
    }

    /** @return list<int> Byte-Positionen der Trennstellen, aufsteigend */
    private function cutPoints(string $candidate): array {
        if (preg_match_all(self::CUT_PATTERN, $candidate, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (array $match): int => (int) $match[1], $matches[0]),
            static fn (int $offset): bool => $offset > 0,
        ));
    }

    /** Platzhalter für einen Wert — derselbe für dieselbe Normalform. */
    private function allocate(AiMaskKind $kind, string $normalized, string $original): string {
        $valueKey = $kind->value . '|' . $normalized;
        if (isset($this->byValue[$valueKey])) {
            return $this->byValue[$valueKey];
        }

        $token = $kind->token();
        $number = $this->counters[$token] ?? 0;
        do {
            $placeholder = self::placeholder($token, ++$number);
        } while (isset($this->reserved[$placeholder]));

        $this->counters[$token] = $number;
        $this->byValue[$valueKey] = $placeholder;
        $this->originals[$placeholder] = $original;

        return $placeholder;
    }

    /** Platzhalter, die schon im Text stehen, bleiben fremd: weder vergeben noch zurückersetzt. */
    private function reserveExisting(string $text): void {
        if (preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches, PREG_SET_ORDER) < 1) {
            return;
        }
        foreach ($matches as $match) {
            $placeholder = self::placeholder(strtoupper($match[1]), (int) $match[2]);
            if (! isset($this->originals[$placeholder])) {
                $this->reserved[$placeholder] = true;
            }
        }
    }

    private static function placeholder(string $token, int $number): string {
        return '[' . $token . '-' . $number . ']';
    }
}
