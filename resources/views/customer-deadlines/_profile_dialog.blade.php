{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _profile_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Fristmerkmale eines Kunden (Feature 197, MVP-1630). Variablen: $customer, $ruleSet, $ruleSets (Schlüssel → Bezeichnung), $schema, $values (FieldValues|null), $derived, $versions, $responsibleId, $validFrom, $members. --}}
@php
    $selectedResponsible = (string) old('responsible_user_id', $responsibleId !== null ? \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $responsibleId) : '');
@endphp

<x-modal
    :title="__('customer_deadline.profile.title')"
    :eyebrow="$customer->name"
    icon="event_note"
    tone="primary"
    :action="route('customers.deadline-profile.store', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer_deadline.profile.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('customers.deadline-profile.edit', $customer) . '?dialog=1&rule_set=' . $ruleSet->key() }}">

    <x-form-group :legend="__('customer_deadline.profile.rule_set')" icon="rule" tone="primary" cols="2">
        @if (count($ruleSets) > 1)
            <div class="flex flex-wrap gap-2 sm:col-span-2">
                @foreach ($ruleSets as $key => $label)
                    <x-icon-btn icon="rule" size="sm" :tone="$key === $ruleSet->key() ? 'primary' : 'ghost'" data-entry-modal-trigger
                                :href="route('customers.deadline-profile.edit', $customer) . '?dialog=1&rule_set=' . $key" show-label>{{ $label }}</x-icon-btn>
                @endforeach
            </div>
        @endif
        <input type="hidden" name="rule_set" value="{{ $ruleSet->key() }}">
        <p class="text-sm sm:col-span-2"><strong>{{ $ruleSet->label() }}</strong></p>
        @if ($values !== null)
            <p class="text-xs text-muted sm:col-span-2">{{ __('customer_deadline.profile.like_last_year') }}</p>
        @endif
        @foreach ($derived as $label => $value)
            <div class="fieldset">
                <span class="fieldset-label">{{ $label }}</span>
                <p class="font-medium">{{ $value }}</p>
                <p class="text-xs text-muted">{{ __('customer_deadline.profile.derived_hint') }}</p>
            </div>
        @endforeach
    </x-form-group>

    <x-form-group :legend="__('customer_deadline.profile.traits')" icon="checklist" tone="primary" cols="2">
        @foreach ($schema as $field)
            <x-field-input :field="$field" :value="$values?->get($field->key)" prefix="traits" id-prefix="cdp-{{ $field->key }}" />
        @endforeach
    </x-form-group>

    <x-form-group :legend="__('customer_deadline.profile.validity')" icon="date_range" tone="primary" cols="2">
        <x-input-field type="date" id="cdp-valid-from" name="valid_from" :label="__('customer_deadline.profile.valid_from')" :value="old('valid_from', $validFrom->format('Y-m-d'))" required :hint="__('customer_deadline.profile.valid_from_hint')" />
        <x-select-field name="responsible_user_id" id="cdp-responsible" :label="__('customer_deadline.column.responsible')">
            <option value="">—</option>
            @foreach ($members as $member)
                @php($memberSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id))
                <option value="{{ $memberSqid }}" @selected($selectedResponsible === $memberSqid)>{{ $member->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    @if ($versions->isNotEmpty())
        <x-form-group :legend="__('customer_deadline.profile.versions')" icon="history" tone="ghost">
            <ul class="space-y-1 text-sm">
                @foreach ($versions->reverse() as $version)
                    <li class="flex justify-between gap-4">
                        <span class="tabular-nums">{{ $version->valid_from->fdate() }} – {{ $version->valid_until?->fdate() ?? __('customer_deadline.open_end') }}</span>
                        <span class="text-muted">{{ $version->responsible?->name ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        </x-form-group>
    @endif

    <x-validation-errors />
</x-modal>
