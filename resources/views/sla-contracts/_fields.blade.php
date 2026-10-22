{{-- Felder eines SLA-Vertrags: Anlegen in der Liste, Bearbeiten im Dialog der Detailseite. --}}
@php($contract ??= null)
<x-input-field name="code" :label="__('Code')" required maxlength="60" :value="old('code', $contract?->code)" />
<x-input-field name="label" :label="__('Bezeichnung')" required maxlength="180" :value="old('label', $contract?->label)" />
{{-- Optionale Projektbindung (W5.4): projektgebundener Vertrag gewinnt vor Kunden-/Default-Vertrag. --}}
<x-project-select :label="__('Projekt (optional)')" :placeholder="__('ohne Projektbezug (kunden-/org-weit)')"
    span="2" :projects="$projects" :selected="(string) old('project_id', $contract?->project?->sqid)" />
<x-textarea-field span="2" name="priority_table"
                  :label="__('Prioritätstabelle (JSON)')"
                  rows="3"
                  required
                  class="font-mono text-xs">{{ old('priority_table', $contract ? \CommonToolkit\Helper\Data\JsonHelper::encode($contract->priority_table, JSON_UNESCAPED_UNICODE) : '{"normal": {"reaction_minutes": 240, "resolution_minutes": 2880} }') }}</x-textarea-field>
<x-textarea-field span="2" name="business_hours"
                  :label="__('Geschäftszeiten (JSON, leer = Kalenderzeit)')"
                  rows="2"
                  class="font-mono text-xs">{{ old('business_hours', $contract?->business_hours ? \CommonToolkit\Helper\Data\JsonHelper::encode($contract->business_hours) : '') }}</x-textarea-field>
<div class="fieldset">
    <span class="fieldset-label">{{ __('SLA-Pause bei') }}</span>
    <label class="label cursor-pointer justify-start gap-2"><input type="checkbox" name="pause_rules[]" value="waiting_customer" class="checkbox checkbox-sm" @checked(in_array('waiting_customer', (array) old('pause_rules', $contract?->pause_rules ?? []), true))>{{ __('Wartet auf Kunde') }}</label>
    <label class="label cursor-pointer justify-start gap-2"><input type="checkbox" name="pause_rules[]" value="waiting_external" class="checkbox checkbox-sm" @checked(in_array('waiting_external', (array) old('pause_rules', $contract?->pause_rules ?? []), true))>{{ __('Wartet auf Dritte') }}</label>
    <label class="label cursor-pointer justify-start gap-2"><input type="checkbox" name="pause_rules[]" value="paused" class="checkbox checkbox-sm" @checked(in_array('paused', (array) old('pause_rules', $contract?->pause_rules ?? []), true))>{{ __('Pausiert') }}</label>
</div>
<div class="fieldset">
    <x-checkbox-field name="is_default" :label="__('Standardvertrag')" :checked="(bool) old('is_default', $contract?->is_default ?? false)" />
    @if ($contract)
        <x-checkbox-field name="is_active" :label="__('Aktiv')" :checked="(bool) old('is_active', $contract->is_active)" />
    @endif
    <x-checkbox-field name="is_ola" :label="__('OLA (internes Ziel)')" :hint="__('Internes Ziel je Team, kein Kundenversprechen.')" :checked="(bool) old('is_ola', $contract?->is_ola ?? false)" />
</div>
