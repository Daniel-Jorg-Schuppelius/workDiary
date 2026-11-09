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

use App\Casts\{BicCast, ByteSizeCast, DecimalCast, GermanTaxIdCast, GermanTaxNumberCast, GtinCast, IbanCast, MoneyCast, PercentageCast, QuantityCast, VatNumberCast};
use App\Exceptions\UnparseableValueObjectException;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Iban, Money};
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

    public function test_iban_round_trip_normalises_and_reads_back_as_text(): void {
        $cast = new IbanCast;
        $model = $this->model();

        $stored = $cast->set($model, 'iban', 'de89 3704 0044 0532 0130 00', []);
        $this->assertSame('DE89370400440532013000', $stored['iban']);

        // MVP-1512: Kennungen lesen als Text, das Wertobjekt baut der Leser.
        $read = $cast->get($model, 'iban', $stored['iban'], []);
        $this->assertSame('DE89370400440532013000', $read);
        $this->assertSame('DE89 3704 0044 0532 0130 00', Iban::tryFrom($read)?->formatted());
    }

    public function test_empty_values_stay_null(): void {
        $cast = new IbanCast;
        $model = $this->model();

        $this->assertSame(['iban' => null], $cast->set($model, 'iban', null, []));
        $this->assertSame(['iban' => null], $cast->set($model, 'iban', '', []));
        $this->assertSame(['iban' => null], (new IbanCast('encrypted'))->set($model, 'iban', '   ', []), 'kein leeres Chiffrat');
        $this->assertNull($cast->get($model, 'iban', null, []));
        $this->assertNull($cast->get($model, 'iban', '', []));
    }

    public function test_invalid_value_survives_the_write_and_stays_readable(): void {
        $cast = new IbanCast;
        $model = $this->model();

        // Nicht verwerfen: die Pflicht zur Gültigkeit liegt im Form-Request.
        $stored = $cast->set($model, 'iban', ' KEINE-IBAN ', []);
        $this->assertSame('KEINE-IBAN', $stored['iban']);
        $this->assertSame('KEINE-IBAN', $cast->get($model, 'iban', $stored['iban'], []));
        // Ungültiger Altbestand mit Leerzeichen kommt unverändert zurück.
        $this->assertSame('DE00 1234', $cast->get($model, 'iban', 'DE00 1234', []));
    }

    public function test_encrypted_round_trip_hides_the_value_in_the_column(): void {
        $cast = new IbanCast('encrypted');
        $model = $this->model();

        $stored = $cast->set($model, 'iban', 'DE89 3704 0044 0532 0130 00', []);
        $this->assertNotSame('DE89370400440532013000', $stored['iban']);
        $this->assertSame('DE89370400440532013000', Crypt::decryptString((string) $stored['iban']));
        $this->assertSame('DE89370400440532013000', $cast->get($model, 'iban', $stored['iban'], []));

        // Zwei Chiffrate desselben Klartexts gelten nicht als Änderung.
        $again = $cast->set($model, 'iban', 'DE89370400440532013000', []);
        $this->assertNotSame($stored['iban'], $again['iban']);
        $this->assertTrue($cast->compare($model, 'iban', $stored['iban'], $again['iban']));
        $this->assertFalse($cast->compare($model, 'iban', $stored['iban'], $cast->set($model, 'iban', 'DE02120300000000202051', [])['iban']));
    }

    public function test_encrypted_cast_reads_plaintext_legacy_rows(): void {
        // Altbestand aus der Zeit vor der Verschlüsselung darf nicht werfen.
        $read = (new IbanCast('encrypted'))->get($this->model(), 'iban', 'DE89 3704 0044 0532 0130 00', []);

        $this->assertSame('DE89 3704 0044 0532 0130 00', $read);
    }

    public function test_encrypted_cast_does_not_hand_out_foreign_ciphertext(): void {
        // Chiffrat eines anderen APP_KEY: werfen wie der encrypted-Cast statt den Chiffretext als IBAN zu zeigen.
        $foreign = (new \Illuminate\Encryption\Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString('DE89370400440532013000');

        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);
        (new IbanCast('encrypted'))->get($this->model(), 'iban', $foreign, []);
    }

    public function test_bic_gtin_vat_and_tax_id_round_trip(): void {
        $model = $this->model();

        $bic = (new BicCast)->set($model, 'bic', ' deutdeff ', []);
        $this->assertSame('DEUTDEFF', $bic['bic'], 'erfasste Länge bleibt');
        $this->assertSame('DEUTDEFF', (new BicCast)->get($model, 'bic', $bic['bic'], []));

        $gtin = (new GtinCast)->set($model, 'gtin', '4006381333931', []);
        $this->assertSame('4006381333931', $gtin['gtin']);
        $this->assertSame('4006381333930', (new GtinCast)->get($model, 'gtin', '4006381333930', []), 'falsche Prüfziffer bleibt lesbar');

        $vat = (new VatNumberCast)->set($model, 'vat_id', 'de 811907980', []);
        $this->assertSame('DE811907980', $vat['vat_id']);

        $taxId = (new GermanTaxIdCast('encrypted'))->set($model, 'tax_identification_number', '86 095 742 719', []);
        $this->assertSame('86095742719', Crypt::decryptString((string) $taxId['tax_identification_number']));
    }

    public function test_tax_number_keeps_the_grouping_of_the_tax_office(): void {
        $model = $this->model();
        $cast = new GermanTaxNumberCast;

        $this->assertSame(['tax_number' => '151/815/08156'], $cast->set($model, 'tax_number', ' 151 / 815 / 08156 ', []));
        $this->assertSame(['tax_number' => '12 34'], $cast->set($model, 'tax_number', '12 34', []), 'ungültig: nur getrimmt');
        $this->assertSame('151/815/08156', $cast->get($model, 'tax_number', '151/815/08156', []));
    }

    /**
     * Vorrats-Cast für die anstehende VO-Integration (Vollreview W2.4):
     * DecimalCast hat noch keine breite Produktionsnutzung, der Round-trip
     * sichert ihn ab.
     */
    public function test_decimal_round_trip(): void {
        $model = $this->model();

        $decimal = (new DecimalCast('4'))->set($model, 'factor', '1.2345', []);
        $this->assertSame('1.2345', $decimal['factor']);
        $read = (new DecimalCast('4'))->get($model, 'factor', $decimal['factor'], []);
        $this->assertInstanceOf(Decimal::class, $read);
    }

    /**
     * Dirty-Prüfung über Engines hinweg: SQLite liefert `decimal` als Float
     * (741.6 / 1236.0), MariaDB als String ('741.60'). Ohne den Vergleichs-Hook
     * gilt auf SQLite jede unveränderte Geldspalte als geändert (Sync-Läufe
     * melden „updated" statt „kept", Audit-Diffs füllen sich mit Scheinänderungen).
     */
    public function test_money_cast_treats_sqlite_float_and_decimal_string_as_equal(): void {
        $model = new class extends Model {
            protected $guarded = [];

            protected $casts = ['amount' => MoneyCast::class, 'unit_price' => MoneyCast::class . ':currency,4'];
        };

        $model->setRawAttributes(['amount' => 741.6, 'unit_price' => 187.92, 'currency' => 'EUR'], true);
        $model->fill(['amount' => '741.60', 'unit_price' => '187.9200']);
        $this->assertFalse($model->isDirty(), 'Float aus SQLite = Dezimalstring des Casts');

        $model->setRawAttributes(['amount' => 1236.0, 'currency' => 'EUR'], true);
        $model->fill(['amount' => Money::of('1236', CurrencyCode::Euro)]);
        $this->assertFalse($model->isDirty('amount'), 'ganze Zahl ohne Nachkommastellen');

        $model->fill(['amount' => '1236.01']);
        $this->assertTrue($model->isDirty('amount'), 'ein Cent Unterschied ist eine Änderung');

        $model->fill(['amount' => null]);
        $this->assertTrue($model->isDirty('amount'), 'null gegen Betrag ist eine Änderung');

        $cast = new MoneyCast;
        $this->assertTrue($cast->compare($model, 'amount', null, null));
        $this->assertFalse($cast->compare($model, 'amount', null, '0.00'));
        $this->assertTrue($cast->compare($model, 'amount', 'kein-betrag', 'kein-betrag'), 'Fremdwert nur bei exakter Gleichheit');
        $this->assertFalse($cast->compare($model, 'amount', 'kein-betrag', '0.00'));
    }

    /** Dasselbe für Prozent, Menge und Dezimal: ein unveränderter Steuersatz einer ausgestellten Rechnung ist sonst eine verbotene Änderung. */
    public function test_numeric_casts_treat_sqlite_number_and_decimal_string_as_equal(): void {
        $model = new class extends Model {
            protected $guarded = [];

            protected $casts = ['tax_rate' => PercentageCast::class . ':2', 'quantity' => QuantityCast::class . ':unit,4', 'factor' => DecimalCast::class . ':6'];
        };

        $model->setRawAttributes(['tax_rate' => 19, 'quantity' => 2.5, 'unit' => 'h', 'factor' => 1], true);
        $model->fill(['tax_rate' => '19.00', 'quantity' => '2.5000', 'factor' => '1.000000']);
        $this->assertFalse($model->isDirty(), 'Zahl aus SQLite = Dezimalstring des Casts');

        $model->fill(['tax_rate' => '7.00']);
        $this->assertTrue($model->isDirty('tax_rate'));

        $cast = new PercentageCast('2');
        $this->assertTrue($cast->compare($model, 'tax_rate', null, ''));
        $this->assertFalse($cast->compare($model, 'tax_rate', null, '0.00'));
    }

    /**
     * Wertobjekt-Audit 2026-09-19: Numerische Casts schrieben unlesbaren
     * Rohtext („19.00 %" vor 2.0, „abc") still in DECIMAL-Spalten. Jetzt lesen
     * sie die eigene Textform und lehnen Unlesbares beim Schreiben ab.
     */
    public function test_numeric_casts_read_own_text_form_and_reject_garbage(): void {
        $model = $this->model();

        $this->assertSame(['tax_rate' => '19.00'], (new PercentageCast('2'))->set($model, 'tax_rate', '19.00 %', []));
        $this->assertSame(['quantity' => '2.500'], (new QuantityCast('3'))->set($model, 'quantity', '2.500 Stk', []));

        foreach ([[new PercentageCast('2'), 'tax_rate'], [new QuantityCast('3'), 'quantity'], [new DecimalCast('2'), 'amount'], [new ByteSizeCast, 'size_bytes']] as [$cast, $key]) {
            try {
                $cast->set($model, $key, 'abc', []);
                $this->fail($cast::class . ' hätte „abc" ablehnen müssen.');
            } catch (UnparseableValueObjectException $e) {
                $this->assertSame($key, $e->attribute);
            }
        }
    }

    public function test_identifier_casts_keep_passing_invalid_values_through(): void {
        $this->assertSame(['vat_id' => 'KEINE'], (new VatNumberCast)->set($this->model(), 'vat_id', 'KEINE', []));
    }

    public function test_money_cast_is_strict_on_write_and_lenient_on_read(): void {
        $model = $this->model();
        $cast = new MoneyCast;

        $this->assertSame(['price' => '12.34'], $cast->set($model, 'price', '12.34 EUR', []));
        $this->assertNull($cast->get($model, 'price', 'kaputt', []), 'Altbestand mit Müll sprengt keine Liste');

        $this->expectException(UnparseableValueObjectException::class);
        $cast->set($model, 'price', 'zwölf Euro', []);
    }
}
