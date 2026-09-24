<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldDocumentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Fields;

use App\Enums\Fields\FieldType;
use App\Services\Fields\FieldDocument;
use Tests\TestCase;

/** Checklisten-Dokument (MVP-866): Altformen, Kanonik, Fortschritt. */
class FieldDocumentTest extends TestCase {
    private function field(\App\Services\Fields\FieldSchema $schema, string $key): \App\Services\Fields\FieldDefinition {
        $field = $schema->get($key);
        $this->assertNotNull($field);

        return $field;
    }

    public function test_legacy_checklists_become_boolean_fields(): void {
        $labelDone = FieldDocument::fromStored([['label' => 'Vertrag abgelegt', 'done' => true], ['label' => 'Arbeitsplatz vorbereitet', 'done' => false]]);
        $this->assertNotNull($labelDone);
        $this->assertSame(['vertrag_abgelegt', 'arbeitsplatz_vorbereitet'], $labelDone->schema->keys());
        $this->assertSame(FieldType::Boolean, $this->field($labelDone->schema, 'vertrag_abgelegt')->type);
        $this->assertTrue($labelDone->values->get('vertrag_abgelegt'));
        $this->assertFalse($labelDone->values->get('arbeitsplatz_vorbereitet'));
        $this->assertSame(['done' => 1, 'total' => 2], $labelDone->progress());

        $map = FieldDocument::fromStored(['Reifen geprüft' => true, 'Öl geprüft' => '0']);
        $this->assertNotNull($map);
        $this->assertSame(['reifen_gepruft', 'ol_gepruft'], $map->schema->keys());
        $this->assertTrue($map->values->get('reifen_gepruft'));

        $list = FieldDocument::fromStored(['Schlüssel', 'Papiere', 'Schlüssel']);
        $this->assertNotNull($list);
        $this->assertSame(['schlussel', 'papiere', 'schlussel_2'], $list->schema->keys());
        $this->assertSame(['done' => 0, 'total' => 3], $list->progress());
    }

    public function test_canonical_form_round_trips_and_empty_is_null(): void {
        $document = FieldDocument::checklist(['Vertrag', 'Zugang'], ['Zugang']);
        $stored = $document->toArray();
        $this->assertSame(['schema', 'values'], array_keys($stored));
        $this->assertSame(['vertrag' => false, 'zugang' => true], $stored['values']);

        $again = FieldDocument::fromStored($stored);
        $this->assertNotNull($again);
        $this->assertSame($stored, $again->toArray());
        $this->assertNull(FieldDocument::fromStored(null));
        $this->assertNull(FieldDocument::fromStored([]));
        $this->assertNull(FieldDocument::fromStored('kaputt'));
        $this->assertSame([['field' => $again->schema->get('vertrag'), 'value' => false], ['field' => $again->schema->get('zugang'), 'value' => true]], $again->items());
    }
}
