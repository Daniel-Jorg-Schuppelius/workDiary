{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  VOB/B-Schreiben anlegen/bearbeiten (Feature 062, MVP-728). Die Belegart steht
  fest (Route), der Rechtsverweis ist vorbelegt und bleibt Text.
--}}
<x-modal
    :title="$notice ? __('construction.action.edit') : $kind->label()"
    :eyebrow="__('construction.title')"
    icon="report"
    :action="$notice ? route('construction-notices.update', $notice) : route('construction-notices.store')"
    :method="$notice ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
>
    <input type="hidden" name="kind" value="{{ $kind->value }}">

    <p class="text-sm text-base-content/70">{{ __('construction.dialog_hint') }}</p>

    <x-input-field name="subject" type="text" maxlength="200" required
                   :label="__('construction.column.subject')"
                   :value="old('subject', $notice->subject ?? '')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="occurred_on" type="date" required
                       :label="__('construction.column.occurred_on')"
                       :value="old('occurred_on', $notice?->occurred_on?->toDateString() ?? now()->toDateString())" />
        <x-input-field name="legal_reference" type="text" maxlength="120"
                       :label="__('construction.field.legal_reference')"
                       :value="old('legal_reference', $notice->legal_reference ?? \App\Models\Construction\ConstructionNotice::defaultLegalReference($kind))"
                       :hint="__('construction.field.legal_reference_hint')" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="cn-form-project"><span class="label-text">{{ __('construction.column.project') }}</span></label>
            <select id="cn-form-project" name="project_id" class="select select-bordered w-full">
                <option value="">—</option>
                <x-project-options :projects="$projects" :selected="old('project_id', $notice?->project_id ? \App\Support\Sqid::encode(\App\Models\Project::class, $notice->project_id) : '')" />
            </select>
        </div>
        <div>
            <label class="label" for="cn-form-site"><span class="label-text">{{ __('construction.field.site') }}</span></label>
            <select id="cn-form-site" name="site_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->sqid }}" @selected(old('site_id', $notice?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="cn-form-customer"><span class="label-text">{{ __('construction.field.customer') }}</span></label>
            <select id="cn-form-customer" name="customer_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected(old('customer_id', $notice?->customer_id) == $customer->id)>{{ $customer->name ?: $customer->company }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="cn-form-diary"><span class="label-text">{{ __('construction.field.diary_entry') }}</span></label>
            <select id="cn-form-diary" name="diary_entry_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($diaryEntries as $entry)
                    <option value="{{ $entry->sqid }}" @selected(old('diary_entry_id', $notice?->diary_entry_id) == $entry->id)>{{ $entry->title }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="recipient_name" type="text" maxlength="200"
                       :label="__('construction.field.recipient_name')"
                       :value="old('recipient_name', $notice->recipient_name ?? '')" />
        <x-input-field name="recipient_email" type="email" maxlength="190"
                       :label="__('construction.field.recipient_email')"
                       :value="old('recipient_email', $notice->recipient_email ?? '')" />
    </div>

    <x-textarea-field name="facts" rows="6" required
                      :label="__('construction.field.facts')"
                      :value="old('facts', $notice->facts ?? '')"
                      :hint="__('construction.field.facts_hint')" />

    <x-textarea-field name="impact_schedule" rows="3"
                      :label="__('construction.field.impact_schedule')"
                      :value="old('impact_schedule', $notice->impact_schedule ?? '')" />

    <x-textarea-field name="impact_cost" rows="3"
                      :label="__('construction.field.impact_cost')"
                      :value="old('impact_cost', $notice->impact_cost ?? '')" />

    <x-checkbox-field name="claims_time_extension" value="1"
                      :label="__('construction.field.claims_time_extension')"
                      :checked="(bool) old('claims_time_extension', $notice->claims_time_extension ?? false)"
                      :hint="__('construction.field.claims_time_extension_hint')" />
</x-modal>
