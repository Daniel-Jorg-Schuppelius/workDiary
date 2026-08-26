{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $project, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $project ? route('projects.update', $project) : route('projects.store');
    $dialogUrl = ($project ? route('projects.edit', $project) : route('projects.create')) . '?dialog=1';
    $customers = \App\Models\Customer::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name']);
    // Mögliche Parents: alle Projekte außer dem aktuellen und dessen Subtree.
    $excludeIds = [];
    if ($project) {
        $excludeIds = $project->descendants()->pluck('id')->all();
        $excludeIds[] = $project->id;
    }
    $parentOptions = \App\Models\Project::query()
        ->when($excludeIds, fn($q) => $q->whereNotIn('id', $excludeIds))
        ->orderBy('name')
        ->get(['id', 'name', 'customer_id', 'foreign_customer_id']);
    // Map Parent-Projekt-Sqid → Kunden-Sqid (die Selects nutzen Sqids als Werte,
    // daher müssen Schlüssel und Auto-Befüllung ebenfalls Sqids sein, keine int-IDs).
    $parentCustomerSqids = $parentOptions->mapWithKeys(fn($p) => [
        $p->sqid => $p->customer_id ? \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) : '',
    ]);
    // Fremdkunden gruppiert nach Kunden-Sqid (für die clientseitige Filterung).
    $foreignCustomersByCustomer = \App\Models\ForeignCustomer::query()
        ->whereNull('archived_at')
        ->orderBy('name')
        ->get(['id', 'name', 'customer_id'])
        ->groupBy(fn($fc) => $fc->customer_id ? \App\Support\Sqid::encode(\App\Models\Customer::class, $fc->customer_id) : '')
        ->map(fn($group) => $group->map(fn($fc) => [
            'sqid' => \App\Support\Sqid::encode(\App\Models\ForeignCustomer::class, $fc->id),
            'name' => $fc->name,
        ])->values());
    $teams = $teams ?? collect();
    $orgUsers = $orgUsers ?? collect();
    $assignedTeamIds = $assignedTeamIds ?? [];
    $assignedMemberIds = $assignedMemberIds ?? [];
    $initialCustomerSqid = (string) old('customer_id', $project?->customer_id ? \App\Support\Sqid::encode(\App\Models\Customer::class, $project->customer_id) : '');
    $initialForeignCustomerSqid = (string) old('foreign_customer_id', $project?->foreign_customer_id ? \App\Support\Sqid::encode(\App\Models\ForeignCustomer::class, $project->foreign_customer_id) : '');
@endphp

<x-modal
    :title="$project ? __('Projekt bearbeiten') : __('Neues Projekt')"
    :eyebrow="__('Projekt')"
    icon="folder"
    :badge="$project?->statusLabel()"
    :badge-tone="$project?->statusTone() ?? 'ghost'"
    tone="primary"
    :action="$action"
    :method="$project ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$project ? __('Speichern') : __('Anlegen')">
    <div x-data="projectForm"
          data-parent-id="{{ (string) old('parent_id', \App\Support\Sqid::encode(\App\Models\Project::class, $project?->parent_id)) }}"
          data-parent-customers="{{ json_encode($parentCustomerSqids) }}"
          data-customer-id="{{ $initialCustomerSqid }}"
          data-foreign-customers="{{ json_encode($foreignCustomersByCustomer) }}"
          data-foreign-customer-id="{{ $initialForeignCustomerSqid }}"
         x-effect="sync()"
         class="space-y-4">
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <x-form-group :legend="__('Stammdaten')" icon="folder" tone="primary">
            <x-input-field name="name" :label="__('Name')" required maxlength="120" :value="old('name', $project?->name)" />

            <x-textarea-field name="description" :label="__('Beschreibung')" rows="3" maxlength="2000" :value="old('description', $project?->description)" />

            {{-- Zusätzliche Begriffe für die Zuordnung importierter Zeiten (MVP-483);
                 der Projektname selbst wirkt immer, ohne Pflege. --}}
            <x-input-field name="keywords" :label="__('Schlüsselwörter (Zeitzuordnung)')" maxlength="500"
                           :value="old('keywords', is_array($project?->keywords) ? implode(', ', $project->keywords) : '')"
                           :hint="__('Kommagetrennt. Enthält der Text einer importierten Zeit (Fernwartung, Toggl, …) einen dieser Begriffe oder den Projektnamen, wird sie diesem Projekt zugeordnet — nur bei eindeutigem Treffer.')" />
        </x-form-group>

        <x-form-group :legend="__('Zuordnung')" icon="link" tone="info">
            <x-project-select name="parent_id" :label="__('Übergeordnetes Projekt')"
                :placeholder="__('— Top-Level (kein Parent) —')"
                :projects="$parentOptions"
                :selected="(string) old('parent_id', \App\Support\Sqid::encode(\App\Models\Project::class, $project?->parent_id))"
                :hint="__('Sub-Projekte erben Customer und Abrechnung vom Parent.')"
                x-model="parentId" />

            <div class="fieldset" x-show="noParent" x-cloak>
                <label for="customer_id" class="fieldset-label">{{ __('Kunde') }}</label>
                <select id="customer_id" name="customer_id" class="select select-bordered w-full" x-ref="customerSelect" x-model="customerId" :disabled="hasParent">
                    <option value="">{{ __('— Kein Kunde —') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $project?->customer_id)) === $customer->sqid)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="showForeignCustomer" x-cloak>
                <label for="foreign_customer_id" class="fieldset-label">{{ __('Fremdkunde') }}</label>
                <select id="foreign_customer_id" name="foreign_customer_id" class="select select-bordered w-full"
                        x-model="foreignCustomerId" :disabled="hasParent">
                    <option value="">{{ __('— Kein Fremdkunde —') }}</option>
                    <template x-for="fc in availableForeignCustomers" :key="fc.sqid">
                        <option :value="fc.sqid" x-text="fc.name" :selected="isSelectedForeign(fc)"></option>
                    </template>
                </select>
                @error('foreign_customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-muted" x-show="hasParent" x-cloak>
                {{ __('Customer wird vom Parent-Projekt übernommen.') }}
            </p>
        </x-form-group>

        <x-form-group :legend="__('Status & Zeitraum')" icon="event" tone="success">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-select-field name="status" :label="__('Status')">
                    @foreach (\App\Enums\Project\ProjectStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $project?->status?->value ?? \App\Enums\Project\ProjectStatus::Active->value) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </x-select-field>

                <x-date-range
                    layout="join"
                    class="sm:col-span-2"
                    :from="old('starts_on', $project?->starts_on?->format('Y-m-d'))"
                    :to="old('ends_on', $project?->ends_on?->format('Y-m-d'))"
                    fromName="starts_on"
                    toName="ends_on"
                    :fromLabel="__('Start')"
                    :toLabel="__('Ende')"
                    :label="__('Start – Ende')"
                    size=""
                    formControl
                    :toError="$errors->first('ends_on')"
                    :fromError="$errors->first('starts_on')"
                />
            </div>
        </x-form-group>

        <x-form-group :legend="__('Teams & Mitglieder')" icon="groups" tone="secondary">
            <div class="fieldset">
                <span class="fieldset-label">{{ __('Zuständige Teams') }}</span>
                @php($selectedTeams = (array) old('team_ids', array_map(fn($id) => \App\Support\Sqid::encode(\App\Models\Team::class, $id), $assignedTeamIds)))
                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                    @forelse ($teams as $team)
                        <label class="label cursor-pointer justify-start gap-2 rounded px-2 hover:bg-base-200">
                            <input type="checkbox" name="team_ids[]" value="{{ $team->sqid }}" class="checkbox checkbox-sm"
                                   @checked(in_array($team->sqid, $selectedTeams, true))>
                            <span class="text-sm">@if ($team->color)<span class="mr-1 inline-block h-2 w-2 rounded-full" style="background-color: {{ $team->color }}"></span>@endif{{ $team->name }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-muted">{{ __('Noch keine Teams angelegt.') }}</p>
                    @endforelse
                </div>
                <p class="text-xs text-muted">{{ __('Mitglieder der gewählten Teams können Aufgaben dieses Auftrags übernehmen.') }}</p>
            </div>

            <div class="fieldset">
                <span class="fieldset-label">{{ __('Zusätzliche Einzelmitglieder') }}</span>
                @php($selectedMembers = (array) old('member_ids', array_map(fn($id) => \App\Support\Sqid::encode(\App\Models\User::class, $id), $assignedMemberIds)))
                <x-user-checklist
                    name="member_ids"
                    :users="$orgUsers"
                    :selected="$selectedMembers"
                    :placeholder="__('Mitarbeiter suchen…')"
                    :empty-text="__('Keine Benutzer vorhanden.')" />
            </div>
        </x-form-group>

        @if (auth()->user()?->canManageBilling())
            <x-form-group :legend="__('Abrechnung')" icon="payments" tone="warning" x-show="noParent" x-cloak>
                <div class="fieldset">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" class="checkbox checkbox-sm"
                               @checked(old('is_default', $project?->is_default))>
                        <span class="label-text">{{ __('Standardprojekt für diesen Kunden') }}</span>
                    </label>
                    <p class="text-xs text-muted">{{ __('Auto-Bucket für Ad-hoc-/Notfall-Stundenzettel. Pro Kunde gibt es genau ein Standardprojekt.') }}</p>
                </div>
            </x-form-group>
        @endif

        {{-- Abrechenbar-Override (Tri-State) — Partial, s. Kommentar dort
             (Blade-Backtracking-Schwelle). --}}
        @include('projects._billable_field', ['project' => $project])

        {{-- Wetter-Auto-Abruf-Override (Feature 062, Rang 12) — Partial,
             s. Kommentar dort (Blade-Backtracking-Schwelle). --}}
        @include('projects._weather_field', ['project' => $project])
    </div>
</x-modal>
