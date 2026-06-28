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
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeDunningService, LexofficeVoucherFileService, LexofficeVoucherSync};
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
            $query->where(function (Builder $q) use ($search): void {
                $q->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('voucher_type', 'like', "%{$search}%");
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

        $organization = $user->organization;
        if ($organization === null) {
            return back()->with('error', __('Keine Organisation zugeordnet.'));
        }

        try {
            $result = (new \App\Plugins\Lexoffice\LexofficeVoucherSync($config['api_key'], $config['base_url']))->sync($organization);

            return back()->with('success', __('Sync abgeschlossen: :created neu, :updated aktualisiert, :archived archiviert.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'archived' => $result['archived'],
            ]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Sync fehlgeschlagen: :msg', ['msg' => $e->getMessage()]));
        }
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
