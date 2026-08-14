{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Feature 002 (Zielwerte): Dialog zum Anlegen/Bearbeiten eines Zielwerts.
--}}
@php
    /** @var \App\Models\ReportTarget $target */
    $isEdit = $target?->exists ?? false;
    $currentScope = old('scope', $target->scope?->value ?? 'org');
    $currentScopeId = old('scope_id', $target->scope_id);
@endphp
<x-modal
    :title="$isEdit ? __('reporting.target.edit') : __('reporting.target.create')"
    :eyebrow="$isEdit ? $target->metric->label() : null"
    icon="flag"
    tone="primary"
    :action="$isEdit ? route('admin.report-targets.update', $target) : route('admin.report-targets.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('reporting.target.create')"
>
    {{-- Scope-Umschaltung via Alpine.data("reveal") (components.js) — CSP-Build-konform. --}}
    <div class="space-y-4" x-data="reveal(@js($currentScope))">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-filter-field show-label :label="__('reporting.target.metric_label')" for="rt-metric">
                <select id="rt-metric" name="metric" class="select select-bordered select-sm w-full" required>
                    @foreach($metricOptions as $m)
                        <option value="{{ $m->value }}" @selected(old('metric', $target->metric?->value) === $m->value)>{{ $m->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field show-label :label="__('reporting.target.value_label')" for="rt-value">
                <input id="rt-value" name="target_value" type="number" step="0.01" min="0"
                       value="{{ old('target_value', $target->target_value) }}"
                       class="input input-bordered input-sm w-full" required />
            </x-filter-field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-filter-field show-label :label="__('reporting.target.scope_label')" for="rt-scope">
                <select id="rt-scope" name="scope" class="select select-bordered select-sm w-full" x-model="value" required>
                    @foreach($scopeOptions as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field show-label :label="__('reporting.target.scope_ref')" for="rt-scope-id">
                <template x-if="is('customer')">
                    <select name="scope_id" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('reporting.target.none') }}</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->sqid }}" @selected($currentScope === 'customer' && (int) $currentScopeId === (int) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </template>
                <template x-if="is('project')">
                    <select name="scope_id" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('reporting.target.none') }}</option>
                        <x-project-options :projects="$projects"
                            :selected="$currentScope === 'project' ? \App\Support\Sqid::encode(\App\Models\Project::class, $currentScopeId) : ''" />
                    </select>
                </template>
                <template x-if="is('user')">
                    <select name="scope_id" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('reporting.target.none') }}</option>
                        @foreach($users as $u)
                            <option value="{{ $u->sqid }}" @selected($currentScope === 'user' && (int) $currentScopeId === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </template>
                <div x-show="is('org')" class="text-sm text-base-content/50 py-2">{{ __('reporting.target.scope.org') }}</div>
                <p class="text-xs text-base-content/50" x-show="isNot('org')">{{ __('reporting.target.scope_ref_hint') }}</p>
            </x-filter-field>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-filter-field show-label :label="__('reporting.target.period_label')" for="rt-period">
                <select id="rt-period" name="period" class="select select-bordered select-sm w-full">
                    <option value="">{{ __('reporting.target.none') }}</option>
                    @foreach($periodOptions as $p)
                        <option value="{{ $p->value }}" @selected(old('period', $target->period?->value) === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-date-range class="sm:col-span-2" layout="split" form-control
                          from-name="valid_from" to-name="valid_until"
                          from-id="rt-from" to-id="rt-until"
                          :from="old('valid_from', $target->valid_from?->format('Y-m-d'))"
                          :to="old('valid_until', $target->valid_until?->format('Y-m-d'))"
                          :from-label="__('reporting.target.valid_from')"
                          :to-label="__('reporting.target.valid_until')" />
        </div>

        <x-filter-field show-label :label="__('reporting.target.note_label')" for="rt-note">
            <input id="rt-note" name="note" type="text" maxlength="255"
                   value="{{ old('note', $target->note) }}"
                   class="input input-bordered input-sm w-full" />
        </x-filter-field>
    </div>
</x-modal>
