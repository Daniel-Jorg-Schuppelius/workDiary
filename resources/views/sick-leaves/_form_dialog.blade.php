{{-- Variablen: $sickLeave, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $previousLeaves, $prefillStart, $prefillEnd --}}
@php
    /** @var \App\Models\SickLeave|null $sickLeave */
    $isEdit   = $isEdit   ?? false;
    $isDialog = $isDialog ?? true;
    $action   = $isEdit ? route('sick-leaves.update', $sickLeave) : route('sick-leaves.store');
    $dialogUrl = ($isEdit ? route('sick-leaves.edit', $sickLeave) : route('sick-leaves.create')) . '?dialog=1';

    $selectedUserSqid = (string) old('user_id', \App\Support\Sqid::encode(\App\Models\User::class, $sickLeave?->user_id ?? auth()->id()));
    $kindOptions  = \App\Enums\Sickness\SickLeaveKind::options();
    $currentKind  = old('kind', $sickLeave?->kind?->value ?? \App\Enums\Sickness\SickLeaveKind::Initial->value);
    $auThreshold = (int) config('sickness.attachment_required_from_day', 4);
    $maxKb       = (int) config('sickness.attachments.max_kilobytes', 10240);
    $mimes       = (array) config('sickness.attachments.mimes', ['pdf','jpg','jpeg','png','heic']);
    $acceptHint  = '.' . implode(',.', $mimes);
    $existing    = $sickLeave?->attachments ?? collect();
@endphp

<x-modal
    :title="$isEdit ? __('Krankmeldung bearbeiten') : __('Krankmeldung erfassen')"
    :eyebrow="__('Krankheit')"
    icon="sick"
    tone="warning"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']"
    :submit-label="$isEdit ? __('Speichern') : __('Krankmeldung einreichen')">

    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <div x-data="sickLeaveForm(@js(old('start_date', $sickLeave?->start_date?->format('Y-m-d') ?? $prefillStart)), @js(old('end_date', $sickLeave?->end_date?->format('Y-m-d') ?? $prefillEnd)), @js($currentKind), {{ $auThreshold }}, {{ $existing->isNotEmpty() ? 'true' : 'false' }})">

    @if ($canAssignOthers && $assignableUsers->isNotEmpty())
        <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary">
            <div class="fieldset">
                <label class="fieldset-label" for="sick-user">{{ __('Mitarbeiter') }}</label>
                <select id="sick-user" name="user_id" class="select select-bordered w-full">
                    @foreach ($assignableUsers as $u)
                        <option value="{{ \App\Support\Sqid::encode(\App\Models\User::class, $u['id'] ?? $u->id) }}" @selected($selectedUserSqid === \App\Support\Sqid::encode(\App\Models\User::class, $u['id'] ?? $u->id))>{{ $u['name'] ?? $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </x-form-group>
    @endif

    <x-form-group :legend="__('Krankmeldung')" icon="sick" tone="warning">
        <div class="fieldset">
            <label class="fieldset-label" for="sick-kind">{{ __('Art') }}</label>
            <select id="sick-kind" name="kind" class="select select-bordered w-full" x-model="kind">
                @foreach ($kindOptions as $val => $label)
                    <option value="{{ $val }}" @selected($currentKind === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="fieldset" x-show="isKind('{{ \App\Enums\Sickness\SickLeaveKind::FollowUp->value }}')" x-cloak>
            <label class="fieldset-label" for="sick-follow">{{ __('Vorausgehende Krankmeldung') }} *</label>
            <select id="sick-follow" name="follow_up_for_id" class="select select-bordered w-full">
                <option value="">{{ __('Bitte wählen …') }}</option>
                @foreach ($previousLeaves as $prev)
                    <option value="{{ $prev->sqid }}" @selected((string) old('follow_up_for_id', \App\Support\Sqid::encode(\App\Models\SickLeave::class, $sickLeave?->follow_up_for_id)) === $prev->sqid)>
                        {{ $prev->start_date->fdate() }} – {{ $prev->end_date->fdate() }}@if ($canAssignOthers) · {{ $prev->user?->name }} @endif
                    </option>
                @endforeach
            </select>
            @error('follow_up_for_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Zeitraum') }} *</label>
            <x-date-range
                type="date"
                :from="old('start_date', $sickLeave?->start_date?->format('Y-m-d') ?? $prefillStart)"
                :to="old('end_date',   $sickLeave?->end_date?->format('Y-m-d')   ?? $prefillEnd)"
                fromName="start_date"
                toName="end_date"
                :fromLabel="__('Von')"
                :toLabel="__('Bis')"
                :label="false"
                required
                class="w-full"
                x-model:from="start"
                x-model:to="end"
            />
            <p class="text-xs text-base-content/60 mt-1">
                <span x-text="days"></span> {{ __('Kalendertage') }}
            </p>
            @error('start_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
            @error('end_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('Bescheinigung')" icon="upload_file" tone="info">
        <div class="fieldset">
            <label class="fieldset-label" for="sick-au-number">{{ __('AU-Nummer') }}</label>
            <input id="sick-au-number" type="text" name="au_number" maxlength="100"
                   value="{{ old('au_number', $sickLeave?->au_number) }}"
                   class="input input-bordered w-full">
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="sick-doctor">{{ __('Arzt / Praxis') }}</label>
            <input id="sick-doctor" type="text" name="doctor_name" maxlength="255"
                   value="{{ old('doctor_name', $sickLeave?->doctor_name) }}"
                   class="input input-bordered w-full">
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="sick-au-file">
                {{ __('AU-Bescheinigung') }}
                <span class="text-error" x-show="requiresAu" x-cloak>*</span>
            </label>
            <input id="sick-au-file" type="file" name="au_file" accept="{{ $acceptHint }}"
                   class="file-input file-input-bordered file-input-sm w-full">
            <p class="text-xs text-base-content/60 mt-1">
                {{ __('Erlaubt: :mimes — max. :mb MB', ['mimes' => strtoupper(implode(', ', $mimes)), 'mb' => number_format($maxKb / 1024, 1, ',', '.')]) }}
            </p>
            <p class="text-xs text-warning mt-1" x-show="requiresAu" x-cloak>
                {{ __('Ab dem :n. Tag ist eine AU-Bescheinigung verpflichtend.', ['n' => $auThreshold]) }}
            </p>
            @if ($existing->isNotEmpty())
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach ($existing as $att)
                        <li class="flex items-center gap-2">
                            <x-icon name="description" class="h-3 w-3" />
                            <a class="link link-hover" href="{{ \App\Http\Controllers\SickLeaveController::attachmentDownloadUrl($sickLeave, $att) }}">{{ $att->original_name }}</a>
                            <span class="text-base-content/50">({{ $att->humanSize() }})</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @error('au_file')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('Sonstiges')" icon="description" tone="ghost">
        <div class="fieldset">
            <label class="fieldset-label cursor-pointer justify-start gap-2">
                <input type="checkbox" name="kasse_notified" value="1"
                       @checked(old('kasse_notified', $sickLeave?->kasse_notified_at !== null))
                       class="checkbox checkbox-sm checkbox-primary">
                <span class="text-sm">{{ __('Krankenkasse wurde informiert') }}</span>
            </label>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="sick-note">{{ __('Notiz') }}</label>
            <textarea id="sick-note" name="note" rows="3" class="textarea textarea-bordered w-full">{{ old('note', $sickLeave?->note) }}</textarea>
        </div>
    </x-form-group>

    </div>{{-- /x-data --}}

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
