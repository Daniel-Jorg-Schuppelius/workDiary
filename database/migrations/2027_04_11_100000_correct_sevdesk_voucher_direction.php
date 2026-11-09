<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_11_100000_correct_sevdesk_voucher_direction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Enums\Billing\DocumentDirection;
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MVP-1520: Der sevDesk-Puller spiegelte `creditDebit` verkehrt herum. Laut
 * API-Doku ist C eine Ausgabe (eingehend) und D eine Einnahme (ausgehend).
 * Betroffen sind nur `/Voucher`-Zeilen (Rohtyp C/D, ohne `invoice:`- und
 * `creditnote:`-Präfix). Belegart und Vorzeichen hängen nicht an der
 * Richtung (`document_kind` bleibt „sonstiges“); den offenen Betrag kennt der
 * gespeicherte Rohbeleg (`sumGross` − `paidAmount`). Idempotent.
 */
return new class extends Migration {
    public function up(): void {
        foreach (['C' => DocumentDirection::Incoming, 'D' => DocumentDirection::Outgoing] as $raw => $direction) {
            $this->vouchers()->where('voucher_type', $raw)->update(['direction' => $direction->value]);
        }

        $this->vouchers()->whereNull('open_amount')->select(['id', 'payload'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $payload = is_string($row->payload) ? json_decode($row->payload, true) : null;
                    if (! is_array($payload)) {
                        continue;
                    }
                    $gross = VoucherMirror::decimal($payload['sumGross'] ?? null);
                    $paid = VoucherMirror::decimal($payload['paidAmount'] ?? null);
                    if (is_numeric($gross) && is_numeric($paid)) {
                        DB::table('accounting_vouchers')->where('id', $row->id)->update(['open_amount' => NumberHelper::subtractPrecise($gross, $paid, 2)]);
                    }
                }
            });
    }

    public function down(): void {
        // Datenkorrektur nach API-Doku; ein Rückschreiben stellte den Fehler wieder her.
    }

    private function vouchers(): Builder {
        return DB::table('accounting_vouchers')
            ->where('plugin_id', 'sevdesk')
            ->whereIn('voucher_type', ['C', 'D'])
            ->where('external_id', 'not like', 'invoice:%')
            ->where('external_id', 'not like', 'creditnote:%');
    }
};
