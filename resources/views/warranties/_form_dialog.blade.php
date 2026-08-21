{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gewährleistungsfrist anlegen (Feature 115, MVP-604). Ohne eigenes Enddatum
  ergibt es sich aus der Rechtsgrundlage; ein abweichendes Ende braucht eine
  Begründung.
--}}
<x-modal
    :title="__('warranty.action.create')"
    :eyebrow="__('warranty.title')"
    icon="shield_with_heart"
    :action="route('warranties.store')"
    method="POST"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-base-content/70">{{ __('warranty.dialog_hint') }}</p>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="w-form-side"><span class="label-text">{{ __('warranty.column.side') }}</span></label>
            <select id="w-form-side" name="side" class="select select-bordered w-full">
                @foreach (\App\Enums\Warranty\WarrantySide::cases() as $side)
                    <option value="{{ $side->value }}" @selected(old('side') === $side->value)>{{ $side->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="w-form-basis"><span class="label-text">{{ __('warranty.column.basis') }}</span></label>
            <select id="w-form-basis" name="basis" class="select select-bordered w-full">
                @foreach (\App\Enums\Warranty\WarrantyBasis::cases() as $basis)
                    <option value="{{ $basis->value }}" @selected(old('basis') === $basis->value)>{{ $basis->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-date-range layout="split" from-name="starts_on" to-name="ends_on" from-required
                  :from-label="__('warranty.column.starts_on')"
                  :to-label="__('warranty.column.ends_on')"
                  :from="old('starts_on', now()->toDateString())"
                  :to="old('ends_on', '')" />

    <x-input-field name="override_reason" type="text" maxlength="500"
                   :label="__('warranty.override_reason')"
                   :value="old('override_reason', '')"
                   :hint="__('warranty.override_reason_hint')" />

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label" for="w-form-project"><span class="label-text">{{ __('warranty.column.project') }}</span></label>
            <select id="w-form-project" name="project_id" class="select select-bordered w-full">
                <option value="">—</option>
                <x-project-options :projects="$projects" :selected="old('project_id', '')" />
            </select>
        </div>
        <div>
            <label class="label" for="w-form-protocol"><span class="label-text">{{ __('warranty.column.protocol') }}</span></label>
            <select id="w-form-protocol" name="protocol_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($protocols as $protocol)
                    <option value="{{ $protocol->sqid }}" @selected(old('protocol_id') === $protocol->sqid)>{{ $protocol->title }} ({{ optional($protocol->occurred_at)->fdate() }})</option>
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
                    <option value="{{ $c->sqid }}" @selected(old('customer_id') === $c->sqid)>{{ $c->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="w-form-supplier"><span class="label-text">{{ __('warranty.column.supplier') }}</span></label>
            <select id="w-form-supplier" name="supplier_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->sqid }}" @selected(old('supplier_id') === $s->sqid)>{{ $s->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="trade" type="text" maxlength="120"
                       :label="__('warranty.column.trade')"
                       :value="old('trade', '')" />
        <div>
            <label class="label" for="w-form-responsible"><span class="label-text">{{ __('warranty.column.responsible') }}</span></label>
            <select id="w-form-responsible" name="responsible_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($users as $u)
                    <option value="{{ $u->sqid }}" @selected(old('responsible_user_id') === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-textarea-field name="note" rows="2" :label="__('warranty.column.note')" :value="old('note', '')" />
</x-modal>
