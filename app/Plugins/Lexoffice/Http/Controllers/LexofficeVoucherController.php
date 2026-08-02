<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Customer, LexofficeVoucher, Supplier, User};
use App\Plugins\Lexoffice\Jobs\SyncVouchersJob;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeDunningService, LexofficeVoucherFileService, LexofficeVoucherSync};
use App\Services\Billing\RetainerVoucherReconciler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Zentrale Übersicht der lokal gecachten Lexoffice-Belege (voucherlist).
 *
 * Nur lesend; der Pull-Sync ({@see \App\Plugins\Lexoffice\LexofficeVoucherSync})
 * über `php artisan lexoffice:sync-vouchers` hält den Cache aktuell. Belege je
 * Kontakt sind zusätzlich auf der jeweiligen Kunden-/Lieferanten-Detailseite.
 */
class LexofficeVoucherController extends Controller {
    use ResolvesGlobalDateRange;

    private const ALLOWED_SORTS = ['voucher_number', 'voucher_date', 'voucher_type', 'voucher_status', 'total_amount'];

    public function index(Request $request): View {
        $user = $this->user();
        abort_unless($user->can(Permission::VoucherViewAny->value), 403);

        $search = trim((string) $request->input('q', ''));
        $type = (string) $request->input('type', '');
        $party = (string) $request->input('party', '');
        $status = (string) $request->input('status', 'active');

        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'voucher_date';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $query = LexofficeVoucher::query()
            ->where('organization_id', $user->organization_id)
            ->whereBetween('voucher_date', [$from, $to])
            ->with(['customer:id,name', 'supplier:id,name'])
            ->orderBy($sort, $dir)
            ->orderByDesc('id');

        if ($search !== '') {
            // Deutsche Betragseingabe (1.167,08) → 1167.08 für den Spaltenvergleich.
            $amount = str_replace(',', '.', str_replace(['.', ' '], '', $search));
            $datePatterns = $this->dateLikePatterns($search);

            $query->where(function (Builder $q) use ($search, $amount, $datePatterns): void {
                $q->whereLikeEscaped('voucher_number', $search)
                    ->orWhereLikeEscaped('voucher_type', $search)
                    ->orWhereHas('customer', fn($c) => $c->whereLikeEscaped('name', $search))
                    ->orWhereHas('supplier', fn($s) => $s->whereLikeEscaped('name', $search));

                if (is_numeric($amount)) {
                    $q->orWhereLikeEscaped('total_amount', $amount);
                }

                foreach ($datePatterns as $pattern) {
                    $q->orWhere('voucher_date', 'like', $pattern);
                }
            });
        }

        if ($type !== '') {
            $query->where('voucher_type', $type);
        }

        if ($party === 'customer') {
            $query->whereNotNull('customer_id');
        } elseif ($party === 'supplier') {
            $query->whereNotNull('supplier_id');
        }

        if ($status === 'archived') {
            $query->where('archived', true);
        } elseif ($status !== 'all') {
            $query->where('archived', false);
        }

        $types = LexofficeVoucher::query()
            ->where('organization_id', $user->organization_id)
            ->whereNotNull('voucher_type')
            ->distinct()
            ->orderBy('voucher_type')
            ->pluck('voucher_type')
            ->all();

        return view('lexoffice::vouchers.index', [
            'vouchers' => $query->paginate(30)->withQueryString(),
            'types' => $types,
            'filters' => ['q' => $search, 'type' => $type, 'party' => $party, 'status' => $status],
            'sort' => $sort,
            'dir' => $dir,
            'rangeLabel' => $range['label'],
            'canSync' => $user->can(Permission::VoucherLexofficeSync->value),
        ]);
    }

    /**
     * Übersetzt eine deutsche/ISO/teilweise Datumseingabe in LIKE-Muster gegen
     * die (als `Y-m-d` gespeicherte) Spalte `voucher_date`. Unterstützt:
     * `29.06.2026`, `06.2026`, `2026`, `29.06` (jahresunabhängig) sowie ISO.
     *
     * @return list<string>
     */
    private function dateLikePatterns(string $search): array {
        $s = trim($search);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) {
            return [sprintf('%04d-%02d-%02d%%', (int) $m[3], (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^(\d{1,2})\.(\d{4})$/', $s, $m)) {
            return [sprintf('%04d-%02d%%', (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^(\d{4})$/', $s, $m)) {
            return [sprintf('%04d%%', (int) $m[1])];
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})$/', $s, $m)) {
            return [sprintf('%%-%02d-%02d', (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $s)) {
            return [$s . '%'];
        }

        return [];
    }

    /**
     * Stößt den Pull-Sync der Lexoffice-Belege für die aktuelle Organisation an
     * ({@see \App\Plugins\Lexoffice\LexofficeVoucherSync}). Manueller Gegenpart
     * zum geplanten `lexoffice:sync-vouchers`.
     */
    public function sync(): \Illuminate\Http\RedirectResponse {
        $user = $this->user();
        abort_unless($user->can(Permission::VoucherLexofficeSync->value), 403);

        $config = LexofficeConfig::resolve($user->organization_id);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            return back()->with('error', __('Lexoffice ist für diese Organisation nicht konfiguriert.'));
        }

        if ($user->organization === null) {
            return back()->with('error', __('Keine Organisation zugeordnet.'));
        }

        // Voll-Sync über ALLE Kontakte kann viele API-Calls bedeuten und das
        // Web-Timeout überschreiten → im Hintergrund per Queue ausführen.
        // ShouldBeUnique verhindert Parallelläufe (Klick + Cron) je Organisation.
        SyncVouchersJob::dispatch((int) $user->organization_id);

        return back()->with('info', __('Beleg-Sync gestartet — läuft im Hintergrund und ist in Kürze aktuell.'));
    }

    /**
     * On-demand-Sync der Lexoffice-Belege EINES Kunden (Button auf der Detailseite).
     */
    public function syncCustomer(Customer $customer): \Illuminate\Http\RedirectResponse {
        return $this->syncOwner($customer);
    }

    /**
     * On-demand-Sync der Lexoffice-Belege EINES Lieferanten (Button auf der Detailseite).
     */
    public function syncSupplier(Supplier $supplier): \Illuminate\Http\RedirectResponse {
        return $this->syncOwner($supplier);
    }

    private function syncOwner(Customer|Supplier $owner): \Illuminate\Http\RedirectResponse {
        $user = $this->user();
        abort_unless($user->can(Permission::VoucherLexofficeSync->value), 403);
        abort_unless((int) $owner->organization_id === (int) $user->organization_id, 403);

        $config = LexofficeConfig::resolve($user->organization_id);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            return back()->with('error', __('Lexoffice ist für diese Organisation nicht konfiguriert.'));
        }

        try {
            $result = (new LexofficeVoucherSync($config['api_key'], $config['base_url']))->syncFor($owner);

            // Retainer-Zahlstatus (Feature 098) mitziehen — sonst holt der
            // Knopf zwar die Belege, der Leistungssaldo bliebe aber bis zum
            // stündlichen `lexoffice:sync-vouchers` unverändert.
            if ($owner instanceof Customer && $user->organization !== null) {
                app(RetainerVoucherReconciler::class)->reconcile($user->organization);
            }

            return back()->with('success', __('Belege synchronisiert: :created neu, :updated aktualisiert.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
            ]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Sync fehlgeschlagen: :msg', ['msg' => $e->getMessage()]));
        }
    }

    /**
     * Erstellt eine Lexoffice-Mahnung zu einer überfälligen Rechnung
     * (Button am Beleg in der Kunden-/Lieferantenansicht).
     */
    public function createDunning(LexofficeVoucher $voucher, LexofficeDunningService $dunnings): \Illuminate\Http\RedirectResponse {
        $user = $this->user();
        abort_unless($user->can(Permission::VoucherLexofficeSync->value), 403);
        abort_unless($voucher->organization_id === $user->organization_id, 403);

        try {
            $reference = $dunnings->push($voucher);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Mahnung in Lexoffice angelegt (ID :id).', [
            'id' => $reference->external_id,
        ]));
    }

    /**
     * Rendert den Dialog-Inhalt (eingebettete Modal-Partial) zur Vorschau des
     * Belegbilds. Wird per data-entry-modal-trigger nachgeladen.
     */
    public function preview(LexofficeVoucher $voucher): View {
        $user = $this->user();
        abort_unless($user->can(Permission::VoucherViewAny->value), 403);
        abort_unless($voucher->organization_id === $user->organization_id, 403);

        return view('lexoffice::vouchers._preview', [
            'voucher' => $voucher,
        ]);
    }

    /**
     * Liefert das in Lexoffice hinterlegte Belegbild/-dokument. Per Default
     * inline (Anzeige im Browser); mit ?download=1 als Datei-Download.
     */
    public function file(Request $request, LexofficeVoucher $voucher): SymfonyResponse {
        $user = $this->user();
        abort_unless($user->can(Permission::VoucherViewAny->value), 403);
        abort_unless($voucher->organization_id === $user->organization_id, 403);

        $config = LexofficeConfig::resolve($user->organization_id);
        $service = new LexofficeVoucherFileService($config['api_key'], $config['base_url']);

        if (! $service->isConfigured()) {
            abort(503, __('Lexoffice-Plugin ist nicht aktiviert oder API-Key fehlt.'));
        }

        try {
            $file = $service->download($voucher);
        } catch (\Throwable $e) {
            report($e);
            abort(404, __('Für diesen Beleg ist kein Belegbild verfügbar.'));
        }

        $base = Str::slug((string) ($voucher->voucher_number ?: 'beleg-' . $voucher->id)) ?: ('beleg-' . $voucher->id);
        $filename = $base . '.' . $file['extension'];
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($file['body'], 200, [
            'Content-Type' => $file['content_type'],
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
