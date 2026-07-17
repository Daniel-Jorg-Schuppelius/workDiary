<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationFkMacroTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Tests\TestCase;

/**
 * Blueprint-Macros für den organization_id-FK (konsolidierungs-audit-2026-07,
 * Befund D9): geprüft wird nur die In-Memory-Definition (Spalte + foreign-
 * Command), kein DDL gegen die Datenbank.
 */
final class OrganizationFkMacroTest extends TestCase {
    public function test_organization_fk_defines_required_cascading_fk(): void {
        $blueprint = $this->blueprintFor('gadgets');
        $blueprint->organizationFk();

        $column = $blueprint->getColumns()[0];
        $this->assertSame('organization_id', $column->get('name'));
        $this->assertSame('bigInteger', $column->get('type'));
        $this->assertTrue((bool) $column->get('unsigned'));
        $this->assertNotTrue($column->get('nullable'));

        $foreign = $this->foreignCommand($blueprint);
        $this->assertSame(['organization_id'], $foreign->get('columns'));
        $this->assertSame('organizations', $foreign->get('on'));
        $this->assertSame('id', $foreign->get('references'));
        $this->assertSame('cascade', $foreign->get('onDelete'));
    }

    public function test_organization_fk_nullable_defines_null_on_delete_fk(): void {
        $blueprint = $this->blueprintFor('gadgets');
        $blueprint->organizationFkNullable();

        $column = $blueprint->getColumns()[0];
        $this->assertSame('organization_id', $column->get('name'));
        $this->assertTrue((bool) $column->get('nullable'));

        $foreign = $this->foreignCommand($blueprint);
        $this->assertSame(['organization_id'], $foreign->get('columns'));
        $this->assertSame('organizations', $foreign->get('on'));
        $this->assertSame('set null', $foreign->get('onDelete'));
    }

    private function blueprintFor(string $table): Blueprint {
        $connection = DB::connection();
        // Initialisiert die Schema-Grammar, die der Blueprint-Konstruktor erwartet.
        $connection->getSchemaBuilder();

        return new Blueprint($connection, $table);
    }

    /** @return Fluent<string, mixed> */
    private function foreignCommand(Blueprint $blueprint): Fluent {
        foreach ($blueprint->getCommands() as $command) {
            if ($command->get('name') === 'foreign') {
                return $command;
            }
        }

        $this->fail('Kein foreign-Command im Blueprint registriert.');
    }
}
