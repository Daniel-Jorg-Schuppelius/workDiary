{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _services_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Leistungsumfang eines Kunden (Feature 197, MVP-1630). Variablen: $customer, $services, $entryTypes, $productGroups, $today. Beenden läuft über eigene Knöpfe (formaction) desselben Formulars. --}}
<x-modal
    :title="__('customer_deadline.service.title')"
    :eyebrow="$customer->name"
    icon="playlist_add_check"
    tone="primary"
    size="wide"
    :action="route('customers.services.store', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer_deadline.service.add')">

    <input type="hidden" name="_dialog_url" value="{{ route('customers.services.index', $customer) . '?dialog=1' }}">

    <x-form-group :legend="__('customer_deadline.service.current')" icon="list" tone="ghost" cols="2">
        @if ($services->isEmpty())
            <x-empty-state compact icon="playlist_add" :title="__('customer_deadline.service.empty')" class="sm:col-span-2" />
        @else
            <ul class="divide-y divide-base-300 text-sm sm:col-span-2">
                @foreach ($services as $service)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2" data-customer-service="{{ $service->sqid }}">
                        <div>
                            <span class="font-medium">{{ $service->entryType?->label ?? '—' }}</span>
                            @if ($service->product_group_code)
                                <span class="text-muted">· {{ $productGroups->firstWhere('code', $service->product_group_code)?->displayLabel() ?? $service->product_group_code }}</span>
                            @endif
                            <div class="text-xs text-muted">
                                {{ $service->period_kind->label() }} ·
                                {{ $service->is_mandatory ? __('customer_deadline.service.mandatory') : __('customer_deadline.service.optional') }} ·
                                <span class="tabular-nums">{{ $service->valid_from->fdate() }} – {{ $service->valid_until?->fdate() ?? __('customer_deadline.open_end') }}</span>
                            </div>
                        </div>
                        @if ($service->valid_until === null)
                            {{-- raw-markup-ok: Absenden mit formaction an „Beenden“ innerhalb des Dialogformulars; <x-button> führt kein formaction --}}
                            <button type="submit" class="btn btn-ghost btn-xs" formnovalidate
                                    formaction="{{ route('customers.services.end', [$customer, $service]) }}">
                                <x-icon name="event_busy" /> {{ __('customer_deadline.service.end') }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
            <x-input-field type="date" id="cs-end-on" name="end_on" :label="__('customer_deadline.service.end_on')" :value="old('end_on', $today->format('Y-m-d'))" :hint="__('customer_deadline.service.end_hint')" />
        @endif
    </x-form-group>

    <x-form-group :legend="__('customer_deadline.service.new')" icon="add" tone="primary" cols="2">
        <x-select-field name="entry_type_id" id="cs-entry-type" :label="__('customer_deadline.service.entry_type')" required>
            <option value="">—</option>
            @foreach ($entryTypes as $type)
                <option value="{{ $type->sqid }}" @selected(old('entry_type_id') === $type->sqid)>{{ $type->label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="product_group_code" id="cs-product-group" :label="__('customer_deadline.service.product_group')" :hint="__('customer_deadline.service.product_group_hint')">
            <option value="">{{ __('customer_deadline.service.any_product_group') }}</option>
            @foreach ($productGroups as $group)
                <option value="{{ $group->code }}" @selected(old('product_group_code') === $group->code)>{{ $group->displayLabel() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="period_kind" id="cs-period-kind" :label="__('customer_deadline.service.period_kind')" required>
            @foreach (\App\Enums\Period\PeriodKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('period_kind', 'year') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-checkbox-field name="is_mandatory" id="cs-mandatory" :label="__('customer_deadline.service.mandatory')" :checked="(bool) old('is_mandatory', true)" :hint="__('customer_deadline.service.mandatory_hint')" />
        <div class="fieldset sm:col-span-2">
            <span class="fieldset-label">{{ __('customer_deadline.service.validity') }} *</span>
            <x-date-range
                type="date"
                :from="old('valid_from', $today->format('Y-m-d'))"
                :to="old('valid_until')"
                fromName="valid_from"
                toName="valid_until"
                :fromLabel="__('customer_deadline.profile.valid_from')"
                :toLabel="__('customer_deadline.service.valid_until')"
                :label="false"
                :fromRequired="true"
                :toRequired="false"
                class="w-full"
            />
        </div>
        <x-textarea-field name="note" id="cs-note" :label="__('customer_deadline.column.note')" rows="2" :value="old('note')" span="sm:col-span-2" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
