<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseVoucherPush.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, LexofficeVoucher};
use App\Plugins\Lexoffice\{LexofficePlugin, LexofficeService};
use App\Plugins\PluginManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Aktiver Auslagen-Belegpush in die Buchhaltung (Feature 106): legt die
 * genehmigte Auslage als Einkaufsbeleg (`purchaseinvoice`) im führenden
 * System an, statt sie dort ein zweites Mal erfassen zu lassen.
 *
 * Drei Wächter tragen den Push:
 *
 * 1. **Nur genehmigte Auslagen** — der Push ist terminal (Lexoffice kennt für
 *    Belege weder Update noch Delete), deshalb steht die Freigabe davor.
 * 2. **Ohne Kategorie-Zuordnung kein Push** — eine geratene Buchungskategorie
 *    wäre schlimmer als eine Fehlermeldung.
 * 3. **Idempotenz über die ExternalReference** — ein zweiter Klick findet die
 *    Referenz und erzeugt keinen zweiten Beleg.
 *
 * Nach dem Push führt der Beleg (Dublettenregel 3 aus Feature 105 greift über
 * die ExternalReference automatisch); die Auslage ist fachlich gesperrt.
 * Korrekturen laufen als Gegenbeleg im führenden System.
 */
class ExpenseVoucherPush {
    public function __construct(
        private readonly PluginManager $plugins,
        private readonly DocumentLinks $links,
    ) {}

    /** Ist der Push für diese Auslage anbietbar (Zustand + Plugin + Mapping)? */
    public function available(Expense $expense): bool {
        return $expense->status === ExpenseStatus::Approved
            && $this->plugins->enabled()->contains(fn ($p): bool => $p->id() === LexofficePlugin::ID)
            && filled($expense->category?->accounting_category_id)
            && $this->links->voucherFor($expense) === null;
    }

    /**
     * Führt den Push aus und verknüpft den entstandenen Beleg.
     *
     * @throws RuntimeException wenn ein Wächter verletzt ist
     */
    public function push(Expense $expense): LexofficeVoucher {
        if ($expense->status !== ExpenseStatus::Approved) {
            throw new RuntimeException((string) __('Nur genehmigte Auslagen können übergeben werden — der Push ist unwiderruflich.'));
        }

        $categoryId = $expense->category?->accounting_category_id;
        if (blank($categoryId)) {
            throw new RuntimeException((string) __('Der Auslagenkategorie fehlt die Buchungskategorie des Buchhaltungssystems — ohne Zuordnung kein Push.'));
        }

        // Idempotenz: Der zweite Klick findet den Beleg des ersten.
        $existing = $this->links->voucherFor($expense);
        if ($existing !== null) {
            return $existing;
        }

        $result = app(LexofficeService::class)->createExpenseVoucher(
            $expense,
            (string) $categoryId,
            $this->localFilePaths($expense),
        );

        // Spiegelzeile wie beim Voucher-Sync - damit hat der Beleg sofort eine
        // lokale Identität, ohne auf den nächsten Sync-Lauf zu warten.
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $expense->organization_id,
            'external_id' => $result['external_id'],
            'voucher_type' => 'purchaseinvoice',
            'voucher_status' => 'open',
            'voucher_date' => $expense->date,
            'total_gross' => $expense->amount_gross?->getAmount(),
            'currency' => $expense->currency->value,
            'synced_at' => Carbon::now(),
        ]);

        // Verknüpfung (Feature 105): ab jetzt führt der Beleg. `pushed`
        // unterscheidet den aktiven Push von der nachträglichen Zuordnung -
        // eine gepushte Verknüpfung darf nicht gelöst werden, der Beleg
        // existiert unwiderruflich.
        $reference = $this->links->link($expense, $voucher);
        $reference->forceFill(['payload' => ['pushed' => true]])->save();

        return $voucher;
    }

    /** Wurde diese Auslage aktiv gepusht (im Gegensatz zur bloßen Zuordnung)? */
    public function wasPushed(Expense $expense): bool {
        $reference = $this->links->referenceFor($expense);

        return $reference !== null && (bool) ($reference->payload['pushed'] ?? false);
    }

    /**
     * Belegdateien als lokale Pfade für den Upload.
     *
     * @return list<string>
     */
    private function localFilePaths(Expense $expense): array {
        $paths = [];
        foreach ($expense->attachments as $attachment) {
            $disk = Storage::disk($attachment->disk);
            if ($disk->exists($attachment->path)) {
                $local = $disk->path($attachment->path);
                if (is_file($local)) {
                    $paths[] = $local;
                }
            }
        }

        return $paths;
    }
}
