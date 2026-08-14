{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Entsorgungsakte anlegen/bearbeiten (Feature 100, MVP-474).
     Erwartet: $job (DisposalJob|null), $customers, $sites, $users, $diaryEntries. --}}
<x-modal
    :title="$job !== null ? __('disposal.form.title_edit') : __('disposal.form.title_create')"
    :eyebrow="__('disposal.eyebrow')"
    icon="recycling"
    tone="primary"
    :action="$job !== null ? route('disposal.update', $job) : route('disposal.store')"
    :method="$job !== null ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$job !== null ? __('Speichern') : __('disposal.form.submit_create')"
>
    {{-- Regel 12: Alpine nicht am auto-<form>, sondern am Body-Wrapper. Der
         Einsatzort-Select filtert reaktiv nach dem gewählten Kunden. --}}
    <div x-data="{
            customerId: @js((string) old('customer_id', $job?->customer_id ?? '')),
            sites: @js($sites->map(fn ($s) => ['id' => (string) $s->id, 'name' => $s->name, 'customer' => (string) $s->customer_id])->values()),
         }"
         class="space-y-4">
        <x-form-group :legend="__('disposal.form.group_assignment')" icon="recycling" tone="primary" cols="2">
            <x-select-field name="customer_id" :label="__('Kunde')" required x-model="customerId">
                <option value="">{{ __('-- Kunde wählen --') }}</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}" @selected((string) old('customer_id', $job?->customer_id) === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="site_id" :label="__('disposal.form.site')">
                <option value="">{{ __('disposal.form.site_none') }}</option>
                <template x-for="site in sites.filter((s) => s.customer === customerId)" :key="site.id">
                    <option :value="site.id" x-text="site.name" :selected="site.id === @js((string) old('site_id', $job?->site_id ?? ''))"></option>
                </template>
            </x-select-field>
            <x-select-field name="diary_entry_id" :label="__('disposal.form.diary_entry')">
                <option value="">{{ __('disposal.form.diary_entry_none') }}</option>
                @foreach ($diaryEntries as $entry)
                    <option value="{{ $entry->id }}" @selected((string) old('diary_entry_id', $job?->diary_entry_id) === (string) $entry->id)>#{{ $entry->id }} {{ $entry->title }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
                <option value="">{{ __('-- später zuweisen --') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) old('responsible_user_id', $job?->responsible_user_id) === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-select-field>
        </x-form-group>

        <x-form-group :legend="__('disposal.form.group_pickup')" icon="local_shipping" tone="primary" cols="2">
            <x-input-field name="picked_up_on" type="date" :label="__('disposal.field.picked_up_on')"
                           :value="old('picked_up_on', $job?->picked_up_on?->format('Y-m-d'))" />
            <x-input-field name="total_weight_kg" type="number" step="0.001" min="0" :label="__('disposal.field.total_weight')"
                           :value="old('total_weight_kg', $job?->total_weight_kg)" />
            <x-textarea-field name="notes" :label="__('Notizen')" rows="2" span="2">{{ old('notes', $job?->notes) }}</x-textarea-field>
        </x-form-group>

        <x-validation-errors />
    </div>
</x-modal>
