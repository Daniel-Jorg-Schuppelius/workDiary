<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataRangeExceptionRenderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

/**
 * UI-Fuzz 2026-09-21: Werte, die die Validierung passierten, aber nicht in die
 * Spalte passten (zu lang, Zahlenüberlauf, TIMESTAMP-Datum), endeten als 500.
 * Jetzt: Meldung am Feld — der Treiber-Fehler wird simuliert, weil SQLite
 * (CI) Längen und Bereiche nicht prüft.
 */
class DataRangeExceptionRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        Route::middleware('web')->post('/__data-range/{state}', function (string $state) {
            $pdo = new PDOException("SQLSTATE[{$state}]: Data too long for column 'title' at row 1");
            $pdo->errorInfo = [$state, 1406, "Data too long for column 'title' at row 1"];

            throw new QueryException('mysql', 'insert into `problems` (`title`) values (?)', ['x'], $pdo);
        });
    }

    public function test_too_long_value_becomes_a_field_error(): void {
        $this->from('/formular')
            ->post('/__data-range/22001')
            ->assertRedirect('/formular')
            ->assertSessionHasErrors(['title' => __('Der Text ist zu lang für dieses Feld.')]);
    }

    public function test_dialog_request_gets_422_json(): void {
        $this->postJson('/__data-range/22003')
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', __('Der Wert liegt außerhalb des zulässigen Bereichs.'));
    }

    public function test_other_query_errors_stay_server_errors(): void {
        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->post('/__data-range/23000');
    }
}
