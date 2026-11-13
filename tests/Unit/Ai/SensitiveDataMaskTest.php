<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SensitiveDataMaskTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Dto\{AiClassificationResult, AiExtractionResult, AiTextResult, AiTranslationResult, AiUsage, ExamplePair, ExplainRequest, FindRequest, FormulateRequest, SummarizeRequest, TranslateRequest};
use App\Services\Ai\Support\SensitiveDataMask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Maskierung vor dem KI-Anbieter (Feature 025, MVP-1671). Alle Werte sind
 * erfunden, aber prüfziffergültig (mit dem common-toolkit erzeugt): IBANs aus
 * ausgedachten Bankleitzahlen und Konten, Steuer-IDs und USt-IdNr. per
 * Zufall mit Prüfziffer, Rufnummern aus dem für Film und Fernsehen
 * reservierten Berliner Block 030 23125.
 */
class SensitiveDataMaskTest extends TestCase {
    private const string IBAN = 'DE19120300001001234567';

    private const string IBAN_SPACED = 'DE19 1203 0000 1001 2345 67';

    /** Gleiche Bankdaten, letzte Prüfziffer verfälscht. */
    private const string IBAN_WRONG_CHECK = 'DE18 1203 0000 1001 2345 67';

    private const string IBAN_SECOND = 'DE85760260004433221100';

    private const string IBAN_AT = 'AT611904300234573201';

    private const string VAT_DE = 'DE800387175';

    private const string VAT_DE_SECOND = 'DE151328517';

    private const string VAT_AT = 'ATU76815699';

    private const string TAX_ID = '51656094265';

    private const string TAX_ID_SECOND = '67647648482';

    public function test_iban_with_and_without_spaces_gets_one_stable_placeholder(): void {
        $mask = new SensitiveDataMask();

        $masked = $mask->mask('Bitte an ' . self::IBAN_SPACED . ' BIC BYLADEM1001 überweisen, gleiche IBAN ' . self::IBAN . '.');

        $this->assertSame('Bitte an [IBAN-1] BIC BYLADEM1001 überweisen, gleiche IBAN [IBAN-1].', $masked);
        $this->assertSame(['iban' => 1], $mask->counts());
    }

    public function test_iban_with_wrong_check_digit_stays(): void {
        $text = 'Falsche Prüfziffer: ' . self::IBAN_WRONG_CHECK . ' bleibt stehen.';

        $this->assertSame($text, (new SensitiveDataMask())->mask($text));
    }

    public function test_lowercase_iban_and_foreign_iban_are_masked(): void {
        $mask = new SensitiveDataMask();

        $masked = $mask->mask('Von ' . strtolower(self::IBAN_SECOND) . ' an ' . self::IBAN_AT . '.');

        $this->assertSame('Von [IBAN-1] an [IBAN-2].', $masked);
    }

    public function test_placeholders_are_numbered_per_kind_and_stable_across_texts_of_one_request(): void {
        $mask = new SensitiveDataMask();

        $first = $mask->mask('Mail an anna.beispiel@example.org, IBAN ' . self::IBAN);
        $second = $mask->mask('Nochmal ANNA.Beispiel@example.org und bert@example.net, IBAN ' . self::IBAN_SECOND);

        $this->assertSame('Mail an [EMAIL-1], IBAN [IBAN-1]', $first);
        $this->assertSame('Nochmal [EMAIL-1] und [EMAIL-2], IBAN [IBAN-2]', $second);
        $this->assertSame(['email' => 2, 'iban' => 2], $mask->counts());
    }

    public function test_vat_ids_with_check_digit_are_masked_and_trailing_words_stay(): void {
        $mask = new SensitiveDataMask();

        $masked = $mask->mask('USt-IdNr. DE 800 387 175, außerdem ' . self::VAT_DE_SECOND . ' ' . self::VAT_AT . ' und CHE-123.456.789 MWST.');

        $this->assertSame('USt-IdNr. [VATID-1], außerdem [VATID-2] [VATID-3] und [VATID-4].', $masked);
        $this->assertSame('Gleiche Nummer: [VATID-1]', $mask->mask('Gleiche Nummer: ' . self::VAT_DE));
    }

    public function test_vat_id_with_wrong_check_digit_and_capital_words_stay(): void {
        $text = 'Falsch: DE800387176. Kein Kennzeichen: RO12, SEPA, DESIGN, IT 2026.';

        $this->assertSame($text, (new SensitiveDataMask())->mask($text));
    }

    public function test_tax_id_with_check_digit_is_masked_grouped_and_plain(): void {
        $mask = new SensitiveDataMask();

        $masked = $mask->mask('Steuer-ID 51 656 094 265, Ehepartner ' . self::TAX_ID_SECOND . ', nochmal ' . self::TAX_ID . '.');

        $this->assertSame('Steuer-ID [TAXID-1], Ehepartner [TAXID-2], nochmal [TAXID-1].', $masked);
    }

    public function test_eleven_digits_with_wrong_check_digit_stay(): void {
        $text = 'Bestellnummer 51656094266 und 12345678901.';

        $this->assertSame($text, (new SensitiveDataMask())->mask($text));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function taxNumberFormats(): array {
        return [
            'Hamburg FF/BBB/UUUUP' => ['Steuer 21/815/08150 laut Bescheid', 'Steuer [TAXNO-1] laut Bescheid'],
            'Bayern FFF/BBB/UUUUP' => ['StNr. 181/815/08155.', 'StNr. [TAXNO-1].'],
            'NRW FFF/BBBB/UUUP' => ['Bescheid zu 133/8150/8159', 'Bescheid zu [TAXNO-1]'],
            'Hessen 0FF BBB UUUUP mit Bezeichnung' => ['Steuernummer: 013 815 08153', 'Steuernummer: [TAXNO-1]'],
            'Bremen FF BBB UUUUP mit Bezeichnung' => ['St.-Nr. 75 815 08152 (Bremen)', 'St.-Nr. [TAXNO-1] (Bremen)'],
            'Bundesformat 13-stellig mit Bezeichnung' => ['Steuer-Nr. 9181081508155 2026', 'Steuer-Nr. [TAXNO-1] 2026'],
        ];
    }

    #[DataProvider('taxNumberFormats')]
    public function test_tax_number_state_formats(string $text, string $expected): void {
        $this->assertSame($expected, (new SensitiveDataMask())->mask($text));
    }

    public function test_unlabelled_thirteen_digits_stay(): void {
        // Ohne Bezeichnung nicht von einer GTIN zu unterscheiden.
        $text = 'Artikel 4006381333931 und 9181081508155';

        $this->assertSame($text, (new SensitiveDataMask())->mask($text));
    }

    public function test_phone_numbers_national_and_international(): void {
        $mask = new SensitiveDataMask();

        $masked = $mask->mask('Tel. 030 23125456, Fax 030/23125457, mobil 0171-2345678, (030) 23125458, +49 (0)30 23125-456, 0049 30 23125456, Paris +33 1 23 45 67 89.');

        $this->assertSame('Tel. [PHONE-1], Fax [PHONE-3], mobil [PHONE-4], [PHONE-5], [PHONE-1], [PHONE-1], Paris [PHONE-2].', $masked);
        $this->assertSame(['phone' => 5], $mask->counts());
    }

    public function test_phone_followed_by_other_number_masks_only_the_phone(): void {
        $this->assertSame('Ruf [PHONE-1] 2026 an.', (new SensitiveDataMask())->mask('Ruf +49 30 23125456 2026 an.'));
    }

    public function test_amounts_dates_and_document_numbers_stay(): void {
        $text = 'Betrag 1.234,56 €, 12.345.678,90 EUR, +1234567,89 €, 0,50 €, 12345678901,50 €, am 01.02.2026, 2026-11-12, '
            . '14:30 Uhr, KW 49/2026, 12/05/2026, PLZ 01067 Dresden, Rechnung RE-2026-00123, Zeitraum 01/2026 - 03/2026, '
            . 'Menge 12 345, +20 % Aufschlag, Kundennummer 10012345, Auftrag 1234567890.';

        $mask = new SensitiveDataMask();

        $this->assertSame($text, $mask->mask($text));
        $this->assertTrue($mask->isEmpty());
    }

    public function test_restore_replaces_only_own_placeholders_and_tolerates_spacing(): void {
        $mask = new SensitiveDataMask();
        $mask->mask('IBAN ' . self::IBAN . ', Mail anna@example.org');

        $restored = $mask->restore('Antwort: [iban-1], [ EMAIL - 1 ], erfunden [IBAN-7], fremd [PHONE-1].');

        $this->assertSame('Antwort: ' . self::IBAN . ', anna@example.org, erfunden [IBAN-7], fremd [PHONE-1].', $restored);
    }

    public function test_placeholder_already_in_the_input_is_never_assigned_or_restored(): void {
        $mask = new SensitiveDataMask();

        $masked = $mask->mask('Vorlage mit [IBAN-1] wörtlich, echte IBAN ' . self::IBAN);

        $this->assertSame('Vorlage mit [IBAN-1] wörtlich, echte IBAN [IBAN-2]', $masked);
        $this->assertSame('Vorlage mit [IBAN-1] wörtlich, echte IBAN ' . self::IBAN, $mask->restore($masked));
    }

    public function test_html_keeps_its_structure(): void {
        $mask = new SensitiveDataMask();

        $masked = $mask->mask('<p>Kontakt: <a href="mailto:anna@example.net">anna@example.net</a></p>');

        $this->assertSame('<p>Kontakt: <a href="mailto:[EMAIL-1]">[EMAIL-1]</a></p>', $masked);
        $this->assertSame('<p>Kontakt: <a href="mailto:anna@example.net">anna@example.net</a></p>', $mask->restore($masked));
    }

    public function test_mask_request_covers_every_free_text_field_with_one_mapping(): void {
        $mask = new SensitiveDataMask();
        $request = new FormulateRequest(
            text: 'Rückruf an anna@example.org',
            styleRules: ['Nominalstil'],
            examples: [new ExamplePair('Mail an anna@example.org', 'E-Mail an anna@example.org gesendet')],
            contextHints: ['Konto ' . self::IBAN],
        );

        $masked = $mask->maskRequest($request);

        $this->assertInstanceOf(FormulateRequest::class, $masked);
        $this->assertSame('Rückruf an [EMAIL-1]', $masked->text);
        $this->assertSame('Mail an [EMAIL-1]', $masked->examples[0]->source);
        $this->assertSame(['Konto [IBAN-1]'], $masked->contextHints);
        $this->assertSame(['Nominalstil'], $masked->styleRules);
        $this->assertNotSame($request->fingerprint(), $masked->fingerprint());
    }

    public function test_mask_request_keeps_keys_numbers_and_control_values(): void {
        $mask = new SensitiveDataMask();

        $find = $mask->maskRequest(new FindRequest('Wer hat anna@example.org?', ['e-7' => 'Mail von anna@example.org']));
        $explain = $mask->maskRequest(new ExplainRequest(['kontakt' => 'Tel. 030 23125456', 'stunden' => 12.5], 'Was mit ' . self::IBAN . '?'));
        $summary = $mask->maskRequest(new SummarizeRequest(['Termin mit anna@example.org'], period: '01.11.–30.11.2026'));
        $translate = $mask->maskRequest(new TranslateRequest('Konto ' . self::IBAN, 'en', 'de', 'html', 'more'));

        $this->assertInstanceOf(FindRequest::class, $find);
        $this->assertSame(['e-7' => 'Mail von [EMAIL-1]'], $find->corpus);
        $this->assertInstanceOf(ExplainRequest::class, $explain);
        $this->assertSame(['kontakt' => 'Tel. [PHONE-1]', 'stunden' => 12.5], $explain->facts);
        $this->assertSame('Was mit [IBAN-1]?', $explain->question);
        $this->assertInstanceOf(SummarizeRequest::class, $summary);
        $this->assertSame('01.11.–30.11.2026', $summary->period);
        $this->assertInstanceOf(TranslateRequest::class, $translate);
        $this->assertSame(['Konto [IBAN-1]', 'en', 'de', 'html', 'more'], [$translate->text, $translate->targetLanguage, $translate->sourceLanguage, $translate->format, $translate->formality]);
    }

    public function test_restore_result_for_text_translation_and_extraction_but_not_classification(): void {
        $mask = new SensitiveDataMask();
        $mask->mask('IBAN ' . self::IBAN . ' von anna@example.org');
        $usage = new AiUsage();

        $text = $mask->restoreResult(new AiTextResult('Bitte auf [IBAN-1] zahlen.', $usage));
        $translation = $mask->restoreResult(new AiTranslationResult('Please pay to [IBAN-1].', true, 'de', $usage));
        $extraction = $mask->restoreResult(new AiExtractionResult(['iban' => '[IBAN-1]', 'email' => '[EMAIL-1]', 'due' => null], ['iban' => 90, 'email' => 80, 'due' => 0], $usage));
        $classification = new AiClassificationResult(['[IBAN-1]'], $usage);

        $this->assertInstanceOf(AiTextResult::class, $text);
        $this->assertSame('Bitte auf ' . self::IBAN . ' zahlen.', $text->text);
        $this->assertInstanceOf(AiTranslationResult::class, $translation);
        $this->assertSame('Please pay to ' . self::IBAN . '.', $translation->text);
        $this->assertTrue($translation->deterministicTerminology);
        $this->assertInstanceOf(AiExtractionResult::class, $extraction);
        $this->assertSame(['iban' => self::IBAN, 'email' => 'anna@example.org', 'due' => null], $extraction->values);
        $this->assertSame($classification, $mask->restoreResult($classification));
    }

    public function test_contains_placeholder(): void {
        $this->assertTrue(SensitiveDataMask::containsPlaceholder('Bitte [TAXID-3] prüfen'));
        $this->assertFalse(SensitiveDataMask::containsPlaceholder('Bitte [Kunde] prüfen'));
    }
}
