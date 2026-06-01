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
use App\Models\{LexofficeVoucher, User};
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeVoucherFileService};
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

        $query = LexofficeVoucher::query()
            ->where('organization_id', $user->organization_id)
            ->whereBetween('voucher_date', [$from, $to])
            ->with(['customer:id,name', 'supplier:id,name'])
            ->orderByDesc('voucher_date')
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
            'rangeLabel' => $range['label'],
        ]);
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
