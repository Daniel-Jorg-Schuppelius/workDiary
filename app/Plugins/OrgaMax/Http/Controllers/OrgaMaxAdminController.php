<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Http\Controllers;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{ExternalReference, IntegrationInboxItem, OrgaMaxConnection, Organization, User};
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\{OrgaMaxConnectionService, OrgaMaxScopePreflight, OrgaMaxSyncService};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Services\Integration\IntegrationOutboxService;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Orgamax\API\Endpoints\InvoicesEndpoint;
use RuntimeException;
use Throwable;

/**
 * Admin-UX des orgaMAX-Plugins (Feature 077, MVP-314): Plugin-Karte mit
 * Verbindung, erkanntem Account, Scopes und Gesundheit; Capability-Matrix
 * mit Datenführerschaft; Sync-Protokoll; getrennte, ausdrücklich bestätigte
 * Faktura-Aktionen (Umwandeln / Sperren / Senden / Zahlung melden) mit
 * eigenen Berechtigungen und Audit.
 */
class OrgaMaxAdminController extends Controller {
    use ResolvesPluginOrgContext;

    public function __construct(
        private readonly OrgaMaxConnectionService $connections,
        private readonly OrgaMaxClientFactory $clients,
        private readonly IntegrationOutboxService $outbox,
    ) {}

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = OrgaMaxConnection::query()->where('organization_id', $organization->id)->first();
        $invoices = ExternalReference::query()
            ->forPlugin($organization, OrgaMaxPlugin::ID, \App\Plugins\OrgaMax\Services\OrgaMaxInvoiceProjector::EXT_TYPE_INVOICE)
            ->orderByDesc('synced_at')
            ->limit(50)
            ->get();
        $orders = ExternalReference::query()
            ->forPlugin($organization, OrgaMaxPlugin::ID, \App\Services\Finance\Targets\OrgaMaxTarget::EXT_TYPE_ORDER)
            ->orderByDesc('synced_at')
            ->limit(50)
            ->get();

        return view('orgamax::admin.index', [
            'connection' => $connection,
            'invoices' => $invoices,
            'orders' => $orders,
            'requiredScopes' => OrgaMaxScopePreflight::requiredScopes(),
            'openInboxCount' => IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', OrgaMaxPlugin::ID)
                ->where('status', IntegrationInboxItem::STATUS_OPEN)
                ->count(),
            'expenseContractConfirmed' => (bool) config('plugins.orgamax.expense_receipt_contract_confirmed', false),
        ]);
    }

    /** Verbindungsabsicht starten (MVP-306) — liefert die Callback-URL mit State-Token. */
    public function startConnect(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'mode' => ['required', 'in:private,marketplace'],
            'api_key' => ['nullable', 'string', 'max:190'],
            'api_secret' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $state = $this->connections->startIntent(
                $organization,
                $admin,
                (string) $data['mode'],
                $data['api_key'] ?? null,
                $data['api_secret'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Die Callback-URL (inkl. State) wird in orgaMAX als Erweiterungs-URL
        // hinterlegt; orgaMAX hängt beim Öffnen `iid` an.
        return back()->with('success', __('orgamax.connect.intent_started'))
            ->with('orgamax_callback_url', route('admin.orgamax.callback', ['state' => $state]));
    }

    /** `iid`-Callback: nur mit gültiger Verbindungsabsicht (State-Token). */
    public function callback(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $iid = trim((string) $request->query('iid', ''));
        $state = trim((string) $request->query('state', ''));
        if ($iid === '' || $state === '') {
            return redirect()->route('admin.orgamax.index')->with('error', __('orgamax.error.intent_invalid'));
        }

        try {
            $this->connections->handleCallback($organization, $iid, $state);
        } catch (ApiException $e) {
            return redirect()->route('admin.orgamax.index')
                ->with('error', __('orgamax.error.token_exchange_failed', ['status' => $e->getCode()]));
        } catch (RuntimeException $e) {
            return redirect()->route('admin.orgamax.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orgamax.index')->with('success', __('orgamax.connect.confirm_account'));
    }

    /** Ausdrückliche Kontobestätigung (MVP-306). */
    public function confirmAccount(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $this->connection($organization);

        try {
            $connection = $this->connections->confirm($connection, $admin);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $connection->isActive() ? 'success' : 'error',
            $connection->isActive()
                ? __('orgamax.connect.active')
                : __('orgamax.connect.blocked', ['reason' => (string) $connection->blocked_reason]),
        );
    }

    /** Capability-Matrix / Datenführerschaft (MVP-305). */
    public function updateCapabilities(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $this->connection($organization);

        $data = $request->validate([
            'capabilities' => ['required', 'array'],
            'capabilities.*.enabled' => ['nullable', 'boolean'],
            'capabilities.*.leader' => ['nullable', 'in:orgamax,workdiary,manual_review'],
        ]);

        $this->connections->updateCapabilities($connection, (array) $data['capabilities']);

        return back()->with('success', __('orgamax.capabilities.saved'));
    }

    /** „Jetzt synchronisieren" — respektiert dieselben Budgets wie der Scheduler. */
    public function syncNow(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $this->connection($organization);

        if (! $connection->isActive()) {
            return back()->with('error', __('orgamax.error.not_connected'));
        }

        try {
            $counters = app(OrgaMaxSyncService::class)->run($connection);
        } catch (Throwable) {
            return back()->with('error', __('orgamax.sync.failed'));
        }

        return back()->with('success', __('orgamax.sync.done', ['counters' => json_encode($counters)]));
    }

    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $this->connection($organization);

        $this->connections->disconnect($connection);

        return back()->with('success', __('orgamax.connect.disconnected'));
    }

    // ── Getrennte Faktura-Aktionen (MVP-310) ────────────────────────────

    /** Auftrag → Rechnung (idempotent über die Outbox, eigene Berechtigung). */
    public function convertOrder(Request $request): RedirectResponse {
        $user = $this->permitted(Permission::OrgamaxInvoiceConvert);
        $organization = $this->organization($user);
        $connection = $this->connection($organization);

        $orderId = trim((string) $request->validate(['order_id' => ['required', 'string', 'max:190']])['order_id']);

        $this->outbox->enqueue(
            $organization->id,
            OrgaMaxPlugin::ID,
            'invoice.convert',
            ['order_id' => $orderId],
            'orgamax:convert:' . $organization->id . ':' . $orderId,
            $connection,
        );
        $connection->audit('orgamax_invoice_convert_requested', ['order_id' => $orderId, 'by' => $user->id]);

        return back()->with('success', __('orgamax.invoice.convert_enqueued'));
    }

    /**
     * Irreversibles Sperren — bewusst SYNCHRON und nie über Outbox/Retry/
     * Scheduler (MVP-310): nur diese ausdrücklich bestätigte Nutzeraktion.
     */
    public function lockInvoice(string $externalId): RedirectResponse {
        $user = $this->permitted(Permission::OrgamaxInvoiceLock);
        $organization = $this->organization($user);
        $connection = $this->connection($organization);

        try {
            (new InvoicesEndpoint($this->clients->for($connection)))->lock(new ID($externalId));
        } catch (ApiException $e) {
            return back()->with('error', __('orgamax.invoice.lock_failed', ['status' => $e->getCode()]));
        }
        $connection->audit('orgamax_invoice_locked', ['invoice_id' => $externalId, 'by' => $user->id]);

        return back()->with('success', __('orgamax.invoice.locked'));
    }

    /** Versand mit Empfängervorschau + separater Bestätigung (MVP-310). */
    public function sendInvoice(Request $request, string $externalId): RedirectResponse {
        $user = $this->permitted(Permission::OrgamaxInvoiceSend);
        $organization = $this->organization($user);
        $connection = $this->connection($organization);

        $data = $request->validate([
            'recipient' => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
        ]);

        $this->outbox->enqueue(
            $organization->id,
            OrgaMaxPlugin::ID,
            'invoice.send',
            [
                'invoice_id' => $externalId,
                // Betreff ist im API-Vertrag Pflicht — Standard in der Sprache
                // des bestätigenden Admins, nicht der des späteren Workers.
                'message' => [
                    'recipients' => [(string) $data['recipient']],
                    'subject' => trim((string) ($data['subject'] ?? '')) !== ''
                        ? (string) $data['subject']
                        : (string) __('orgamax.invoice.send_subject_default', ['number' => $externalId]),
                ],
            ],
            'orgamax:send:' . $organization->id . ':' . $externalId,
            $connection,
        );
        $connection->audit('orgamax_invoice_send_requested', ['invoice_id' => $externalId, 'recipient' => (string) $data['recipient'], 'by' => $user->id]);

        return back()->with('success', __('orgamax.invoice.send_enqueued'));
    }

    /** Zahlung melden — nur bei WorkDiary-geführtem Zahlungseingang, mit Dublettenprüfung. */
    public function recordPayment(Request $request, string $externalId): RedirectResponse {
        $user = $this->permitted(Permission::OrgamaxPaymentRecord);
        $organization = $this->organization($user);
        $connection = $this->connection($organization);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        $this->outbox->enqueue(
            $organization->id,
            OrgaMaxPlugin::ID,
            'payment.push',
            ['invoice_id' => $externalId] + $data,
            'orgamax:payment:' . $organization->id . ':' . $externalId . ':' . $data['amount'] . ':' . $data['date'],
            $connection,
        );
        $connection->audit('orgamax_payment_requested', ['invoice_id' => $externalId, 'amount' => (string) $data['amount'], 'by' => $user->id]);

        return back()->with('success', __('orgamax.invoice.payment_enqueued'));
    }

    /** Rechnungs-PDF-Projektion (Herkunft orgaMAX, Hash im Audit). */
    public function invoicePdf(string $externalId): Response {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $connection = $this->connection($organization);

        try {
            // GET /invoice/document/{id} — die ältere /download-Route ist deprecated.
            $pdf = (new InvoicesEndpoint($this->clients->for($connection)))->document(new ID($externalId));
        } catch (ApiException) {
            abort(502, (string) __('orgamax.invoice.pdf_failed'));
        }
        $connection->audit('orgamax_invoice_pdf_fetched', ['invoice_id' => $externalId, 'sha256' => CryptoHelper::hash($pdf)]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="orgamax-rechnung-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $externalId) . '.pdf"',
        ]);
    }

    // ── Guards ──────────────────────────────────────────────────────────

    private function permitted(Permission $permission): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin() || $user->can($permission->value), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function connection(Organization $organization): OrgaMaxConnection {
        $connection = OrgaMaxConnection::query()->where('organization_id', $organization->id)->first();
        abort_unless($connection instanceof OrgaMaxConnection, 404);

        return $connection;
    }
}
