{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bürgschaft anlegen/bearbeiten (Feature 114, MVP-603).
--}}
<x-modal
    :title="$guarantee === null ? __('guarantee.action.create') : __('guarantee.action.edit')"
    :eyebrow="__('guarantee.title')"
    icon="gpp_maybe"
    :action="$guarantee === null ? route('guarantees.store') : route('guarantees.update', $guarantee)"
    :method="$guarantee === null ? 'POST' : 'PUT'"
    :submit-label="__('Speichern')"
>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="g-form-direction"><span class="label-text">{{ __('guarantee.column.direction') }}</span></label>
            <select id="g-form-direction" name="direction" class="select select-bordered w-full">
                @foreach (\App\Enums\Guarantee\GuaranteeDirection::cases() as $d)
                    <option value="{{ $d->value }}" @selected(old('direction', $guarantee?->direction?->value) === $d->value)>{{ $d->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="g-form-kind"><span class="label-text">{{ __('guarantee.column.kind') }}</span></label>
            <select id="g-form-kind" name="kind" class="select select-bordered w-full">
                @foreach (\App\Enums\Guarantee\GuaranteeKind::cases() as $k)
                    <option value="{{ $k->value }}" @selected(old('kind', $guarantee?->kind?->value) === $k->value)>{{ $k->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="reference" type="text" maxlength="64"
                       :label="__('guarantee.column.reference')"
                       :value="old('reference', $guarantee?->reference)" />
        <x-input-field name="amount" type="number" step="0.01" min="0.01" required
                       :label="__('guarantee.column.amount')"
                       :value="old('amount', $guarantee?->amount?->toFloat())" />
    </div>

    {{-- Ausstellung/Befristung als Zeitraum: Die Befristung darf nie vor der
         Ausstellung liegen — das koppelt die Komponente selbst. --}}
    <x-date-range layout="split" from-name="issued_on" to-name="expires_on"
                  :from-label="__('guarantee.column.issued_on')"
                  :to-label="__('guarantee.column.expires_on')"
                  :from="old('issued_on', $guarantee?->issued_on?->toDateString())"
                  :to="old('expires_on', $guarantee?->expires_on?->toDateString())" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="issuer_name" type="text" maxlength="191"
                       :label="__('guarantee.column.issuer')"
                       :value="old('issuer_name', $guarantee?->issuer_name)"
                       :hint="__('guarantee.issuer_hint')" />
        <div>
            <label class="label" for="g-form-issuer-supplier"><span class="label-text">{{ __('guarantee.issuer_supplier') }}</span></label>
            <select id="g-form-issuer-supplier" name="issuer_supplier_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->sqid }}" @selected(old('issuer_supplier_id', $guarantee?->issuer_supplier_id !== null ? \App\Support\Sqid::encode(\App\Models\Supplier::class, (int) $guarantee->issuer_supplier_id) : null) === $s->sqid)>{{ $s->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="g-form-customer"><span class="label-text">{{ __('guarantee.column.customer') }}</span></label>
            <select id="g-form-customer" name="customer_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->sqid }}" @selected(old('customer_id', $guarantee?->customer_id !== null ? \App\Support\Sqid::encode(\App\Models\Customer::class, (int) $guarantee->customer_id) : null) === $c->sqid)>{{ $c->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="g-form-supplier"><span class="label-text">{{ __('guarantee.column.supplier') }}</span></label>
            <select id="g-form-supplier" name="supplier_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->sqid }}" @selected(old('supplier_id', $guarantee?->supplier_id !== null ? \App\Support\Sqid::encode(\App\Models\Supplier::class, (int) $guarantee->supplier_id) : null) === $s->sqid)>{{ $s->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="g-form-project"><span class="label-text">{{ __('guarantee.column.project') }}</span></label>
            <select id="g-form-project" name="project_id" class="select select-bordered w-full">
                <option value="">—</option>
                <x-project-options :projects="$projects" :selected="old('project_id', $guarantee?->project_id !== null ? \App\Support\Sqid::encode(\App\Models\Project::class, (int) $guarantee->project_id) : '')" />
            </select>
        </div>
        <div>
            <label class="label" for="g-form-responsible"><span class="label-text">{{ __('guarantee.column.responsible') }}</span></label>
            <select id="g-form-responsible" name="responsible_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($users as $u)
                    <option value="{{ $u->sqid }}" @selected(old('responsible_user_id', $guarantee?->responsible_user_id !== null ? \App\Support\Sqid::encode(\App\Models\User::class, (int) $guarantee->responsible_user_id) : null) === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-textarea-field name="note" rows="3" :label="__('guarantee.column.note')" :value="old('note', $guarantee?->note)" />
</x-modal>
