<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ExternalInvoices;

use App\Enums\Finance\TransferStatus;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition, ExternalInvoice, ExternalInvoicePosition};
use App\Models\Platform\User;
use App\Services\Finance\Contracts\ExternalInvoiceProvider;
use App\Services\Finance\Dto\ExternalInvoiceSnapshot;
use App\Services\Invoicing\EInvoice\RecipientProfiles;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper, StringHelper};
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Extern ausgestellte Rechnungen (Feature 196, Nachtrag 07.11.2026): Stand beim
 * Anbieter abrufen, das Original der festgeschriebenen Rechnung einmal
 * unverändert sichern, eingefrorene Übergabepositionen zuordnen und die
 * Positionen abgleichen. Nummer, Inhalt und Korrekturen bleiben beim Anbieter.
 */
final class ExternalInvoiceService {
    public const string DISK = 'local';

    private const string FOLDER = 'external-invoices';

    public function __construct(
        private readonly ExternalInvoiceProviders $providers,
        private readonly ExternalInvoicePackager $packager,
        private readonly RecipientProfiles $profiles,
    ) {}

    /**
     * Rechnung zum Übergabenachweis abrufen; beim ersten Abruf gehören alle
     * noch freien Positionen der Übergabe zu dieser Rechnung.
     */
    public function syncForTransfer(BillingTransfer $transfer, ?User $actor): ExternalInvoice {
        $provider = $this->providerFor($transfer);
        $externalId = $provider->invoiceIdFor($transfer);
        if ($externalId === null || $externalId === '') {
            throw ValidationException::withMessages(['external_id' => __('finance.external_invoice.error.no_invoice', ['program' => $provider->label()])]);
        }

        $invoice = $this->sync($provider, (int) $transfer->organization_id, $externalId, (int) $transfer->customer_id, $actor);
        if (! $invoice->positionLinks()->exists()) {
            $this->assign($invoice, $transfer, $this->freePositionIds($transfer), $actor);
        }

        return $invoice->refresh();
    }

    /**
     * Geteilte, gebündelte oder direkt extern angelegte Rechnung: ausgewählte
     * Positionen der Übergabe einer Rechnung des Anbieters zuordnen.
     *
     * @param  list<int>  $positionIds
     */
    public function link(BillingTransfer $transfer, string $externalId, array $positionIds, User $actor): ExternalInvoice {
        $externalId = trim($externalId);
        if ($externalId === '' || $positionIds === []) {
            throw ValidationException::withMessages(['positions' => __('finance.external_invoice.error.nothing_to_link')]);
        }
        $invoice = $this->sync($this->providerFor($transfer), (int) $transfer->organization_id, $externalId, (int) $transfer->customer_id, $actor);
        $this->assign($invoice, $transfer, $positionIds, $actor);

        return $invoice->refresh();
    }

    /** Stand neu abrufen; das Original nur, solange keines gesichert ist. */
    public function refresh(ExternalInvoice $invoice, ?User $actor): ExternalInvoice {
        $provider = $this->provider($invoice->plugin_id);

        return $this->sync($provider, (int) $invoice->organization_id, $invoice->external_id, $invoice->customer_id, $actor);
    }

    /** Original ersetzen, etwa nach einer Korrektur beim Anbieter; nur ohne Versandfassung. */
    public function refetchOriginal(ExternalInvoice $invoice, ?User $actor): ExternalInvoice {
        $this->assertNoPackage($invoice);
        $provider = $this->provider($invoice->plugin_id);
        $snapshot = $provider->fetch((int) $invoice->organization_id, $invoice->external_id);
        $this->apply($invoice, $snapshot, $actor);
        if (! $snapshot->status->isFinalized()) {
            throw ValidationException::withMessages(['original' => __('finance.external_invoice.error.not_finalized')]);
        }
        $this->storeOriginal($invoice, $provider, $snapshot, replace: true);

        return $invoice->refresh();
    }

    public function unassign(ExternalInvoice $invoice, ExternalInvoicePosition $link): void {
        abort_unless($link->external_invoice_id === $invoice->id, 404);
        $this->assertNoPackage($invoice);
        $link->delete();
        $invoice->forceFill(['reconciled_hash' => null, 'reconciled_at' => null, 'reconciled_by' => null])->save();
    }

    /**
     * Abgleich der externen Positionen mit den zugeordneten Übergabepositionen.
     * Paare nach Name, Menge und Nettobetrag; danach nach Menge und Betrag
     * (umbenannt). Was übrig bleibt, ist eine Abweichung.
     *
     * @return array{hash: string, deviations: list<array{type: string, name: string, quantity: string, net: string}>, external_net: string, mapped_net: string, confirmed: bool}
     */
    public function reconciliation(ExternalInvoice $invoice): array {
        $positions = $invoice->positions()->get();
        $external = $invoice->lines ?? [];
        $mapped = $positions->map(static fn (BillingTransferPosition $p): array => [
            'id' => (int) $p->id,
            'name' => (string) $p->name,
            'quantity' => self::decimal((string) $p->quantity, 3),
            'net' => self::decimal((string) $p->amount, 2),
        ])->values()->all();

        $openExternal = $external;
        $openMapped = $mapped;
        foreach ([true, false] as $byName) {
            foreach ($openMapped as $mi => $position) {
                foreach ($openExternal as $ei => $line) {
                    if (self::decimal((string) ($line['quantity'] ?? '0'), 3) !== $position['quantity']
                        || self::decimal((string) ($line['net'] ?? '0'), 2) !== $position['net']
                        || ($byName && self::normalizeName((string) ($line['name'] ?? '')) !== self::normalizeName($position['name']))) {
                        continue;
                    }
                    unset($openMapped[$mi], $openExternal[$ei]);
                    break;
                }
            }
        }

        $deviations = [];
        foreach ($openMapped as $position) {
            $deviations[] = ['type' => 'missing_external', 'name' => $position['name'], 'quantity' => $position['quantity'], 'net' => $position['net']];
        }
        foreach ($openExternal as $line) {
            $deviations[] = [
                'type' => 'unmapped_external',
                'name' => (string) ($line['name'] ?? ''),
                'quantity' => self::decimal((string) ($line['quantity'] ?? '0'), 3),
                'net' => self::decimal((string) ($line['net'] ?? '0'), 2),
            ];
        }

        $hash = (string) CryptoHelper::hash(JsonHelper::encode([
            'lines' => (string) $invoice->lines_hash,
            'mapped' => array_map(static fn (array $p): string => $p['id'] . ':' . $p['quantity'] . ':' . $p['net'], $mapped),
        ]));

        return [
            'hash' => $hash,
            'deviations' => $deviations,
            'external_net' => self::sum(array_map(static fn (array $l): string => self::decimal((string) ($l['net'] ?? '0'), 2), $external)),
            'mapped_net' => self::sum(array_column($mapped, 'net')),
            'confirmed' => $invoice->reconciled_hash !== null && hash_equals($invoice->reconciled_hash, $hash),
        ];
    }

    /** Abweichungen bewusst bestätigen; gilt nur für genau diesen Stand. */
    public function confirmReconciliation(ExternalInvoice $invoice, User $actor, ?string $note): void {
        $result = $this->reconciliation($invoice);
        $invoice->forceFill([
            'reconciled_hash' => $result['hash'],
            'reconciled_at' => now(),
            'reconciled_by' => $actor->id,
            'note' => $note !== null ? Str::limit($note, 500, '') : $invoice->note,
            'updated_by' => $actor->id,
        ])->save();
        $invoice->audit('externalInvoice.reconciled', ['deviations' => count($result['deviations']), 'hash' => $result['hash']]);
    }

    /** Ohne Abweichung oder mit bestätigter Abweichung. */
    public function isReconciled(ExternalInvoice $invoice): bool {
        $result = $this->reconciliation($invoice);

        return $result['deviations'] === [] || $result['confirmed'];
    }

    public function originalContent(ExternalInvoice $invoice): ?string {
        if ($invoice->original_path === null) {
            return null;
        }
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($invoice->original_path)) {
            return null;
        }
        $content = (string) $disk->get($invoice->original_path);

        return CryptoHelper::hash($content) === $invoice->original_sha256 ? $content : null;
    }

    /**
     * Freie Positionen einer übergebenen Übergabe (noch keiner externen Rechnung zugeordnet).
     *
     * @return list<int>
     */
    public function freePositionIds(BillingTransfer $transfer): array {
        $taken = ExternalInvoicePosition::query()->where('billing_transfer_id', $transfer->id)->pluck('billing_transfer_position_id')->all();

        return array_values($transfer->positions()->whereNotIn('id', $taken)->pluck('id')->map(static fn ($id): int => (int) $id)->all());
    }

    public function provider(string $key): ExternalInvoiceProvider {
        return $this->providers->for($key) ?? throw ValidationException::withMessages(['external_id' => __('finance.external_invoice.error.no_provider')]);
    }

    private function providerFor(BillingTransfer $transfer): ExternalInvoiceProvider {
        return $this->providers->forTransfer($transfer) ?? throw ValidationException::withMessages(['external_id' => __('finance.external_invoice.error.no_provider')]);
    }

    private function sync(ExternalInvoiceProvider $provider, int $organizationId, string $externalId, ?int $customerId, ?User $actor): ExternalInvoice {
        if (! $provider->isConfigured($organizationId)) {
            throw ValidationException::withMessages(['external_id' => __('finance.external_invoice.error.not_configured', ['program' => $provider->label()])]);
        }

        $invoice = ExternalInvoice::query()->firstOrNew([
            'organization_id' => $organizationId,
            'plugin_id' => $provider->key(),
            'external_id' => $externalId,
        ]);
        if ($invoice->exists && $customerId !== null && $invoice->customer_id !== null && $invoice->customer_id !== $customerId) {
            throw ValidationException::withMessages(['external_id' => __('finance.external_invoice.error.other_customer')]);
        }

        try {
            $snapshot = $provider->fetch($organizationId, $externalId);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($invoice->exists) {
                $invoice->forceFill(['sync_error' => Str::limit($e->getMessage(), 500, '')])->save();
            }
            throw ValidationException::withMessages(['external_id' => __('finance.external_invoice.error.fetch_failed', ['program' => $provider->label(), 'message' => Str::limit($e->getMessage(), 200)])]);
        }

        if (! $invoice->exists) {
            $invoice->fill(['customer_id' => $customerId, 'created_by' => $actor?->id]);
        }
        $this->apply($invoice, $snapshot, $actor);

        if ($snapshot->status->isFinalized() && $invoice->original_sha256 === null) {
            $this->storeOriginal($invoice, $provider, $snapshot, replace: false);
        }

        return $invoice;
    }

    private function apply(ExternalInvoice $invoice, ExternalInvoiceSnapshot $snapshot, ?User $actor): void {
        $invoice->fill([
            'external_number' => $snapshot->number,
            'remote_status_code' => $snapshot->rawStatus,
            'status' => $snapshot->status,
            'kind' => $snapshot->kind,
            'issued_on' => $snapshot->issuedOn?->toDateString(),
            'currency' => $snapshot->currency,
            'net_amount' => $snapshot->netAmount,
            'gross_amount' => $snapshot->grossAmount,
            'lines' => $snapshot->lines,
            'lines_hash' => CryptoHelper::hash(JsonHelper::encode($snapshot->lines)),
            'provider_send_state' => $snapshot->sent,
            'provider_sent_at' => $snapshot->sentAt,
            'synced_at' => now(),
            'sync_error' => null,
            'updated_by' => $actor?->id,
        ])->save();
    }

    private function storeOriginal(ExternalInvoice $invoice, ExternalInvoiceProvider $provider, ExternalInvoiceSnapshot $snapshot, bool $replace): void {
        try {
            $invoice->loadMissing(['customer', 'organization']);
            $preferXml = $this->profiles->for($invoice->customer, $invoice->organization)->xmlOnlyMail();
            $file = $provider->original((int) $invoice->organization_id, $snapshot, $preferXml);
        } catch (Throwable $e) {
            $invoice->forceFill(['sync_error' => Str::limit($e->getMessage(), 500, '')])->save();

            return;
        }
        if ($file === null || $file->content === '') {
            return;
        }

        $format = $this->packager->detectFormat($file->content);
        $hash = (string) CryptoHelper::hash($file->content);
        $path = sprintf('%s/%d/%d/original-%s.%s', self::FOLDER, $invoice->organization_id, $invoice->id, substr($hash, 0, 16), $format?->extension() ?? 'bin');
        $disk = Storage::disk(self::DISK);
        $previous = $invoice->original_path;
        $disk->put($path, $file->content);

        $invoice->forceFill([
            'original_format' => $format,
            'original_path' => $path,
            'original_mime' => $format?->mime() ?? $file->mime,
            'original_sha256' => $hash,
            'original_fetched_at' => now(),
        ])->save();
        if ($replace && $previous !== null && $previous !== $path) {
            $disk->delete($previous);
        }
        $invoice->audit('externalInvoice.originalStored', ['sha256' => $hash, 'format' => $format?->value]);
    }

    /**
     * @param  list<int>  $positionIds
     */
    private function assign(ExternalInvoice $invoice, BillingTransfer $transfer, array $positionIds, ?User $actor): void {
        if ($transfer->status !== TransferStatus::Transferred) {
            throw ValidationException::withMessages(['positions' => __('finance.external_invoice.error.transfer_not_transferred')]);
        }
        if ($invoice->customer_id !== null && $invoice->customer_id !== $transfer->customer_id) {
            throw ValidationException::withMessages(['positions' => __('finance.external_invoice.error.other_customer')]);
        }
        $this->assertNoPackage($invoice);

        $valid = $transfer->positions()->whereIn('id', $positionIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if (count($valid) !== count(array_unique($positionIds))) {
            throw ValidationException::withMessages(['positions' => __('finance.external_invoice.error.foreign_position')]);
        }
        $taken = ExternalInvoicePosition::query()->whereIn('billing_transfer_position_id', $valid)
            ->where('external_invoice_id', '!=', $invoice->id)->exists();
        if ($taken) {
            throw ValidationException::withMessages(['positions' => __('finance.external_invoice.error.position_taken')]);
        }

        DB::transaction(function () use ($invoice, $transfer, $valid, $actor): void {
            foreach ($valid as $positionId) {
                ExternalInvoicePosition::query()->firstOrCreate(
                    ['billing_transfer_position_id' => $positionId],
                    [
                        'organization_id' => $invoice->organization_id,
                        'external_invoice_id' => $invoice->id,
                        'billing_transfer_id' => $transfer->id,
                        'created_by' => $actor?->id,
                    ],
                );
            }
            $invoice->forceFill(['customer_id' => $invoice->customer_id ?? $transfer->customer_id, 'reconciled_hash' => null, 'reconciled_at' => null, 'reconciled_by' => null])->save();
        });
    }

    private function assertNoPackage(ExternalInvoice $invoice): void {
        if ($invoice->packages()->exists()) {
            throw ValidationException::withMessages(['positions' => __('finance.external_invoice.error.packaged')]);
        }
    }

    private static function normalizeName(string $name): string {
        return StringHelper::collapseWhitespace(mb_strtolower(trim($name)), true);
    }

    /** Dezimalzeichenkette auf feste Stellen, ohne Gleitkomma-Rundung. */
    private static function decimal(string $value, int $scale): string {
        return Decimal::of(trim($value) === '' ? '0' : trim($value), $scale)->getValue();
    }

    /** @param  list<string>  $values */
    private static function sum(array $values): string {
        return Decimal::sum(array_map(static fn (string $v): Decimal => Decimal::of($v, 2), $values), 2)->getValue();
    }
}
