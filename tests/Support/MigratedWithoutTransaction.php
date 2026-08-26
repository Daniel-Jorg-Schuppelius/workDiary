<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MigratedWithoutTransaction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wie {@see RefreshDatabase} — aber OHNE die umschließende Test-Transaktion
 * (Vollscan 2026-08-23, D7 / MVP-725).
 *
 * Für Tests, die echtes DDL fahren (Plugin-Schema-Lifecycle): auf MySQL/MariaDB
 * committet jedes CREATE/DROP TABLE implizit — die Test-Transaktion samt ihrer
 * Savepoints wäre danach weg und die Datenbank verschmutzt. Solche Tests waren
 * deshalb bisher SQLite-only. Mit diesem Trait bleibt die Migrations-Garantie
 * von RefreshDatabase erhalten (einmaliges `migrate:fresh` je Testprozess bzw.
 * die wiederverwendete DB aus {@see PersistedTestDatabase}), es wird aber keine
 * Transaktion geöffnet.
 *
 * **Preis und Pflicht:** Geschriebene Zeilen und Tabellen überleben den Test.
 * Wer dieses Trait nutzt, räumt in `tearDown()` selbst auf — und fasst nur
 * eigene Datensätze an (nie den geseedeten Grundbestand).
 */
trait MigratedWithoutTransaction {
    use RefreshDatabase;

    /**
     * Bewusst leer: die Transaktion ist genau das, was hier nicht gehen darf.
     * RefreshDatabase ruft die Methode am Ende von refreshTestDatabase() auf.
     */
    public function beginDatabaseTransaction() {
        // absichtlich ohne Wirkung — siehe Klassenkommentar.
    }
}
