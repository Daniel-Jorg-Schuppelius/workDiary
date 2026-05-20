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
              parentCustomers: @js($parentOptions->pluck('customer_id', 'id')),
              get hasParent() { return this.parentId !== '' && this.parentId !== null; },
              get parentCustomerId() { return this.hasParent ? (this.parentCustomers[this.parentId] ?? '') : ''; },
          }"
         x-effect="if (hasParent && parentCustomerId) { $refs.customerSelect.value = String(parentCustomerId); }"
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
                        <option value="{{ $opt->id }}" @selected((int) old('parent_id', $project?->parent_id) === (int) $opt->id)>
                            {{ $opt->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-base-content/60">{{ __('Sub-Projekte erben Customer und Abrechnung vom Parent.') }}</p>
                @error('parent_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" x-show="!hasParent" x-cloak>
                <label class="fieldset-label">{{ __('Kunde') }}</label>
                <select name="customer_id" class="select select-bordered w-full" x-ref="customerSelect" :disabled="hasParent">
                    <option value="">{{ __('— Kein Kunde —') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id', $project?->customer_id) === (int) $customer->id)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
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

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Farbe') }}</label>
                <input name="color" type="color"
                       value="{{ old('color', $project?->color ?? '#3b82f6') }}"
                       class="input input-bordered h-10 w-20 p-1">
            </div>

            <x-date-range
                layout="split"
                :from="old('starts_on', $project?->starts_on?->format('Y-m-d'))"
                :to="old('ends_on', $project?->ends_on?->format('Y-m-d'))"
                fromName="starts_on"
                toName="ends_on"
                :fromLabel="__('Start')"
                :toLabel="__('Ende')"
                size=""
                formControl
                gridClass="contents"
                :toError="$errors->first('ends_on')"
                :fromError="$errors->first('starts_on')"
            />
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
