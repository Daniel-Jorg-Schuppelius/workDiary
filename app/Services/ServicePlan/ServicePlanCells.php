<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanCells.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan;

use App\Enums\Diary\Status;
use App\Models\Customer\{Customer, CustomerService};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Models\ServicePlan\{CustomerServicePeriodNote, CustomerServiceWaiver};
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Eigene Angaben einer Zelle des Leistungsplans (Feature 199, MVP-1638) —
 * einzige Schreibstelle für „entfällt“ und „Unterlagen da am“/Bemerkung.
 * Der Stand selbst gehört dem Auftrag; „entfällt“ gibt es deshalb nur für
 * Zellen ohne Auftrag und nur für Leistungen, die der Kunde in der Periode
 * im Leistungsumfang hat.
 */
final class ServicePlanCells {
    /** Leistungsumfang des Kunden, der in der Periode gilt (null: Leistung nicht im Umfang). */
    public function scope(Customer $customer, ServiceKey $service, PeriodKey $period): ?CustomerService {
        return CustomerService::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('entry_type_id', $service->entryTypeId)
            ->where('period_kind', $period->kind->value)
            ->when($service->productGroup === null, static fn (Builder $q) => $q->whereNull('product_group_code'), static fn (Builder $q) => $q->where('product_group_code', $service->productGroup))
            ->validBetween($period->start(), $period->end())
            ->orderByDesc('valid_from')
            ->first();
    }

    /** Auftrag der Zelle (nicht storniert), sonst null. */
    public function order(Customer $customer, ServiceKey $service, PeriodKey $period): ?DiaryEntry {
        return DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('entry_type_id', $service->entryTypeId)
            ->where('period_key', $period->key())
            ->where('status', '!=', Status::Cancelled->value)
            ->with('classifications')
            ->orderBy('id')
            ->get()
            ->first(static fn (DiaryEntry $entry): bool => $service->covers((int) $entry->entry_type_id, $entry->productGroupCode()));
    }

    public function waiver(Customer $customer, ServiceKey $service, PeriodKey $period): ?CustomerServiceWaiver {
        return $this->annotation(CustomerServiceWaiver::query(), $customer, $service, $period)->first();
    }

    public function note(Customer $customer, ServiceKey $service, PeriodKey $period): ?CustomerServicePeriodNote {
        return $this->annotation(CustomerServicePeriodNote::query(), $customer, $service, $period)->first();
    }

    /**
     * Zelle als „entfällt“ markieren (Grund Pflicht). Wirft, wenn die Leistung
     * in der Periode nicht im Umfang ist oder schon ein Auftrag existiert.
     *
     * @throws ValidationException
     */
    public function waive(Customer $customer, ServiceKey $service, PeriodKey $period, string $reason, User $actor): CustomerServiceWaiver {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => (string) __('service_plan.error.reason_required')]);
        }
        if ($this->scope($customer, $service, $period) === null) {
            throw ValidationException::withMessages(['period' => (string) __('service_plan.error.not_in_scope')]);
        }
        if ($this->order($customer, $service, $period) !== null) {
            throw ValidationException::withMessages(['period' => (string) __('service_plan.error.order_exists')]);
        }
        $existing = $this->waiver($customer, $service, $period);
        if ($existing !== null) {
            $existing->update(['reason' => $reason]);

            return $existing->refresh();
        }

        return CustomerServiceWaiver::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'entry_type_id' => $service->entryTypeId,
            'product_group_code' => $service->productGroup,
            'period_key' => $period,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
    }

    /** „Entfällt“ aufheben; die Zelle zeigt danach wieder „fehlt“ bzw. die Wahl. */
    public function unwaive(Customer $customer, ServiceKey $service, PeriodKey $period): bool {
        $waiver = $this->waiver($customer, $service, $period);

        return $waiver !== null && (bool) $waiver->delete();
    }

    /**
     * „Unterlagen da am“ und Bemerkung speichern; beide leer entfernt den Eintrag.
     */
    public function saveNote(Customer $customer, ServiceKey $service, PeriodKey $period, ?CarbonImmutable $documentsReceivedOn, ?string $note, User $actor): ?CustomerServicePeriodNote {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        $existing = $this->note($customer, $service, $period);
        if ($documentsReceivedOn === null && $note === null) {
            $existing?->delete();

            return null;
        }
        if ($existing !== null) {
            $existing->update([
                'documents_received_on' => $documentsReceivedOn?->toDateString(),
                'note' => $note,
                'updated_by' => $actor->id,
            ]);

            return $existing->refresh();
        }

        return CustomerServicePeriodNote::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'entry_type_id' => $service->entryTypeId,
            'product_group_code' => $service->productGroup,
            'period_key' => $period,
            'documents_received_on' => $documentsReceivedOn?->toDateString(),
            'note' => $note,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @template TModel of CustomerServiceWaiver|CustomerServicePeriodNote
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function annotation(Builder $query, Customer $customer, ServiceKey $service, PeriodKey $period): Builder {
        return $query->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('entry_type_id', $service->entryTypeId)
            ->when($service->productGroup === null, static fn (Builder $q) => $q->whereNull('product_group_code'), static fn (Builder $q) => $q->where('product_group_code', $service->productGroup))
            ->where('period_key', $period->key());
    }
}
