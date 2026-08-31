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
use App\Http\Controllers\Controller;
use App\Models\{Customer, LexofficeVoucher, Supplier, User};
use App\Plugins\Lexoffice\Jobs\SyncVouchersJob;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeDunningService, LexofficeVoucherFileService, LexofficeVoucherSync};
use App\Services\Billing\RetainerVoucherReconciler;
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

        // MVP-690 (G3): materialisierte Belegbilder zuerst — nach dem
        // Buchhaltungswechsel gibt es keine Live-API mehr.
        $file = $service->localFile($voucher);
        if ($file === null) {
            if (! $service->isConfigured()) {
                abort(503, __('Lexoffice-Plugin ist nicht aktiviert oder API-Key fehlt.'));
            }

            try {
                $file = $service->download($voucher);
            } catch (\Throwable $e) {
                report($e);
                abort(404, __('Für diesen Beleg ist kein Belegbild verfügbar.'));
            }
        }

        $base = Str::slug((string) ($voucher->voucher_number ?: 'beleg-' . $voucher->id)) ?: ('beleg-' . $voucher->id);
        $filename = $base . '.' . $file['extension'];

        // Content-Type gegen eine Positivliste (Sicherheitsscan 2026-08-23,
        // S-64): übernommen wurde er ungeprüft aus der Lexoffice-Antwort.
        // Meldet die Quelle `text/html` oder `image/svg+xml`, rendert der
        // Browser den Inhalt INLINE im eigenen Origin — eine im Lexoffice-Konto
        // als „Beleg" abgelegte HTML-Datei wäre damit gespeichertes XSS.
        // `X-Content-Type-Options: nosniff` hilft dagegen nicht: es verhindert
        // das Raten des Typs, nicht das Befolgen eines erklärten.
        $contentType = $this->safeContentType((string) $file['content_type']);
        $disposition = $request->boolean('download') || $contentType === self::FALLBACK_CONTENT_TYPE
            ? 'attachment'
            : 'inline';

        return response($file['body'], 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Belegtypen, die inline angezeigt werden dürfen. Alles andere wird zum
     * Download — auch `image/svg+xml`: SVG ist ein Dokument mit Skriptfähigkeit,
     * kein Bild im harmlosen Sinn.
     */
    private const INLINE_CONTENT_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/tiff',
        'image/webp',
    ];

    private const FALLBACK_CONTENT_TYPE = 'application/octet-stream';

    private function safeContentType(string $reported): string {
        // Parameter abschneiden (`; charset=…`) und normalisieren.
        $type = mb_strtolower(trim(explode(';', $reported, 2)[0]));

        return in_array($type, self::INLINE_CONTENT_TYPES, true) ? $type : self::FALLBACK_CONTENT_TYPE;
    }

}
