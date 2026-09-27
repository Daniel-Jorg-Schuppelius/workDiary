<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionOrderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance;

use App\Enums\AssetCompliance\{AssetInspectionOrderStatus, AssetInspectionResult, AssetInspectionScheduleStatus};
use App\Mail\InspectionOrderMail;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetInspectionOrder, AssetInspectionSchedule};
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\Supplier;
use App\Services\Attachments\FileAttacher;
use App\Services\Concerns\AssertsValidatedTransition;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Prüfaufträge an Dienstleister (MVP-938): fällige Prüftermine gehen per
 * Link an einen Prüfdienstleister, der ein Angebot abgibt und nach der
 * Annahme je Prüfmittel Ergebnis, Datum, Gültigkeit und Zertifikat meldet.
 * Übernommen wird über {@see AssetComplianceService::recordInspection()}.
 */
final class InspectionOrderService {
    use AssertsValidatedTransition;

    public const LINK_DAYS = 90;

    public function __construct(
        private readonly AssetComplianceService $compliance,
        private readonly FileAttacher $files,
    ) {}

    /** @param list<int> $scheduleIds */
    public function create(Organization $organization, Supplier $supplier, string $title, string $email, array $scheduleIds, User $actor): AssetInspectionOrder {
        $schedules = AssetInspectionSchedule::query()
            ->whereIn('id', $scheduleIds)
            ->whereIn('status', [AssetInspectionScheduleStatus::Planned->value, AssetInspectionScheduleStatus::Announced->value])
            ->get();
        if ($schedules->isEmpty()) {
            throw ValidationException::withMessages(['schedule_ids' => __('inspection_order.error.no_schedules')]);
        }
        $token = Str::lower(Str::random(40));

        return DB::transaction(function () use ($organization, $supplier, $title, $email, $schedules, $actor, $token): AssetInspectionOrder {
            $order = AssetInspectionOrder::query()->create([
                'organization_id' => $organization->id,
                'supplier_id' => $supplier->id,
                'title' => $title,
                'status' => AssetInspectionOrderStatus::Requested,
                'token_hash' => CryptoHelper::hash($token),
                'recipient_email' => $email,
                'expires_at' => now()->addDays(self::LINK_DAYS),
                'created_by' => $actor->id,
            ]);
            foreach ($schedules as $schedule) {
                $order->items()->create([
                    'organization_id' => $organization->id,
                    'asset_inspection_schedule_id' => $schedule->id,
                    'asset_compliance_assignment_id' => $schedule->asset_compliance_assignment_id,
                    'asset_id' => $schedule->asset_id,
                ]);
                $schedule->forceFill(['status' => AssetInspectionScheduleStatus::Announced->value])->save();
            }
            Mail::to($email)->queue(new InspectionOrderMail((int) $order->id, $token));

            return $order;
        });
    }

    public function resolve(string $token): ?AssetInspectionOrder {
        if ($token === '') {
            return null;
        }
        $order = AssetInspectionOrder::query()->withoutGlobalScopes()->where('token_hash', CryptoHelper::hash($token))->first();

        return $order !== null && $order->expires_at->isFuture()
            && in_array($order->status, [AssetInspectionOrderStatus::Requested, AssetInspectionOrderStatus::Offered, AssetInspectionOrderStatus::Accepted, AssetInspectionOrderStatus::Reported], true) ? $order : null;
    }

    /** @param array{offer_amount: string, offer_planned_on: string, offer_note?: ?string} $data */
    public function offer(AssetInspectionOrder $order, array $data): AssetInspectionOrder {
        if ($order->status === AssetInspectionOrderStatus::Offered) {
            $order->status = AssetInspectionOrderStatus::Requested;
        }
        $this->assertValidatedTransition($order->status, AssetInspectionOrderStatus::Offered, 'inspection_order.error.transition');
        $order->forceFill([
            'status' => AssetInspectionOrderStatus::Offered,
            'offer_amount' => $data['offer_amount'],
            'currency' => 'EUR',
            'offer_planned_on' => $data['offer_planned_on'],
            'offer_note' => $data['offer_note'] ?? null,
            'offered_at' => now(),
        ])->save();

        return $order;
    }

    public function decide(AssetInspectionOrder $order, bool $accept, User $actor): AssetInspectionOrder {
        $target = $accept ? AssetInspectionOrderStatus::Accepted : AssetInspectionOrderStatus::Requested;
        $this->assertValidatedTransition($order->status, $target, 'inspection_order.error.transition');
        $order->forceFill(['status' => $target, 'accepted_at' => $accept ? now() : null, 'accepted_by' => $accept ? $actor->id : null])->save();
        $order->audit($accept ? 'inspection_order.accepted' : 'inspection_order.offer_rejected', ['offer_amount' => $order->offer_amount]);

        return $order;
    }

    /**
     * Rückmeldung des Dienstleisters je Prüfmittel (Schlüssel = Sqid der Position).
     *
     * @param array<string, array{result?: ?string, performed_on?: ?string, valid_until?: ?string, certificate_no?: ?string, note?: ?string}> $items
     * @param array<string, UploadedFile> $certificates
     */
    public function report(AssetInspectionOrder $order, array $items, array $certificates): AssetInspectionOrder {
        $this->assertValidatedTransition($order->status, AssetInspectionOrderStatus::Reported, 'inspection_order.error.transition');

        return DB::transaction(function () use ($order, $items, $certificates): AssetInspectionOrder {
            $reported = 0;
            foreach ($order->items()->get() as $item) {
                $row = $items[$item->sqid] ?? null;
                if (! is_array($row) || AssetInspectionResult::tryFrom((string) ($row['result'] ?? '')) === null) {
                    continue;
                }
                $item->forceFill([
                    'result' => $row['result'],
                    'performed_on' => $row['performed_on'] ?? now()->toDateString(),
                    'valid_until' => $row['valid_until'] ?? null,
                    'certificate_no' => $row['certificate_no'] ?? null,
                    'note' => $row['note'] ?? null,
                ])->save();
                if (isset($certificates[$item->sqid])) {
                    $this->files->store($item, $certificates[$item->sqid], null);
                }
                $reported++;
            }
            if ($reported === 0) {
                throw ValidationException::withMessages(['items' => __('inspection_order.error.nothing_reported')]);
            }
            $order->forceFill(['status' => AssetInspectionOrderStatus::Reported, 'reported_at' => now()])->save();

            return $order;
        });
    }

    /** Übernahme der gemeldeten Ergebnisse als Prüfereignisse (Zertifikat samt Datei und Prüfsumme). */
    public function takeOver(AssetInspectionOrder $order, User $actor): int {
        $this->assertValidatedTransition($order->status, AssetInspectionOrderStatus::Completed, 'inspection_order.error.transition');
        $supplierName = (string) $order->supplier?->name;

        return DB::transaction(function () use ($order, $actor, $supplierName): int {
            $count = 0;
            foreach ($order->items()->with(['assignment', 'attachments'])->whereNotNull('result')->whereNull('asset_inspection_event_id')->get() as $item) {
                $assignment = $item->assignment;
                if (! $assignment instanceof AssetComplianceAssignment) {
                    continue;
                }
                $file = $item->attachments->first();
                $content = $file !== null ? Storage::disk($file->disk)->get($file->path) : null;
                $event = $this->compliance->recordInspection($assignment, $actor, [
                    'result' => $item->result,
                    'performed_at' => $item->performed_on?->toDateString(),
                    'valid_until' => $item->valid_until?->toDateString(),
                    'schedule_id' => $item->asset_inspection_schedule_id,
                    'external_inspector_name' => $supplierName,
                    'note' => $item->note,
                    'certificate' => $item->certificate_no !== null ? [
                        'certificate_no' => $item->certificate_no,
                        'issuer' => $supplierName,
                        'issued_on' => $item->performed_on?->toDateString(),
                        'valid_until' => $item->valid_until?->toDateString(),
                        'sha256' => $content !== null ? CryptoHelper::hash($content) : null,
                    ] : null,
                ]);
                if ($file !== null && $content !== null) {
                    $this->files->storeContent($event, $content, $file->original_name, $file->mime, $actor->id);
                }
                $item->forceFill(['asset_inspection_event_id' => $event->id])->save();
                $count++;
            }
            $order->forceFill(['status' => AssetInspectionOrderStatus::Completed, 'completed_at' => now()])->save();
            $order->audit('inspection_order.completed', ['events' => $count]);

            return $count;
        });
    }

    public function cancel(AssetInspectionOrder $order, User $actor): void {
        $this->assertValidatedTransition($order->status, AssetInspectionOrderStatus::Cancelled, 'inspection_order.error.transition');
        DB::transaction(function () use ($order): void {
            $order->forceFill(['status' => AssetInspectionOrderStatus::Cancelled])->save();
            AssetInspectionSchedule::query()
                ->whereIn('id', $order->items()->whereNotNull('asset_inspection_schedule_id')->pluck('asset_inspection_schedule_id'))
                ->where('status', AssetInspectionScheduleStatus::Announced->value)
                ->update(['status' => AssetInspectionScheduleStatus::Planned->value]);
        });
        $order->audit('inspection_order.cancelled', []);
    }
}
