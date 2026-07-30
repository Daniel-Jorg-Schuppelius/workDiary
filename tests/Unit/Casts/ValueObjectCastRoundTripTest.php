<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValueObjectCastRoundTripTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Casts;

use App\Casts\{BicCast, DecimalCast, GermanTaxIdCast, GermanTaxNumberCast, GtinCast, IbanCast, VatNumberCast};
use CommonToolkit\ValueObjects\{Bic, Decimal, GermanTaxNumber, Gtin, Iban};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Round-trip der Identifikator-Casts — inklusive der verschlüsselten Variante.
 *
 * Ein Fehler hier bedeutet Datenverlust in Bankverbindungen und Personalakten,
 * deshalb sind alle vier Fälle abgedeckt: leer, gültig, ungültig und
 * unentschlüsselbarer Altbestand (Klartext aus der Zeit vor der Verschlüsselung).
 */
class ValueObjectCastRoundTripTest extends TestCase {
    private function model(): Model {
        return new class extends Model {};
    }

    public function test_iban_round_trip_normalises_and_reads_back(): void {
        $cast = new IbanCast;
        $model = $this->model();

        $stored = $cast->set($model, 'iban', 'de89 3704 0044 0532 0130 00', []);
        $this->assertSame('DE89370400440532013000', $stored['iban']);

        $read = $cast->get($model, 'iban', $stored['iban'], []);
        $this->assertInstanceOf(Iban::class, $read);
        $this->assertSame('DE89 3704 0044 0532 0130 00', $read->formatted());
    }

    public function test_empty_values_stay_null(): void {
        $cast = new IbanCast;
        $model = $this->model();

        $this->assertSame(['iban' => null], $cast->set($model, 'iban', null, []));
        $this->assertSame(['iban' => null], $cast->set($model, 'iban', '', []));
        $this->assertNull($cast->get($model, 'iban', null, []));
        $this->assertNull($cast->get($model, 'iban', '', []));
    }

    public function test_invalid_value_survives_the_write_and_reads_as_null(): void {
        $cast = new IbanCast;
        $model = $this->model();

        // Nicht verwerfen: die Pflicht zur Gültigkeit liegt im Form-Request.
        $stored = $cast->set($model, 'iban', 'KEINE-IBAN', []);
        $this->assertSame('KEINE-IBAN', $stored['iban']);
        $this->assertNull($cast->get($model, 'iban', $stored['iban'], []));
    }

    public function test_encrypted_round_trip_hides_the_value_in_the_column(): void {
        $cast = new IbanCast('encrypted');
        $model = $this->model();

        $stored = $cast->set($model, 'iban', 'DE89 3704 0044 0532 0130 00', []);
        $this->assertNotSame('DE89370400440532013000', $stored['iban']);
        $this->assertSame('DE89370400440532013000', Crypt::decryptString((string) $stored['iban']));

        $read = $cast->get($model, 'iban', $stored['iban'], []);
        $this->assertInstanceOf(Iban::class, $read);
        $this->assertSame('DE89370400440532013000', $read->getValue());
    }

    public function test_encrypted_cast_reads_plaintext_legacy_rows(): void {
        // Altbestand aus der Zeit vor der Verschlüsselung darf nicht werfen.
        $read = (new IbanCast('encrypted'))->get($this->model(), 'iban', 'DE89370400440532013000', []);

        $this->assertInstanceOf(Iban::class, $read);
        $this->assertSame('DE89370400440532013000', $read->getValue());
    }

    public function test_bic_gtin_vat_and_tax_id_round_trip(): void {
        $model = $this->model();

        $bic = (new BicCast)->set($model, 'bic', 'deutdeff', []);
        $this->assertSame('DEUTDEFF', $bic['bic']);
        $this->assertInstanceOf(Bic::class, (new BicCast)->get($model, 'bic', $bic['bic'], []));

        $gtin = (new GtinCast)->set($model, 'gtin', '4006381333931', []);
        $this->assertSame('4006381333931', $gtin['gtin']);
        $this->assertInstanceOf(Gtin::class, (new GtinCast)->get($model, 'gtin', $gtin['gtin'], []));
        $this->assertNull((new GtinCast)->get($model, 'gtin', '4006381333930', []), 'falsche Prüfziffer → null');

        $vat = (new VatNumberCast)->set($model, 'vat_id', 'de 811907980', []);
        $this->assertSame('DE811907980', $vat['vat_id']);

        $taxId = (new GermanTaxIdCast('encrypted'))->set($model, 'tax_identification_number', '86095742719', []);
        $this->assertSame('86095742719', Crypt::decryptString((string) $taxId['tax_identification_number']));
    }

    /**
     * Vorrats-Casts für die anstehende VO-Integration (Vollreview W2.4):
     * DecimalCast und GermanTaxNumberCast haben noch keine Produktionsnutzung,
     * bleiben aber bewusst erhalten — der Round-trip sichert sie bis dahin ab.
     */
    public function test_decimal_and_tax_number_round_trip(): void {
        $model = $this->model();

        $decimal = (new DecimalCast('4'))->set($model, 'factor', '1.2345', []);
        $this->assertSame('1.2345', $decimal['factor']);
        $read = (new DecimalCast('4'))->get($model, 'factor', $decimal['factor'], []);
        $this->assertInstanceOf(Decimal::class, $read);

        $taxNumber = (new GermanTaxNumberCast)->set($model, 'tax_number', '151/815/08156', []);
        $this->assertIsString($taxNumber['tax_number']);
        $readTax = (new GermanTaxNumberCast)->get($model, 'tax_number', $taxNumber['tax_number'], []);
        $this->assertInstanceOf(GermanTaxNumber::class, $readTax);
    }
}
