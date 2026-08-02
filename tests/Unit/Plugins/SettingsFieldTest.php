<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingsFieldTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins;

use App\Plugins\Contracts\{FieldType, SettingsField};
use InvalidArgumentException;
use Tests\TestCase;

/** Typisiertes Settings-Schema (Review 2026-08, W5b). */
class SettingsFieldTest extends TestCase {
    public function test_from_array_normalizes_legacy_literals(): void {
        $field = SettingsField::fromArray(['key' => 'api_key', 'label' => 'API', 'type' => 'password', 'required' => true]);

        $this->assertSame(FieldType::Password, $field->type);
        $this->assertTrue($field->required);
        $this->assertTrue($field->isSecret(), 'password ist implizit secret.');
    }

    public function test_secret_flag_works_independent_of_type(): void {
        $field = SettingsField::fromArray(['key' => 'webhook_token', 'label' => 'Token', 'type' => 'text', 'secret' => true]);

        $this->assertSame(FieldType::Text, $field->type);
        $this->assertTrue($field->isSecret());
    }

    public function test_unknown_type_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        SettingsField::fromArray(['key' => 'x', 'label' => 'X', 'type' => 'daterange']);
    }

    public function test_invalid_key_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        SettingsField::fromArray(['key' => 'API-Key', 'label' => 'X', 'type' => 'text']);
    }

    public function test_select_without_options_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        SettingsField::fromArray(['key' => 'mode', 'label' => 'Modus', 'type' => 'select']);
    }

    public function test_factories_round_trip_to_array(): void {
        $field = SettingsField::url('base_url', 'Basis-URL', required: true);

        $this->assertSame([
            'key' => 'base_url',
            'label' => 'Basis-URL',
            'type' => 'url',
            'required' => true,
        ], $field->toArray());
        $this->assertSame(['string', 'url', 'max:1000'], FieldType::Url->rules());
    }
}
