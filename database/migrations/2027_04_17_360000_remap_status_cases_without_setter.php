<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_360000_remap_status_cases_without_setter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Statusfälle ohne Setzer entfallen (MVP-1620, Gate „Statusfall ohne Setzer“).
 * Kein Code hat sie je geschrieben; eine Altzeile bekommt trotzdem einen
 * belegten Fall, weil der Enum-Cast beim Lesen eines gestrichenen Werts wirft.
 */
return new class extends Migration {
    /** @var list<array{0: string, 1: string, 2: string, 3: string}> Tabelle, Spalte, alter Wert, neuer Wert */
    private const array REMAP = [
        // Fällige, nicht referenzierte Rate bleibt geplant; „bezahlt“ heißt nur „Eingangsrechnung referenziert“ (D11).
        ['asset_finance_rate_schedules', 'status', 'overdue', 'planned'],
        // Gescheiterte Quittung: gespeichert, der nächste Abruf quittiert erneut.
        ['domain_events', 'status', 'failed', 'stored'],
        // Der Import überspringt Fingerprint-Dubletten; eine gespeicherte gilt als beiseitegelegt.
        ['bank_transactions', 'match_status', 'duplicate', 'ignored'],
        // Ein Entwurf galt nie — er darf nicht still wirksam werden.
        ['tax_rules', 'status', 'draft', 'retired'],
        ['stock_reservations', 'status', 'cancelled', 'released'],
        // Reservierungen sind mengenbasiert, nie an eine Seriennummer gebunden.
        ['stock_serials', 'status', 'reserved', 'in_stock'],
        ['stock_deliveries', 'stock_status', 'cancelled', 'delivered'],
        // Zustellquittungen wertet WorkDiary nicht aus (Feature 147).
        ['notification_dispatch_log', 'status', 'delivered', 'sent'],
        // Der Ablauf steht in expires_at; die Einladung bleibt unbrauchbar.
        ['survey_invitations', 'status', 'expired', 'sent'],
        // Ohne Urteil bleibt der Anhang in Quarantäne und wird erneut geprüft.
        ['whistleblowing_attachments', 'scan_status', 'failed', 'pending'],
        ['msgraph_task_list_links', 'status', 'paused', 'active'],
    ];

    public function up(): void {
        foreach (self::REMAP as [$table, $column, $from, $to]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }

    public function down(): void {
        // Die alten Werte waren nie erreichbar; es gibt nichts zurückzuschreiben.
    }
};
