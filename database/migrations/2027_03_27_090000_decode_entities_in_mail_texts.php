<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_27_090000_decode_entities_in_mail_texts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\Communication\CommunicationNote;
use App\Support\MorphMap;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * HTML-Entities in übernommenen Mail-Texten (Befund 2026-10-19). Die
 * Postfach-Gateways machten aus HTML-Mails nur `strip_tags`; `&nbsp;` und
 * `&amp;` blieben wörtlich stehen („Hallo Simone,&nbsp;sorry“). Dekodiert
 * werden nur Texte aus dem Mail-Eingang: Snapshots und Herkunftsverweise des
 * Plugins `email`, daraus gebuchte Kommunikationsnotizen (Verweis oder
 * Rechnungsnachfrage), Service-Tickets aus Mail und deren Mail-Nachrichten.
 * Verlorene Zeilenumbrüche kommen nicht zurück — das HTML ist nicht gespeichert.
 */
return new class extends Migration {
    private const ENTITY = '/&(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);/i';

    public function up(): void {
        $this->decodeJsonBody('integration_inbox_items', 'remote_snapshot', fn (Builder $q) => $q->where('plugin_id', 'email'));
        $this->decodeJsonBody('external_references', 'payload', fn (Builder $q) => $q->where('plugin_id', 'email')->where('external_type', 'message'));
        $this->decodeColumn('communication_notes', 'body', function (Builder $q): void {
            $q->where(function (Builder $q): void {
                $q->whereIn('id', DB::table('external_references')
                    ->where('plugin_id', 'email')
                    ->where('external_type', 'message')
                    ->where('referenceable_type', MorphMap::alias(CommunicationNote::class))
                    ->select('referenceable_id'));
                if (Schema::hasTable('invoice_inquiries')) {
                    $q->orWhereIn('id', DB::table('invoice_inquiries')->whereNotNull('communication_note_id')->select('communication_note_id'));
                }
            });
        });
        $this->decodeColumn('service_tickets', 'description', fn (Builder $q) => $q->where('source', 'email'));
        $this->decodeColumn('service_ticket_messages', 'body', fn (Builder $q) => $q->where('channel', 'mail'));
    }

    public function down(): void {
        // Dekodierter Text ist der Sollzustand; die Entities waren ein Fehler der Übernahme.
    }

    /** @param callable(Builder): mixed $scope */
    private function decodeColumn(string $table, string $column, callable $scope): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        $query = DB::table($table)->where($column, 'like', '%&%;%');
        $scope($query);
        $query->chunkById(200, function ($rows) use ($table, $column): void {
            foreach ($rows as $row) {
                $decoded = $this->decode((string) $row->{$column});
                if ($decoded !== (string) $row->{$column}) {
                    DB::table($table)->where('id', $row->id)->update([$column => $decoded]);
                }
            }
        });
    }

    /** @param callable(Builder): mixed $scope */
    private function decodeJsonBody(string $table, string $column, callable $scope): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        $query = DB::table($table)->where($column, 'like', '%&%;%');
        $scope($query);
        $query->chunkById(200, function ($rows) use ($table, $column): void {
            foreach ($rows as $row) {
                $data = json_decode((string) $row->{$column}, true);
                if (! is_array($data) || ! is_string($data['body'] ?? null)) {
                    continue;
                }
                $decoded = $this->decode($data['body']);
                if ($decoded === $data['body']) {
                    continue;
                }
                $data['body'] = $decoded;
                $json = json_encode($data);
                if ($json !== false) {
                    DB::table($table)->where('id', $row->id)->update([$column => $json]);
                }
            }
        });
    }

    private function decode(string $text): string {
        if (preg_match(self::ENTITY, $text) !== 1) {
            return $text;
        }

        return str_replace("\u{00A0}", ' ', StringHelper::htmlEntitiesToText($text));
    }
};
