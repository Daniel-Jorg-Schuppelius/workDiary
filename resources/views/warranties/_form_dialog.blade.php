{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gewährleistungsfrist anlegen und ändern (Feature 115, MVP-604/1564). Ohne
  eigenes Enddatum ergibt es sich aus der Rechtsgrundlage; ein abweichendes
  Ende braucht eine Begründung. Variablen: $period (null = neu)
--}}
@php
    $sqidOf = static fn (string $class, ?int $id): string => $id !== null ? \App\Support\Sqid::encode($class, $id) : '';
    $startDefault = now()->toDateString();
@endphp
<x-modal
    :title="$period === null ? __('warranty.action.create') : __('warranty.action.edit')"
    :eyebrow="__('warranty.title')"
    icon="shield_with_heart"
    :action="$period === null ? route('warranties.store') : route('warranties.update', $period)"
    :method="$period === null ? 'POST' : 'PUT'"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-base-content/70">{{ $period === null ? __('warranty.dialog_hint') : __('warranty.edit_hint') }}</p>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="w-form-side"><span class="label-text">{{ __('warranty.column.side') }}</span></label>
            <select id="w-form-side" name="side" class="select select-bordered w-full">
                @foreach (\App\Enums\Warranty\WarrantySide::cases() as $side)
                    <option value="{{ $side->value }}" @selected(old('side', $period?->side?->value) === $side->value)>{{ $side->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="w-form-basis"><span class="label-text">{{ __('warranty.column.basis') }}</span></label>
            <select id="w-form-basis" name="basis" class="select select-bordered w-full">
                @foreach (\App\Enums\Warranty\WarrantyBasis::cases() as $basis)
                    <option value="{{ $basis->value }}" @selected(old('basis', $period?->basis?->value) === $basis->value)>{{ $basis->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-date-range layout="split" from-name="starts_on" to-name="ends_on" from-required
                  :from-label="__('warranty.column.starts_on')"
                  :to-label="__('warranty.column.ends_on')"
                  :from="old('starts_on', $period?->starts_on?->toDateString() ?? $startDefault)"
                  :to="old('ends_on', $period?->ends_on?->toDateString() ?? '')"
                  :from-data="$period === null
                      ? ['prefill-from' => 'protocol_id', 'prefill-key' => 'date', 'prefill-default' => $startDefault]
                      : ['prefill-from' => 'protocol_id', 'prefill-key' => 'date', 'prefill-default' => $period->starts_on->toDateString()]" />

    <x-input-field name="override_reason" type="text" maxlength="500"
                   :label="__('warranty.override_reason')"
                   :value="old('override_reason', $period?->override_reason ?? '')"
                   :hint="__('warranty.override_reason_hint')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="w-form-project"><span class="label-text">{{ __('warranty.column.project') }}</span></label>
            <select id="w-form-project" name="project_id" class="select select-bordered w-full">
                <option value="">—</option>
                <x-project-options :projects="$projects" :selected="old('project_id', $sqidOf(\App\Models\Project\Project::class, $period?->project_id))" />
            </select>
        </div>
        <div>
            <label class="label" for="w-form-protocol"><span class="label-text">{{ __('warranty.column.protocol') }}</span></label>
            <select id="w-form-protocol" name="protocol_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($protocols as $protocol)
                    {{-- Fristbeginn ist der Abnahmetag: die Wahl belegt „Beginn“ vor (data-prefill-date). --}}
                    <option value="{{ $protocol->sqid }}" data-prefill-date="{{ $protocol->occurred_at?->toDateString() }}" @selected(old('protocol_id', $sqidOf(\App\Models\Protocol\Protocol::class, $period?->protocol_id)) === $protocol->sqid)>{{ $protocol->title }} ({{ optional($protocol->occurred_at)->fdate() }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="w-form-customer"><span class="label-text">{{ __('warranty.column.customer') }}</span></label>
            <select id="w-form-customer" name="customer_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->sqid }}" @selected(old('customer_id', $sqidOf(\App\Models\Customer\Customer::class, $period?->customer_id)) === $c->sqid)>{{ $c->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="w-form-supplier"><span class="label-text">{{ __('warranty.column.supplier') }}</span></label>
            <select id="w-form-supplier" name="supplier_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->sqid }}" @selected(old('supplier_id', $sqidOf(\App\Models\Supplier\Supplier::class, $period?->supplier_id)) === $s->sqid)>{{ $s->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="trade" type="text" maxlength="120"
                       :label="__('warranty.column.trade')"
                       :value="old('trade', $period?->trade ?? '')" />
        <div>
            <label class="label" for="w-form-responsible"><span class="label-text">{{ __('warranty.column.responsible') }}</span></label>
            <select id="w-form-responsible" name="responsible_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($users as $u)
                    <option value="{{ $u->sqid }}" @selected(old('responsible_user_id', $sqidOf(\App\Models\Platform\User::class, $period?->responsible_user_id)) === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-textarea-field name="note" rows="2" :label="__('warranty.column.note')" :value="old('note', $period?->note ?? '')" />
</x-modal>
