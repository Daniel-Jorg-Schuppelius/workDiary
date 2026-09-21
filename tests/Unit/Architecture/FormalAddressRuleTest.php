<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormalAddressRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate (MVP-828): Die Oberfläche spricht förmlich an — Deutsch
 * „Sie", Französisch „vous", Spanisch „usted", Italienisch „Lei".
 *
 * Deutsch: Pronomen, Du-Verbformen und informelle Imperative am Satzanfang in
 * allen Quelltexten (JSON-Schlüssel), lang/de/*.php, lang/de.json, den
 * Hilfethemen und Rohtext der Views. FR/ES/IT: eindeutige Du-Pronomen in
 * Übersetzungen und Hilfe — Imperative sind dort mit der 3. Person gleichlautend
 * („Añade" = „füge hinzu" oder „fügt hinzu") und bleiben der Durchsicht vorbehalten.
 *
 * Nicht erfasst: KI-Prompts in app/Services/Ai (sprechen das Modell an, nicht Nutzer).
 */
class FormalAddressRuleTest extends TestCase {
    use ScansSourceTree;

    private const DE_PRONOUN = '(?:du|dich|dir|dein|deine|deinen|deinem|deiner|deines|euch|euer|eure|euren|eurem|eurer|eures)';

    private const DE_VERB = '(?:kannst|musst|hast|bist|willst|solltest|darfst|wirst|siehst|findest|brauchst|möchtest|weißt|bekommst|erhältst|gibst|nimmst|kriegst|änderst|klickst|wählst|legst|speicherst|trägst|arbeitest|buchst|lädst|meldest|bestätigst|erstellst|öffnest|gehst|kommst|stellst|hättest|wärst|würdest|könntest|müsstest)';

    private const DE_IMPERATIVE = '(?:Wähle|Klicke|Gib|Lege|Leg|Prüfe|Nutze|Speichere|Trage|Füge|Öffne|Schließe|Melde|Lade|Bestätige|Ändere|Lösche|Erstelle|Setze|Aktiviere|Deaktiviere|Hinterlege|Ergänze|Beachte|Achte|Kontaktiere|Wende|Richte|Verknüpfe|Ordne|Markiere|Filtere|Exportiere|Importiere|Sende|Schicke|Scanne|Tippe|Ziehe|Gehe|Geh|Sieh|Schau|Lies|Nimm|Hilf|Vergiss|Erfasse|Bearbeite|Verwende|Wiederhole|Kopiere|Überprüfe|Vergib|Informiere|Drucke|Unterschreibe|Fülle|Entferne|Mach|Mache|Halte|Behalte|Definiere|Wechsle|Logge|Registriere|Pflege|Aktualisiere|Übernimm|Übertrage|Verschiebe|Sortiere|Gruppiere|Benenne|Erzeuge|Generiere|Rufe|Schreibe|Antworte|Beschreibe|Bewerte|Kläre|Probiere|Vergleiche|Beantworte|Reiche|Erteile|Verwirf|Fordere|Erneuere)';

    private const DE_START = '(?:^|[.!?:;»„"(]\s*|\bbitte\s+|\bBitte\s+|\n\s*[-*]?\s*|\d\.\s+)';

    /** Substantive, die nur mit typischem Objekt als Imperativ gelten („Weise jede ID zu"). */
    private const DE_AMBIGUOUS = '(?:Weise|Teile|Suche|Starte|Lass|Zeige|Blende|Hole|Warte|Frage|Denk|Teste)\s+(?:den|die|das|dem|der|ein|eine|einen|einem|jede|jeden|jedes|sie|ihn|es|dich|dir|mich|uns|hier|oben|unten|zunächst|zuerst|dazu|bitte|einfach|auf|in|im)\b';

    /** @var array<string, string> */
    private const ROMANCE_PRONOUN = [
        // ton/ta/tes nur vor einem Wort — „Ton" allein ist der Farbton.
        'fr' => "(?<![\\w'’-])(?:tu|toi|te|tien|tienne)(?![\\w-])|(?<![\\w'’-])(?:ton|ta|tes)\\s+\\p{L}|\\b(?:as|es|peux|dois|veux|sais|vas|fais)-tu\\b|-toi\\b",
        // „ti“ nur klein — „TI“ ist die spanische Abkürzung für IT.
        'es' => '(?<![\wáéíóúñ-])(?:tú|tu|tus|te|(?-i:ti)|contigo|tuyo|tuya|tuyos|tuyas|asegúrate|inténtalo)(?![\wáéíóúñ-])',
        'it' => "(?<![\\wàèéìòù'’-])(?:tu|tuo|tua|tuoi|tue|(?-i:ti)|assicurati)(?![\\wàèéìòù-])",
    ];

    public function test_german_texts_use_formal_address(): void {
        $violations = [];
        foreach ($this->germanTexts() as $where => $text) {
            if ($this->isInformalGerman($text)) {
                $violations[] = $where . ': ' . mb_substr(trim($text), 0, 120);
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Du-Form in deutschen Texten — förmlich formulieren („Wählen Sie …\", „Ihr Konto\"):\n" . implode("\n", $violations));
    }

    public function test_romance_translations_use_formal_address(): void {
        $violations = [];
        foreach (self::ROMANCE_PRONOUN as $locale => $pattern) {
            $regex = '~' . $pattern . '~iu';
            foreach ($this->translations($locale) as $where => $text) {
                if (preg_match($regex, $this->stripPlaceholders($text)) === 1) {
                    $violations[] = $where . ': ' . mb_substr(trim($text), 0, 120);
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Informelle Anrede in Übersetzungen — FR „vous\", ES „usted\", IT „Lei\":\n" . implode("\n", $violations));
    }

    private function isInformalGerman(string $text): bool {
        $text = $this->stripPlaceholders($text);
        $text = (string) preg_replace('~Dir\s+(?:Listing|Sync)|\bdu\s+(?:jour|mois|client)\b|\bdir=~i', '', $text);

        return preg_match('~(?<![\w-])(?:' . self::DE_PRONOUN . '|' . self::DE_VERB . ')(?![\w-])~iu', $text) === 1
            || preg_match('~' . self::DE_START . self::DE_IMPERATIVE . '(?![\wäöüß-])~mu', $text) === 1
            || preg_match('~' . self::DE_START . self::DE_AMBIGUOUS . '~mu', $text) === 1
            || preg_match('~\bStelle\s+(?:sicher|ein|zuerst|bitte)\b|\bVersuche(?:,|\s+(?:es|den|die|das|ein|eine|einen|zunächst|bitte|später|erneut)\b)~u', $text) === 1;
    }

    /** Platzhalter (:name), Code und URLs sind keine Anrede. */
    private function stripPlaceholders(string $text): string {
        return (string) preg_replace(['~`[^`]*`~', '~https?://\S+~', '~:[a-z_]+~i'], ' ', $text);
    }

    /** @return iterable<string, string> */
    private function germanTexts(): iterable {
        foreach (array_keys($this->json('en')) as $source) {
            yield 'Quelltext „' . mb_substr($source, 0, 40) . '…"' => (string) $source;
        }
        foreach ($this->json('de') as $key => $value) {
            yield "lang/de.json [{$key}]" => (string) $value;
        }
        foreach ($this->phpTranslations('de') as $where => $value) {
            yield $where => $value;
        }
        foreach ($this->helpLines('de') as $where => $line) {
            yield $where => $line;
        }
        foreach ([...$this->bladeFiles(), ...$this->bladeFiles('app/Plugins')] as $file) {
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $source = (string) preg_replace(['~\{\{.*?\}\}|\{!!.*?!!\}~s', '~<script\b.*?</script>|<style\b.*?</style>~s', '~@php\b.*?@endphp~s'], ' ', $source);
            if (preg_match_all('~>([^<>@]{3,})<~u', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[1] as [$text, $offset]) {
                    if (preg_match('~[a-zäöü]~u', $text) === 1) {
                        yield $this->relativePath($file) . ':' . $this->lineOf($source, (int) $offset) => $text;
                    }
                }
            }
        }
    }

    /** @return iterable<string, string> */
    private function translations(string $locale): iterable {
        foreach ($this->json($locale) as $key => $value) {
            yield "lang/{$locale}.json [" . mb_substr((string) $key, 0, 40) . ']' => (string) $value;
        }
        foreach ($this->phpTranslations($locale) as $where => $value) {
            yield $where => $value;
        }
        foreach ($this->helpLines($locale) as $where => $line) {
            yield $where => $line;
        }
    }

    /** @return array<string, mixed> */
    private function json(string $locale): array {
        $path = $this->repoRoot() . "/lang/{$locale}.json";

        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }

    /** @return iterable<string, string> */
    private function phpTranslations(string $locale): iterable {
        foreach (glob($this->repoRoot() . "/lang/{$locale}/*.php") ?: [] as $file) {
            $group = basename($file, '.php');
            // validation.attributes sind Feldnamen, keine Anrede.
            $data = (array) require $file;
            if ($group === 'validation') {
                unset($data['attributes']);
            }
            foreach ($this->flatten($data) as $key => $value) {
                yield "lang/{$locale}/{$group}.php [{$key}]" => $value;
            }
        }
    }

    /**
     * @param array<mixed> $data
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $out += $this->flatten($value, $path);
            } elseif (is_string($value)) {
                $out[$path] = $value;
            }
        }

        return $out;
    }

    /** @return iterable<string, string> */
    private function helpLines(string $locale): iterable {
        foreach (glob($this->repoRoot() . "/resources/help/{$locale}/*.md") ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
                yield $this->relativePath($file) . ':' . ($index + 1) => $line;
            }
        }
    }
}
