{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vertretung anlegen (Feature 202, MVP-1647). Variablen: $prefill (represented, deputy_user_id, valid_from, valid_until, kind, absence), $members, $canChooseRepresented, $work (übernommene Bereiche der vorbelegten Person). --}}
@php
    $represented = $prefill['represented'];
    $representedSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $represented->id);
    $selectedRepresented = (string) old('represented_user_id', $representedSqid);
    $deputyDefault = $prefill['deputy_user_id'] !== null ? \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $prefill['deputy_user_id']) : '';
    $selectedDeputy = (string) old('deputy_user_id', $deputyDefault);
    $fromAbsence = $prefill['absence'] !== null;
@endphp

<x-modal
    :title="__('delegation.dialog.title')"
    :eyebrow="__('delegation.title')"
    icon="supervisor_account"
    tone="primary"
    :action="route('delegations.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('delegation.dialog.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('delegations.create') . '?dialog=1' }}">
    @if ($fromAbsence)
        <input type="hidden" name="absence" value="{{ $prefill['absence'] }}">
        <input type="hidden" name="kind" value="{{ $prefill['kind']->value }}">
    @endif

    <div role="note" class="alert alert-info mb-3 text-sm">
        <x-icon name="info" />
        <span>{{ __('delegation.dialog.no_rights_hint') }}</span>
    </div>

    <x-form-group :legend="__('delegation.dialog.people')" icon="group" tone="primary">
        @if ($canChooseRepresented)
            <x-select-field name="represented_user_id" id="wd-represented" :label="__('delegation.column.represented')" required>
                @foreach ($members as $member)
                    @php($memberSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id))
                    <option value="{{ $memberSqid }}" @selected($selectedRepresented === $memberSqid)>{{ $member->name }}</option>
                @endforeach
            </x-select-field>
        @else
            <input type="hidden" name="represented_user_id" value="{{ $representedSqid }}">
            <div class="fieldset">
                <span class="fieldset-label">{{ __('delegation.column.represented') }}</span>
                <p class="font-medium">{{ $represented->name }}</p>
            </div>
        @endif

        <x-select-field name="deputy_user_id" id="wd-deputy" :label="__('delegation.column.deputy')" required
                        :hint="$prefill['deputy_user_id'] !== null ? __('delegation.dialog.deputy_hint') : null">
            <option value="">{{ __('delegation.dialog.choose_deputy') }}</option>
            @foreach ($members as $member)
                @continue((int) $member->id === (int) $represented->id && ! $canChooseRepresented)
                @php($memberSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id))
                <option value="{{ $memberSqid }}" @selected($selectedDeputy === $memberSqid)>{{ $member->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('delegation.column.period')" icon="date_range" tone="primary">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('delegation.column.period') }} *</span>
            <x-date-range
                type="date"
                :from="old('valid_from', $prefill['valid_from']->format('Y-m-d'))"
                :to="old('valid_until', $prefill['valid_until']?->format('Y-m-d'))"
                fromName="valid_from"
                toName="valid_until"
                :fromLabel="__('delegation.dialog.from')"
                :toLabel="__('delegation.dialog.until')"
                :label="false"
                :fromRequired="true"
                :toRequired="false"
                class="w-full"
            />
            <p class="mt-1 text-xs text-muted">{{ __('delegation.dialog.until_hint') }}</p>
        </div>
        @unless ($fromAbsence)
            <x-select-field name="kind" id="wd-kind" :label="__('delegation.dialog.kind')" required>
                @foreach (\App\Enums\Delegation\WorkDelegationKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected(old('kind', $prefill['kind']->value) === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </x-select-field>
        @else
            <p class="text-sm"><x-icon :name="$prefill['kind']->icon()" class="align-middle" /> {{ $prefill['kind']->label() }}</p>
        @endunless
        <x-textarea-field name="note" id="wd-note" :label="__('delegation.dialog.note')" rows="2" :value="old('note')" />
    </x-form-group>

    <x-form-group :legend="__('delegation.dialog.work_heading', ['name' => $represented->name])" icon="assignment_ind" tone="ghost">
        @if ($work === [])
            <x-empty-state compact icon="task_alt" :title="__('delegation.dialog.work_none')" />
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($work as $area)
                    <li class="flex justify-between gap-4"><span>{{ $area['label'] }}</span><span class="tabular-nums font-medium">{{ $area['count'] }}</span></li>
                @endforeach
            </ul>
        @endif
        <p class="mt-2 text-xs text-muted">{{ __('delegation.dialog.work_hint') }}</p>
    </x-form-group>

    <x-validation-errors />
</x-modal>
