<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Http\Controllers;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Finance\DatevBatchStatus;
use App\Models\Finance\DatevBookingBatch;
use App\Models\Invoicing\IncomingEInvoiceTransfer;
use App\Models\Platform\User;
use App\Plugins\DatevOnline\Api\DatevOnlineOAuth;
use App\Plugins\DatevOnline\{DatevOnlineConfig, DatevOnlinePlugin};
use App\Plugins\DatevOnline\Enums\{DatevConnectionStatus, DatevTransferKind};
use App\Plugins\DatevOnline\Exceptions\DatevOnlineException;
use App\Plugins\DatevOnline\Models\{DatevOnlineConnection, DatevOnlineTransfer};
use App\Plugins\DatevOnline\Services\{DatevClientDirectory, DatevDocumentUploader, DatevExtfTransferService};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use App\Services\Invoicing\EInvoice\IncomingInvoiceTransferService;
use App\Support\Query\DateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Collection, Str};
use Illuminate\View\View;
use Throwable;

/** Verwaltung der DATEV-Online-Anbindung (MVP-122). */
class DatevOnlineAdminController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    public function index(DatevClientDirectory $directory): View {
        $organization = $this->organization($this->admin());
        $connection = DatevOnlineConnection::query()->where('organization_id', $organization->id)->first();

        $clients = [];
        $clientsError = null;
        if ($connection instanceof DatevOnlineConnection && $connection->isActive()) {
            try {
                $clients = $directory->list($connection);
            } catch (Throwable $e) {
                $clientsError = class_basename($e);
            }
        }

        $batches = DatevBookingBatch::query()
            ->where('organization_id', $organization->id)
            ->where('status', DatevBatchStatus::Exported->value)
            ->latest('id')->paginate(20)->withQueryString();
        $transfers = DatevOnlineTransfer::query()->where('organization_id', $organization->id);

        return view('datev-online::admin.index', [
            'configured' => DatevOnlineConfig::isConfigured((int) $organization->id),
            'sandbox' => DatevOnlineConfig::resolve((int) $organization->id)['sandbox'],
            'connection' => $connection,
            'clients' => $clients,
            'clientsError' => $clientsError,
            'batches' => $batches,
            // Stand je Stapel der Seite gezielt laden — ein Fenster über alle Übertragungen verlor ihn hinter vielen Belegen.
            'batchTransfers' => (clone $transfers)->where('kind', DatevTransferKind::Extf->value)
                ->whereIn('source_id', $batches->pluck('id'))->get()->keyBy('source_id'),
            'documentTransfers' => $this->documentTransfers($transfers, (int) $organization->id),
        ]);
    }

    /**
     * Belegbilder beider Journale, neueste zuerst: Ausgangsrechnungen hier,
     * der Rechnungseingang im gemeinsamen Übergabejournal (MVP-1111).
     *
     * @param  Builder<DatevOnlineTransfer>  $transfers
     * @return Collection<int, array{kind: string, at: ?Carbon, label: string, tone: string, error: ?string}>
     */
    private function documentTransfers(Builder $transfers, int $organizationId): Collection {
        $outgoing = (clone $transfers)->where('kind', '!=', DatevTransferKind::Extf->value)->latest('id')->limit(30)->get()
            ->map(static fn (DatevOnlineTransfer $t): array => [
                'kind' => $t->kind->label(), 'at' => $t->transferred_at ?? $t->updated_at,
                'label' => $t->status->label(), 'tone' => $t->status->tone(), 'error' => $t->error,
            ]);
        $incoming = IncomingEInvoiceTransfer::query()->where('organization_id', $organizationId)
            ->where('target', DatevOnlinePlugin::ID)->with('incoming:id,direction')->latest('id')->limit(30)->get()
            ->map(static fn (IncomingEInvoiceTransfer $t): array => [
                'kind' => ($t->incoming?->direction === DocumentDirection::Outgoing ? DatevTransferKind::OutgoingDocument : DatevTransferKind::IncomingDocument)->label(),
                'at' => $t->transferred_at ?? $t->updated_at,
                'label' => $t->status->label(), 'tone' => $t->status->tone(), 'error' => $t->error,
            ]);

        return $outgoing->concat($incoming)->sortByDesc(static fn (array $row): int => (int) $row['at']?->getTimestamp())->take(30)->values();
    }

    public function selectClient(Request $request, DatevClientDirectory $directory): RedirectResponse {
        $connection = $this->activeConnection();
        $data = $request->validate(['datev_client' => ['required', 'string', 'regex:/^\d{1,7}-\d{1,5}$/']]);
        $client = collect($directory->list($connection))->firstWhere('id', $data['datev_client']);
        if ($client === null) {
            return back()->withErrors(['datev_client' => __('datev-online::datev.error.unknown_client')]);
        }
        $connection->forceFill(['datev_client_number' => $client['id'], 'datev_client_name' => mb_substr($client['name'], 0, 191)])->save();

        return back()->with('success', __('datev-online::datev.flash.client_selected', ['client' => $client['name']]));
    }

    public function updateDocuments(Request $request): RedirectResponse {
        $connection = $this->activeConnection();
        $data = $request->validate([
            'is_documents_enabled' => ['nullable', 'boolean'],
            'documents_since' => ['nullable', 'date'],
        ]);
        $connection->forceFill([
            'is_documents_enabled' => (bool) ($data['is_documents_enabled'] ?? false),
            'documents_since' => isset($data['documents_since']) ? DateRange::day($data['documents_since']) : ($connection->documents_since ?? now()->toDateString()),
        ])->save();

        return back()->with('success', __('datev-online::datev.flash.documents_saved'));
    }

    public function uploadNow(DatevDocumentUploader $uploader, IncomingInvoiceTransferService $incoming): RedirectResponse {
        $connection = $this->activeConnection();
        $counts = $uploader->uploadPending($connection, 50);
        $more = $incoming->retryOpen($this->organization($this->admin()), DatevOnlinePlugin::ID);
        $counts = ['transferred' => $counts['transferred'] + $more['transferred'], 'failed' => $counts['failed'] + $more['failed']];

        return back()->with($counts['failed'] > 0 ? 'warning' : 'success', __('datev-online::datev.flash.uploaded', $counts));
    }

    public function transferBatch(DatevBookingBatch $batch, DatevExtfTransferService $extf): RedirectResponse {
        $connection = $this->activeConnection();
        abort_unless((int) $batch->organization_id === (int) $connection->organization_id, 404);
        try {
            $extf->transfer($connection, $batch);
        } catch (DatevOnlineException $e) {
            return back()->with('error', __('datev-online::datev.error.' . $e->reason));
        }

        return back()->with('success', __('datev-online::datev.flash.batch_transferred', ['no' => $batch->batch_no]));
    }

    public function refreshJobs(DatevExtfTransferService $extf): RedirectResponse {
        $done = $extf->refresh($this->activeConnection());

        return back()->with('success', __('datev-online::datev.flash.jobs_refreshed', ['count' => $done]));
    }

    private function activeConnection(): DatevOnlineConnection {
        $organization = $this->organization($this->admin());
        $connection = DatevOnlineConnection::query()->where('organization_id', $organization->id)->first();
        abort_unless($connection instanceof DatevOnlineConnection && $connection->isActive(), 404);

        return $connection;
    }

    protected function oauth(): PluginOAuthGrant {
        return app(DatevOnlineOAuth::class);
    }

    protected function isConfigured(): bool {
        return DatevOnlineConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return DatevOnlineConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'datev-online-oauth';
    }

    protected function overviewRouteName(): string {
        return 'admin.datev-online.index';
    }

    protected function pluginKey(): string {
        return 'datev';
    }

    protected function pluginId(): string {
        return 'datev-online';
    }

    protected function connectedStatus(): string {
        return DatevConnectionStatus::Active->value;
    }

    protected function disconnectedStatus(): string {
        return DatevConnectionStatus::Disconnected->value;
    }

    /** OpenID Connect verlangt eine Nonce. */
    protected function extraAuthorizeParams(): array {
        return ['nonce' => Str::random(32)];
    }

    protected function afterConnected(Model $connection, User $admin): void {
        if ($connection instanceof DatevOnlineConnection && $connection->documents_since === null) {
            // Belegbilder erst ab dem Verbinden — kein ungefragter Altbestand.
            $connection->forceFill(['documents_since' => now()->toDateString()])->save();
        }
    }
}
