<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevMasterDataExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Datev;

use App\Models\{Customer, ExpenseCategory, Organization};
use App\Services\Finance\FinancialFormatsSupport;
use CommonToolkit\Entities\CSV\DataLine;
use CommonToolkit\FinancialFormats\Builders\DATEV\V700\{DebitorsCreditorsDocumentBuilder, GLAccountDescriptionDocumentBuilder};
use CommonToolkit\FinancialFormats\Entities\DATEV\Header\DebitorsCreditorsHeaderLine;
use CommonToolkit\FinancialFormats\Enums\DATEV\HeaderFields\V700\{DebitorsCreditorsHeaderField as Field, MetaHeaderField as MetaField};
use CommonToolkit\FinancialFormats\Generators\DATEV\DatevDocumentGenerator;

/**
 * DATEV-EXTF-Stammdatenexport Kategorie 16 (Nachtrag 045a, AR §5):
 * Debitoren aus dem Kundenstamm über den Kategorie-16-Writer des
 * php-financial-formats-Toolkits (DebitorsCreditorsDocumentBuilder,
 * Formatversion 5/V700, CP1252/CRLF/Semikolon, volle Spaltenzahl je Zeile).
 * Das Debitorenkonto folgt der bestehenden Buchungsstapel-Logik
 * ({@see DatevBookingConfig::debtorAccountFor()} — explizite debtor_no vor
 * Nummernkreis-Basis + Kunden-ID).
 *
 * MVP-334 (Bauturbo A15) ergänzt die Sachkonten-Beistellung: Kategorie 20
 * (Kontenbeschriftungen, Formatversion 3) über den GLAccountDescription-
 * Builder — beigestellt werden alle im Buchungsstapel verwendeten Sachkonten
 * (Erlöskonten + Aufwandskonten je Spesenkategorie) mit Beschriftung.
 */
final class DatevMasterDataExporter {
    /**
     * @return array{csv: string, count: int}
     */
    public function generateDebtors(Organization $organization, DatevBookingConfig $config): array {
        FinancialFormatsSupport::ensureAvailable();

        $fieldHeader = DebitorsCreditorsHeaderLine::createV700();
        $fieldCount = $fieldHeader->countFields();

        $builder = new DebitorsCreditorsDocumentBuilder;
        // Der Builder-Default-MetaHeader trägt noch Buchungsstapel-Werte
        // (Kategorie 21) — für Stammdaten explizit Kategorie 16,
        // Formatname "Debitoren/Kreditoren", Formatversion 5 setzen
        // (AR §5; Toolkit-Default als Klasse-C-Fix im Schwester-Repo notiert).
        $metaHeader = new \CommonToolkit\FinancialFormats\Entities\DATEV\MetaHeaderLine(
            new \CommonToolkit\FinancialFormats\Entities\DATEV\Header\V700\MetaHeaderDefinition,
        );
        $metaHeader->set(MetaField::Formatkategorie, 16);
        $metaHeader->set(MetaField::Formatname, 'Debitoren/Kreditoren');
        $metaHeader->set(MetaField::Formatversion, 5);
        $metaHeader->set(MetaField::ErzeugtAm, now()->format('YmdHis') . '000');
        $builder->setMetaHeader($metaHeader);
        $builder->setFieldHeader($fieldHeader);
        $builder->setClient($config->advisorNumber, $config->clientNumber);
        // DATEV-Bezeichnung: max. 30 Zeichen, nur \w.-/ und Leerzeichen —
        // Firmennamen (Kommata!) sind hier nicht zulässig.
        $builder->setDescription('Debitorenstammdaten');

        $customers = Customer::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        foreach ($customers as $customer) {
            $builder->addDataLine($this->debtorLine($fieldHeader, $fieldCount, $config, $customer));
        }

        $document = $builder->build();
        $csv = (new DatevDocumentGenerator)->generate($document, ';', '"', null, $config->encoding);

        return ['csv' => $csv, 'count' => $customers->count()];
    }

    /**
     * Sachkonten-Beistellung (MVP-334): alle im Export verwendeten Sachkonten
     * (Erlöskonto Standard/steuerfrei + Aufwandskonten des Spesenkategorie-
     * Mappings) als EXTF Kategorie 20 „Kontenbeschriftungen" (Formatversion 3).
     * Dublettenfrei je Kontonummer; die Beschriftung stammt aus der Rolle bzw.
     * dem Kategorienamen.
     *
     * @return array{csv: string, count: int}
     */
    public function generateGlAccounts(Organization $organization, DatevBookingConfig $config): array {
        FinancialFormatsSupport::ensureAvailable();

        $accounts = [];
        $add = static function (string $account, string $label) use (&$accounts): void {
            $account = trim($account);
            if ($account !== '' && ! isset($accounts[$account])) {
                // DATEV-Kontenbeschriftung: max. 40 Zeichen.
                $accounts[$account] = mb_substr(trim($label), 0, 40);
            }
        };

        $add($config->revenueAccount, 'Erlöse');
        $add($config->taxFreeRevenueAccount, 'Erlöse steuerfrei');

        if ($config->expenseAccounts !== []) {
            $categories = ExpenseCategory::query()
                ->whereIn('id', array_keys($config->expenseAccounts))
                ->get()
                ->keyBy('id');

            foreach ($config->expenseAccounts as $categoryId => $mapping) {
                $label = (string) ($categories[$categoryId]->label ?? ('Aufwand Kategorie ' . $categoryId));
                $add($mapping['account'], $label);
            }
        }

        $builder = new GLAccountDescriptionDocumentBuilder;
        // Builder-Default-MetaHeader trägt Buchungsstapel-Werte (Kategorie 21)
        // — für Sachkonten explizit Kategorie 20, Formatname
        // "Kontenbeschriftungen", Formatversion 3 setzen (Muster Kategorie 16).
        $metaHeader = new \CommonToolkit\FinancialFormats\Entities\DATEV\MetaHeaderLine(
            new \CommonToolkit\FinancialFormats\Entities\DATEV\Header\V700\MetaHeaderDefinition,
        );
        $metaHeader->set(MetaField::Formatkategorie, 20);
        $metaHeader->set(MetaField::Formatname, 'Kontenbeschriftungen');
        $metaHeader->set(MetaField::Formatversion, 3);
        $metaHeader->set(MetaField::ErzeugtAm, now()->format('YmdHis') . '000');
        $builder->setMetaHeader($metaHeader);
        $builder->setFieldHeader();
        $builder->setClient($config->advisorNumber, $config->clientNumber);
        $builder->setDescription('Sachkontenbeschriftungen');

        foreach ($accounts as $account => $label) {
            $builder->addGLAccount((string) $account, $label);
        }

        $document = $builder->build();
        $csv = (new DatevDocumentGenerator)->generate($document, ';', '"', null, $config->encoding);

        return ['csv' => $csv, 'count' => count($accounts)];
    }

    private function debtorLine(DebitorsCreditorsHeaderLine $fieldHeader, int $fieldCount, DatevBookingConfig $config, Customer $customer): DataLine {
        $values = array_fill(0, $fieldCount, '');
        $set = static function (Field $field, string $value) use (&$values, $fieldHeader): void {
            $values[$fieldHeader->getFieldIndex($field)] = $value;
        };

        $companyName = trim((string) ($customer->company ?: $customer->name));
        $set(Field::Konto, $config->debtorAccountFor($customer));
        $set(Field::NameUnternehmen, mb_substr($companyName, 0, 50));
        $set(Field::Adressattyp, '2'); // Unternehmen (natürliche Personen pflegt DATEV-seitig der Berater)
        $set(Field::Kurzbezeichnung, mb_substr($companyName, 0, 15));

        $vatId = strtoupper(str_replace(' ', '', (string) ($customer->vat_id ?? '')));
        if (preg_match('/^[A-Z]{2}[0-9A-Z]+$/', $vatId) === 1) {
            $set(Field::EULand, substr($vatId, 0, 2));
            $set(Field::EUUStID, substr($vatId, 2));
        }

        if (trim((string) $customer->address_street) !== '') {
            $set(Field::Adressart, 'STR');
            $set(Field::Strasse, mb_substr(trim((string) $customer->address_street), 0, 36));
            $set(Field::Postleitzahl, mb_substr(trim((string) $customer->address_zip), 0, 10));
            $set(Field::Ort, mb_substr(trim((string) $customer->address_city), 0, 30));
            $set(Field::Land, strtoupper(mb_substr(trim((string) ($customer->country ?? 'DE')), 0, 2)));
        }

        if (trim((string) $customer->number) !== '') {
            $set(Field::Kundennummer, mb_substr(trim((string) $customer->number), 0, 15));
        }

        return new DataLine($values, ';', '"');
    }
}
