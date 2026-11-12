{{-- Felder eines SLA-Vertrags: Anlegen in der Liste, Bearbeiten im Dialog der Detailseite. --}}
@php
    $contract ??= null;
@endphp
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
<x-select-field name="ola_team_id" :id="'ola-team-' . ($contract?->sqid ?? 'new')" :label="__('sla.form.olaTeam')" :hint="__('sla.form.olaTeamHint')">
    <option value="">{{ __('— Kein Team —') }}</option>
    @foreach ($teams as $team)
        <option value="{{ $team->sqid }}" @selected((string) old('ola_team_id', \App\Support\Sqid::encodeOrNull(\App\Models\Platform\Team::class, $contract?->ola_team_id)) === $team->sqid)>{{ $team->name }}</option>
    @endforeach
</x-select-field>
@error('is_ola')<p class="text-error text-sm md:col-span-2">{{ $message }}</p>@enderror

{{-- Eskalationsstufen (MVP-1548) in der Form, die der SlaTicketScan liest. --}}
@php
    $escalationRows = old('escalation');
    if (! is_array($escalationRows)) {
        $escalationRows = array_map(static fn (array $step): array => [
            'after_minutes' => $step['after_minutes'] ?? null,
            'notify' => match (true) {
                (string) ($step['notify'] ?? '') === '' => '',
                ctype_digit((string) $step['notify']) => 'user:' . \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $step['notify']),
                default => 'role:' . $step['notify'],
            },
        ], array_values((array) ($contract?->escalation_chain ?? [])));
    }
    $escalationRows = array_pad(array_values($escalationRows), max(4, count($escalationRows) + 1), ['after_minutes' => null, 'notify' => '']);
@endphp
<div class="fieldset md:col-span-2" data-sla-escalation>
    <span class="fieldset-label">{{ __('sla.form.escalation') }}</span>
    <div class="grid gap-2 grid-cols-[8rem_1fr]">
        @foreach ($escalationRows as $i => $row)
            <input type="number" min="1" max="525600" name="escalation[{{ $i }}][after_minutes]" class="input input-sm input-bordered w-full"
                   aria-label="{{ __('sla.form.escalationAfter', ['level' => $i + 1]) }}" placeholder="{{ __('sla.form.escalationAfterShort') }}"
                   value="{{ $row['after_minutes'] ?? '' }}">
            <select name="escalation[{{ $i }}][notify]" class="select select-sm select-bordered w-full" aria-label="{{ __('sla.form.escalationNotify', ['level' => $i + 1]) }}">
                <option value="">{{ __('sla.form.escalationAssignee') }}</option>
                <optgroup label="{{ __('sla.form.escalationRoles') }}">
                    @foreach ($roleNames as $roleName)
                        <option value="role:{{ $roleName }}" @selected(($row['notify'] ?? '') === 'role:' . $roleName)>{{ \App\Support\Trans::or("user.role.{$roleName}", $roleName) }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('sla.form.escalationUsers') }}">
                    @foreach ($orgUsers as $orgUser)
                        <option value="user:{{ $orgUser->sqid }}" @selected(($row['notify'] ?? '') === 'user:' . $orgUser->sqid)>{{ $orgUser->name }}</option>
                    @endforeach
                </optgroup>
            </select>
            @error("escalation.{$i}.after_minutes")<p class="text-error text-sm col-span-2">{{ $message }}</p>@enderror
            @error("escalation.{$i}.notify")<p class="text-error text-sm col-span-2">{{ $message }}</p>@enderror
        @endforeach
    </div>
    <p class="text-xs text-muted mt-1">{{ __('sla.form.escalationHint') }}</p>
</div>
