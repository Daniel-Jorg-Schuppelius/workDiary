{{-- Dialog: neue Reklamation (Feature 072, MVP-247/248) --}}
<x-modal
    :title="__('Neue Reklamation')"
    :eyebrow="__('Reklamation')"
    icon="assignment_return"
    tone="primary"
    :action="route('claims.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Fall anlegen')"
>
    <x-form-group :legend="__('Eingang')" icon="assignment_return" tone="primary" cols="2">
        <x-input-field name="title" :label="__('Titel')" :value="old('title')" required span="2" />
        <x-select-field name="source" :label="__('Eingangskanal')" required>
            @foreach (\App\Enums\Claims\ClaimSource::cases() as $s)
                <option value="{{ $s->value }}" @selected(old('source', 'manual') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="customer_id" :label="__('Kunde (optional)')">
            <option value="">{{ __('ohne Kundenbezug (interner Mangel)') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="priority" :label="__('Priorität')" required>
            @foreach (\App\Models\Claims\ClaimCase::PRIORITIES as $p)
                <option value="{{ $p }}" @selected(old('priority', 'normal') === $p)>{{ __("values.$p") }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="severity" :label="__('Schweregrad')" required>
            @foreach (\App\Models\Claims\ClaimCase::SEVERITIES as $sev)
                <option value="{{ $sev }}" @selected(old('severity', 'minor') === $sev)>{{ __("values.$sev") }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('Beschreibung des Mangels')" rows="3" span="2">{{ old('description') }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('Fristen & Verantwortung')" icon="schedule" tone="primary" cols="2">
        <x-input-field name="due_at" type="date" :label="__('Bearbeitungsfrist (optional)')" :value="old('due_at')" />
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
            <option value="">{{ __('-- später zuweisen --') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('responsible_user_id') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_b2b" value="0">
            <input type="checkbox" id="claim-is-b2b" name="is_b2b" value="1" class="checkbox checkbox-sm" @checked(old('is_b2b'))>
            <label for="claim-is-b2b" class="text-sm">{{ __('B2B-Fall (Rügepflicht § 377 HGB)') }}</label>
        </div>
        <x-input-field name="complaint_notice_at" type="date" :label="__('Rügedatum (B2B)')" :value="old('complaint_notice_at')" />
        <x-input-field name="reporter_name" :label="__('Melder (Name)')" :value="old('reporter_name')" />
        <x-input-field name="reporter_email" type="email" :label="__('Melder (E-Mail)')" :value="old('reporter_email')" />
        <x-input-field name="serial_no" :label="__('Seriennummer (optional)')" :value="old('serial_no')" span="2" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
