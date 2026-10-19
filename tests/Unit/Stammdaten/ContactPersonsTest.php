<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactPersonsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Stammdaten;

use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Services\Stammdaten\ContactPersons;
use Tests\TestCase;

/**
 * Hauptansprechpartner aus dem Buchhaltungsprogramm: Einträge aus fremden
 * Feldern, Übernahme nur in den leeren Hauptansprechpartner (primär vor erstem).
 */
class ContactPersonsTest extends TestCase {
    public function test_external_fields_become_a_normalized_entry(): void {
        $entry = ContactPersons::fromExternal('Herr Prof.', 'Karl Heinz', 'Berg', 'Dr.', 'k@acme.test', null, true);

        $this->assertSame(['mr', 'Dr. Prof.', 'Karl', 'Heinz', 'Berg', 'Dr. Prof. Karl Heinz Berg', 'k@acme.test', null, true], array_values($entry ?? []));
        $this->assertSame('ms', ContactPersons::fromExternal('ms', 'Anna', 'Müller')['salutation'] ?? null);
        $this->assertNull(ContactPersons::fromExternal('Frau', '', ''));
    }

    public function test_the_primary_entry_fills_an_empty_main_contact_and_a_maintained_one_stays(): void {
        $customer = new Customer(['contact_persons' => [
            ['salutation' => 'mr', 'first_name' => 'Ralph', 'last_name' => 'Hartig', 'primary' => false],
            ['salutation' => 'ms', 'first_name' => 'Birgit', 'last_name' => 'Hartig', 'primary' => true],
        ]]);

        $this->assertTrue(ContactPersons::adoptPrimary($customer));
        $this->assertSame([ContactSalutation::Ms, 'Birgit', 'Hartig'], [$customer->contact_salutation, $customer->contact_first_name, $customer->contact_last_name]);

        $maintained = new Supplier(['contact_first_name' => 'Anna', 'contact_last_name' => 'Müller', 'contact_persons' => [['first_name' => 'Ralph', 'last_name' => 'Hartig']]]);
        $this->assertFalse(ContactPersons::adoptPrimary($maintained, ['first_name' => 'Jemand', 'last_name' => 'Anderes']));
        $this->assertSame('Anna', $maintained->contact_first_name);
    }

    public function test_without_a_primary_flag_the_first_entry_or_the_given_person_is_taken(): void {
        $customer = new Customer(['contact_persons' => [
            ['first_name' => 'Ralph', 'last_name' => 'Hartig'],
            ['first_name' => 'Birgit', 'last_name' => 'Hartig'],
        ]]);
        $this->assertTrue(ContactPersons::adoptPrimary($customer));
        $this->assertSame('Ralph', $customer->contact_first_name);

        $person = new Customer;
        $this->assertTrue(ContactPersons::adoptPrimary($person, ContactPersons::fromExternal('Frau', 'Maria Luise', 'Mustermann', primary: true)));
        $this->assertSame([ContactSalutation::Ms, 'Maria', 'Luise', 'Mustermann'], [$person->contact_salutation, $person->contact_first_name, $person->contact_middle_names, $person->contact_last_name]);

        $this->assertFalse(ContactPersons::adoptPrimary(new Customer));
        $this->assertFalse(ContactPersons::adoptPrimary(new Customer(['contact_persons' => [['email' => 'nur@mail.test']]])));
    }
}
