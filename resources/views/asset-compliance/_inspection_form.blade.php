{{-- Prüfprotokoll-Formular (MVP-286/287/289); $assignment Pflicht, $schedule optional --}}
<form method="POST"
      action="{{ route('asset-compliance.inspections.record', $assignment) }}"
      enctype="multipart/form-data"
      class="mt-2 grid w-96 gap-2 rounded-box border border-base-300 p-3 text-left">
    @csrf
    @isset($schedule)
        <input type="hidden" name="schedule_id" value="{{ $schedule->sqid }}">
    @endisset

    <x-select-field name="result" :label="__('Ergebnis')" required>
        @foreach (\App\Enums\AssetCompliance\AssetInspectionResult::cases() as $result)
            <option value="{{ $result->value }}">{{ $result->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="performed_at" type="datetime-local" :label="__('Durchgeführt am (leer = jetzt)')" />
    <x-input-field name="valid_until" type="date" :label="__('Gültig bis (leer = Intervall)')" />
    <x-input-field name="external_inspector_name" :label="__('Externer Prüfer (Name)')" />

    @foreach ($assignment->profile?->requirements ?? [] as $i => $requirement)
        <div class="grid grid-cols-2 items-end gap-2">
            <input type="hidden" name="results[{{ $i }}][requirement_id]" value="{{ $requirement->sqid }}">
            <x-input-field name="results[{{ $i }}][value]" type="number" step="0.0001"
                :label="$requirement->label . ($requirement->unit !== null ? ' (' . $requirement->unit . ')' : '')" />
            <span class="text-xs text-base-content/60">
                {{ $requirement->limit_min !== null ? '≥ ' . $requirement->limit_min : '' }}
                {{ $requirement->limit_max !== null ? '≤ ' . $requirement->limit_max : '' }}
            </span>
        </div>
    @endforeach

    <x-select-field name="follow_up" :label="__('Folgeentscheidung')">
        <option value="none">{{ __('Keine / Freigabe') }}</option>
        <option value="recalibration">{{ __('Nachkalibrierung (sperrt)') }}</option>
        <option value="repair">{{ __('Reparatur (sperrt)') }}</option>
        <option value="restricted">{{ __('Eingeschränkte Nutzung') }}</option>
        <option value="block">{{ __('Sperre') }}</option>
        <option value="decommission">{{ __('Aussonderung') }}</option>
        <option value="claim">{{ __('Reklamation eröffnen') }}</option>
    </x-select-field>
    <x-input-field name="follow_up_note" :label="__('Begründung der Maßnahme')" />

    <details>
        <summary class="cursor-pointer text-sm font-medium">{{ __('Zertifikat / Prüfnachweis') }}</summary>
        <div class="mt-2 grid gap-2">
            <x-input-field name="certificate_no" :label="__('Zertifikatsnummer')" />
            <x-input-field name="certificate_issuer" :label="__('Aussteller')" />
            <x-input-field name="certificate_issued_on" type="date" :label="__('Ausgestellt am')" />
            <x-input-field name="certificate_valid_until" type="date" :label="__('Gültig bis')" />
            <x-input-field name="certificate_measurement_range" :label="__('Messbereich')" />
            <x-input-field name="certificate_tolerance" :label="__('Toleranz')" />
            <x-input-field name="certificate_file" type="file" :label="__('Dokument (Hash wird gespeichert)')" />
        </div>
    </details>

    <x-input-field name="signature_name" :label="__('Unterschrift (Name)')" />
    <x-textarea-field name="note" :label="__('Bemerkung')" rows="2"></x-textarea-field>

    <button type="submit" class="btn btn-sm btn-primary">{{ __('Prüfung dokumentieren') }}</button>
</form>
