<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalReturnService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims;

use App\Enums\Claims\ClaimSource;
use App\Enums\Manufacturing\DeliveryStockStatus;
use App\Models\Asset\Asset;
use App\Models\Claims\{ClaimCase, ClaimRmaReturn};
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, StockSerial};
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retourenanmeldung im Kundenportal (MVP-935): Der Kunde wählt eine eigene
 * Auslieferung (optional mit Seriennummer) oder ein eigenes Objekt; daraus
 * entstehen Reklamation (Quelle Portal) und angekündigte Rücksendung.
 */
final class PortalReturnService {
    public function __construct(
        private readonly ClaimCaseService $cases,
        private readonly ClaimRmaService $rma,
        private readonly FileAttacher $files,
    ) {}

    /** @return Collection<int, StockDelivery> */
    public function deliveries(Customer $customer): Collection {
        return StockDelivery::query()
            ->where('customer_id', $customer->id)
            ->where('stock_status', DeliveryStockStatus::Delivered)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * @param Collection<int, StockDelivery> $deliveries
     * @return array<int, list<string>> Auslieferung → ausgelieferte Seriennummern
     */
    public function serialsByDelivery(Collection $deliveries): array {
        $out = [];
        foreach (StockSerial::query()->whereIn('stock_delivery_id', $deliveries->modelKeys())->orderBy('serial_no')->get(['stock_delivery_id', 'serial_no']) as $serial) {
            $out[(int) $serial->stock_delivery_id][] = (string) $serial->serial_no;
        }

        return $out;
    }

    /** @return Collection<int, Asset> */
    public function assets(Customer $customer): Collection {
        return Asset::query()->where('customer_id', $customer->id)->orderBy('name')->limit(200)->get(['id', 'name', 'serial_no']);
    }

    /**
     * @param array{delivery_id?: ?int, asset_id?: ?int, serial_no?: ?string, quantity?: ?string, title: string, description: string} $data
     * @param list<UploadedFile> $photos
     * @return array{claim: ClaimCase, rma: ClaimRmaReturn}
     */
    public function submit(User $portalUser, Customer $customer, array $data, array $photos = []): array {
        $delivery = isset($data['delivery_id']) ? StockDelivery::query()->where('customer_id', $customer->id)->find($data['delivery_id']) : null;
        $asset = isset($data['asset_id']) ? Asset::query()->where('customer_id', $customer->id)->find($data['asset_id']) : null;
        if ($delivery === null && $asset === null) {
            throw ValidationException::withMessages(['delivery_id' => __('claims.portal_return.error.subject')]);
        }

        $serialNo = trim((string) ($data['serial_no'] ?? ''));
        $serial = null;
        if ($serialNo !== '' && $delivery !== null) {
            $serial = StockSerial::query()->where('stock_delivery_id', $delivery->id)->where('serial_no', $serialNo)->first();
            if ($serial === null) {
                throw ValidationException::withMessages(['serial_no' => __('claims.portal_return.error.serial')]);
            }
        }

        return DB::transaction(function () use ($portalUser, $customer, $data, $photos, $delivery, $asset, $serial, $serialNo): array {
            $claim = $this->cases->open($customer->organization()->firstOrFail(), $portalUser, [
                'source' => ClaimSource::Portal->value,
                'priority' => 'normal',
                'severity' => 'minor',
                'title' => $data['title'],
                'description' => $data['description'],
                'customer_id' => $customer->id,
                'reporter_name' => $portalUser->name,
                'reporter_email' => $portalUser->email,
                'article_id' => $delivery?->variant?->article_id,
                'asset_id' => $asset?->id,
                'stock_serial_id' => $serial?->id,
                'serial_no' => $serialNo !== '' ? $serialNo : ($asset->serial_no ?? null),
            ]);
            $rma = $this->rma->announce($claim, [
                'serial_no' => $claim->serial_no,
                'qty' => $data['quantity'] ?? null,
            ]);
            foreach ($photos as $photo) {
                $this->files->store($claim, $photo, $portalUser->id);
            }

            return ['claim' => $claim, 'rma' => $rma];
        });
    }
}
