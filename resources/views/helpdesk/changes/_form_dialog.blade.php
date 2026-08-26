{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Modal (Feature 065, MVP-157): Change anlegen. Typ-Regeln erzwingt der
  ChangeService — die UI bietet für standard NUR freigegebene Vorlagen an,
  verlangt Rollback bei normal/emergency und blendet die Genehmigungskette
  bei standard aus (vorab genehmigt über die Vorlage). Erwartet:
  $templates (nur approved), $problems, $tickets, $assets, $orgUsers,
  $roles, $preselectedProblem.
--}}
@php
    $stepTemplate = ['type' => 'role', 'user' => '', 'role' => ''];
    $preselectedProblemSqid = $preselectedProblem !== null
        ? \App\Support\Sqid::encode(\App\Models\Problem::class, (int) $preselectedProblem)
        : '';
@endphp

<x-modal
    :title="__('Neuer Change')"
    :eyebrow="__('Service Desk')"
    icon="published_with_changes"
    tone="primary"
    size="lg"
    :action="route('servicedesk.changes.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    {{-- Typ-Umschaltung via Alpine.data("reveal") (components.js) — CSP-Build-konform. --}}
    <div class="contents" x-data="reveal(@js(old('change_type', 'normal')))">
        <x-form-group :legend="__('Change')" icon="published_with_changes" tone="primary" cols="2">
            <x-input-field name="title" :label="__('Titel')" required minlength="3" maxlength="200" span="2" :value="old('title')" />

            <x-select-field name="change_type" :label="__('Typ')" required x-model="value"
                            :hint="__('Standard-Changes entstehen nur aus einer freigegebenen Vorlage; normal/emergency brauchen einen Rollback-Plan.')">
                <option value="standard">{{ __('Standard') }}</option>
                <option value="normal">{{ __('Normal') }}</option>
                <option value="emergency">{{ __('Emergency') }}</option>
            </x-select-field>

            <div class="fieldset" x-show="is('standard')" x-cloak>
                <label class="fieldset-label" for="change_template_id">{{ __('Freigegebene Vorlage') }}</label>
                <select id="change_template_id" name="change_template_id" class="select select-bordered w-full">
                    <option value="">—</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->sqid }}" @selected(old('change_template_id') === $template->sqid)>
                            {{ $template->name }} (v{{ $template->version }})
                        </option>
                    @endforeach
                </select>
                @error('change_template_id')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
                @error('change_type')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>

            <x-textarea-field name="reason" :label="__('Grund')" rows="2" maxlength="10000" span="2" :value="old('reason')" />
            <x-textarea-field name="scope" :label="__('Umfang')" rows="2" maxlength="10000" span="2" :value="old('scope')" />

            <x-select-field name="risk" :label="__('Risiko')" error="risk">
                <option value="">—</option>
                <option value="1" @selected(old('risk') === '1')>{{ __('Niedrig') }}</option>
                <option value="2" @selected(old('risk') === '2')>{{ __('Mittel') }}</option>
                <option value="3" @selected(old('risk') === '3')>{{ __('Hoch') }}</option>
            </x-select-field>
            <x-select-field name="impact" :label="__('Auswirkung')" error="impact">
                <option value="">—</option>
                <option value="1" @selected(old('impact') === '1')>{{ __('Niedrig') }}</option>
                <option value="2" @selected(old('impact') === '2')>{{ __('Mittel') }}</option>
                <option value="3" @selected(old('impact') === '3')>{{ __('Hoch') }}</option>
            </x-select-field>
            <x-select-field name="urgency" :label="__('Dringlichkeit')" error="urgency">
                <option value="">—</option>
                <option value="1" @selected(old('urgency') === '1')>{{ __('Niedrig') }}</option>
                <option value="2" @selected(old('urgency') === '2')>{{ __('Mittel') }}</option>
                <option value="3" @selected(old('urgency') === '3')>{{ __('Hoch') }}</option>
            </x-select-field>

            <x-date-range class="md:col-span-2"
                type="datetime-local"
                fromName="window_from"
                toName="window_to"
                :from="old('window_from')"
                :to="old('window_to')"
                :fromLabel="__('Fenster von')"
                :toLabel="__('Fenster bis')"
                layout="split" />
        </x-form-group>

        <x-form-group :legend="__('Pläne')" icon="checklist" tone="primary" cols="1">
            <x-textarea-field name="implementation_plan" :label="__('Umsetzungsplan')" rows="2" maxlength="20000" :value="old('implementation_plan')" />
            <x-textarea-field name="test_plan" :label="__('Testplan')" rows="2" maxlength="20000" :value="old('test_plan')" />
            <div class="fieldset">
                <label class="fieldset-label" for="rollback_plan">
                    {{ __('Rollback-Plan') }}
                    <span class="text-muted" x-show="isNot('standard')">({{ __('Pflicht') }})</span>
                </label>
                <textarea id="rollback_plan" name="rollback_plan" rows="2" maxlength="20000"
                          class="textarea textarea-bordered w-full @error('rollback_plan') textarea-error @enderror"
                          :required="isNot('standard')">{{ old('rollback_plan') }}</textarea>
                @error('rollback_plan')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('Genehmigungskette')" icon="approval" tone="info">
            <div x-show="isNot('standard')"
                 x-data="repeater"
                 data-prefix="approval_steps"
                 data-items="[]"
                 data-template="{{ json_encode($stepTemplate) }}"
                 class="space-y-2 sm:col-span-2">
                <template x-for="(it, i) in items" :key="i">
                    <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-6 items-end">
                            <div class="fieldset md:col-span-2">
                                <label :for="fieldName(i, 'type')" class="fieldset-label">{{ __('Schritt-Typ') }}</label>
                                <select :id="fieldName(i, 'type')" :name="fieldName(i, 'type')" x-model="it.type"
                                        class="select select-sm select-bordered w-full">
                                    <option value="role">{{ __('Rolle') }}</option>
                                    <option value="user">{{ __('Benutzer') }}</option>
                                </select>
                            </div>
                            <div class="fieldset md:col-span-3" x-show="it.type === 'user'">
                                <label :for="fieldName(i, 'user')" class="fieldset-label">{{ __('Genehmiger (Benutzer)') }}</label>
                                <select :id="fieldName(i, 'user')" :name="fieldName(i, 'user')" x-model="it.user"
                                        class="select select-sm select-bordered w-full">
                                    <option value="">—</option>
                                    @foreach ($orgUsers as $orgUser)
                                        <option value="{{ $orgUser->sqid }}">{{ $orgUser->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="fieldset md:col-span-3" x-show="it.type === 'role'">
                                <label :for="fieldName(i, 'role')" class="fieldset-label">{{ __('Genehmiger (Rolle)') }}</label>
                                <select :id="fieldName(i, 'role')" :name="fieldName(i, 'role')" x-model="it.role"
                                        class="select select-sm select-bordered w-full">
                                    <option value="">—</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex justify-end">
                                <x-icon-btn icon="close" tone="error" type="button"
                                            :label="__('Schritt entfernen')" @click="remove(i)" />
                            </div>
                        </div>
                    </div>
                </template>

                <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                    {{ __('Genehmigungsschritt hinzufügen') }}
                </x-icon-btn>
                <p class="text-xs text-muted">{{ __('Ohne Schritte gilt der Change sofort als genehmigt. Emergency kürzt auf EINEN Schritt; Selbstfreigabe ist immer gesperrt.') }}</p>
            </div>
            <p class="text-xs text-muted sm:col-span-2" x-show="is('standard')" x-cloak>
                {{ __('Standard-Changes sind über die freigegebene Vorlage vorab genehmigt — keine Kette nötig.') }}
            </p>
        </x-form-group>

        <x-form-group :legend="__('Verknüpfungen')" icon="link" tone="info" cols="2">
            <x-select-field name="problem_id" :label="__('Problem')" error="problem_id">
                <option value="">—</option>
                @foreach ($problems as $problem)
                    <option value="{{ $problem->sqid }}" @selected(old('problem_id', $preselectedProblemSqid) === $problem->sqid)>{{ $problem->title }}</option>
                @endforeach
            </x-select-field>

            <x-select-field name="ticket_ids[]" :label="__('Tickets')" multiple error="ticket_ids">
                @foreach ($tickets as $ticket)
                    <option value="{{ $ticket->sqid }}" @selected(in_array($ticket->sqid, (array) old('ticket_ids', []), true))>
                        {{ $ticket->ticket_no }} — {{ \Illuminate\Support\Str::limit($ticket->title, 60) }}
                    </option>
                @endforeach
            </x-select-field>

            <x-select-field name="asset_ids[]" :label="__('Betroffene Assets')" multiple error="asset_ids" span="2">
                @foreach ($assets as $asset)
                    <option value="{{ $asset->sqid }}" @selected(in_array($asset->sqid, (array) old('asset_ids', []), true))>
                        {{ $asset->asset_no }} — {{ $asset->name }}
                    </option>
                @endforeach
            </x-select-field>
        </x-form-group>
    </div>
</x-modal>
