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
        ->get(['id', 'name', 'customer_id']);
    $initialParentCustomer = $project?->parent?->customer_id;
    // Map Parent-Projekt-ID → Kunden-Sqid (das Kunden-Select nutzt Sqid als Wert,
    // daher muss die Auto-Befüllung ebenfalls den Sqid setzen, nicht die int-ID).
    $parentCustomerSqids = $parentOptions->mapWithKeys(fn($p) => [
        (string) $p->id => $p->customer_id ? \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) : '',
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
    <div x-data="{
              parentId: @js((string) old('parent_id', $project?->parent_id ?? '')),
              parentCustomers: @js($parentCustomerSqids),
              customerId: @js($initialCustomerSqid),
              foreignCustomersByCustomer: @js($foreignCustomersByCustomer),
              foreignCustomerId: @js($initialForeignCustomerSqid),
              get hasParent() { return this.parentId !== '' && this.parentId !== null; },
              get parentCustomerId() { return this.hasParent ? (this.parentCustomers[this.parentId] ?? '') : ''; },
              get effectiveCustomerId() { return this.hasParent ? this.parentCustomerId : this.customerId; },
              get availableForeignCustomers() { return this.foreignCustomersByCustomer[this.effectiveCustomerId] ?? []; },
          }"
         x-effect="if (hasParent && parentCustomerId) { customerId = String(parentCustomerId); }
                   if (!availableForeignCustomers.some(fc => fc.sqid === foreignCustomerId)) { foreignCustomerId = ''; }"
         class="space-y-4">
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <x-form-group :legend="__('Stammdaten')" icon="folder" tone="primary">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Name') }}</label>
                <input name="name" type="text" required maxlength="120"
                       class="input input-bordered w-full"
                       value="{{ old('name', $project?->name) }}">
                @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Beschreibung') }}</label>
                <textarea name="description" rows="3" maxlength="2000"
                          class="textarea textarea-bordered w-full">{{ old('description', $project?->description) }}</textarea>
                @error('description')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('Zuordnung')" icon="link" tone="info">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Übergeordnetes Projekt') }}</label>
                <select name="parent_id" class="select select-bordered w-full" x-model="parentId">
                    <option value="">{{ __('— Top-Level (kein Parent) —') }}</option>
                    @foreach ($parentOptions as $opt)
                        <option value="{{ $opt->sqid }}" @selected((string) old('parent_id', \App\Support\Sqid::encode(\App\Models\Project::class, $project?->parent_id)) === $opt->sqid)>
                            {{ $opt->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-base-content/60">{{ __('Sub-Projekte erben Customer und Abrechnung vom Parent.') }}</p>
                @error('parent_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="!hasParent" x-cloak>
                <label class="fieldset-label">{{ __('Kunde') }}</label>
                <select name="customer_id" class="select select-bordered w-full" x-ref="customerSelect" x-model="customerId" :disabled="hasParent">
                    <option value="">{{ __('— Kein Kunde —') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $project?->customer_id)) === $customer->sqid)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="!hasParent && availableForeignCustomers.length > 0" x-cloak>
                <label class="fieldset-label">{{ __('Fremdkunde') }}</label>
                <select name="foreign_customer_id" class="select select-bordered w-full"
                        x-model="foreignCustomerId" :disabled="hasParent">
                    <option value="">{{ __('— Kein Fremdkunde —') }}</option>
                    <template x-for="fc in availableForeignCustomers" :key="fc.sqid">
                        <option :value="fc.sqid" x-text="fc.name" :selected="fc.sqid === foreignCustomerId"></option>
                    </template>
                </select>
                @error('foreign_customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-base-content/60" x-show="hasParent" x-cloak>
                {{ __('Customer wird vom Parent-Projekt übernommen.') }}
            </p>
        </x-form-group>

        <x-form-group :legend="__('Status & Zeitraum')" icon="event" tone="success" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Status') }}</label>
                <select name="status" class="select select-bordered w-full">
                    @foreach (\App\Enums\Project\ProjectStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $project?->status?->value ?? \App\Enums\Project\ProjectStatus::Active->value) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-date-range
                layout="join"
                :from="old('starts_on', $project?->starts_on?->format('Y-m-d'))"
                :to="old('ends_on', $project?->ends_on?->format('Y-m-d'))"
                fromName="starts_on"
                toName="ends_on"
                :fromLabel="__('Start')"
                :toLabel="__('Ende')"
                :label="__('Start – Ende')"
                size=""
                formControl
                class="col-span-2"
                :toError="$errors->first('ends_on')"
                :fromError="$errors->first('starts_on')"
            />
        </x-form-group>

        <x-form-group :legend="__('Teams & Mitglieder')" icon="groups" tone="secondary">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Zuständige Teams') }}</label>
                @php($selectedTeams = (array) old('team_ids', array_map(fn($id) => \App\Support\Sqid::encode(\App\Models\Team::class, $id), $assignedTeamIds)))
                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                    @forelse ($teams as $team)
                        <label class="label cursor-pointer justify-start gap-2 rounded px-2 hover:bg-base-200">
                            <input type="checkbox" name="team_ids[]" value="{{ $team->sqid }}" class="checkbox checkbox-sm"
                                   @checked(in_array($team->sqid, $selectedTeams, true))>
                            <span class="text-sm">@if ($team->color)<span class="mr-1 inline-block h-2 w-2 rounded-full" style="background-color: {{ $team->color }}"></span>@endif{{ $team->name }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-base-content/60">{{ __('Noch keine Teams angelegt.') }}</p>
                    @endforelse
                </div>
                <p class="text-xs text-base-content/60">{{ __('Mitglieder der gewählten Teams können Aufgaben dieses Auftrags übernehmen.') }}</p>
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Zusätzliche Einzelmitglieder') }}</label>
                @php($selectedMembers = (array) old('member_ids', array_map(fn($id) => \App\Support\Sqid::encode(\App\Models\User::class, $id), $assignedMemberIds)))
                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($orgUsers as $u)
                        <label class="label cursor-pointer justify-start gap-2 rounded px-2 hover:bg-base-200">
                            <input type="checkbox" name="member_ids[]" value="{{ $u->sqid }}" class="checkbox checkbox-sm"
                                   @checked(in_array($u->sqid, $selectedMembers, true))>
                            <span class="text-sm">{{ $u->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </x-form-group>

        @if (auth()->user()?->canManageBilling())
            <x-form-group :legend="__('Abrechnung')" icon="payments" tone="warning" x-show="!hasParent" x-cloak>
                <div class="fieldset">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" class="checkbox checkbox-sm"
                               @checked(old('is_default', $project?->is_default))>
                        <span class="label-text">{{ __('Standardprojekt für diesen Kunden') }}</span>
                    </label>
                    <p class="text-xs text-base-content/60">{{ __('Auto-Bucket für Ad-hoc-/Notfall-Stundenzettel. Pro Kunde gibt es genau ein Standardprojekt.') }}</p>
                </div>
            </x-form-group>
        @endif
    </div>
</x-modal>
