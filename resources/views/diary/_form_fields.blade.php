{{-- Inhalt der Diary Form. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds --}}
@php
    $canCreateForOthers = $canCreateForOthers ?? false;
    $assignableUsers = $assignableUsers ?? collect();
    $prefillStartAt = $prefillStartAt ?? null;
    $prefillUserId = $prefillUserId ?? 0;
    $defaultUserId = old('user_id', $entry?->user_id ?? ($prefillUserId ?: auth()->id()));
@endphp

@if (! $isEdit && $canCreateForOthers && $assignableUsers->isNotEmpty())
    <div>
        <label for="user_id" class="mb-2 block text-sm font-medium text-base-content">{{ __('Benutzer') }}<span class="text-error">*</span></label>
        <select
            id="user_id"
            name="user_id"
            class="select select-bordered select-sm w-full @error('user_id') ring-2 ring-error/30 @enderror"
        >
            @foreach ($assignableUsers as $u)
                <option value="{{ $u->id }}" @selected((int) $defaultUserId === (int) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        @error('user_id')
            <p class="mt-2 text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
@endif

<div>
    <label for="content" class="mb-2 block text-sm font-medium text-base-content">{{ __('Inhalt') }}<span class="text-error">*</span></label>
    <textarea
        id="content"
        name="content"
        rows="8"
        class="textarea textarea-bordered textarea-sm w-full @error('content') ring-2 ring-error/30 @enderror"
        placeholder="{{ __('Beschreibe den Vorgang...') }}"
    >{{ old('content', $entry?->content) }}</textarea>
    @error('content')
        <p class="mt-2 text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="response" class="mb-2 block text-sm font-medium text-base-content">{{ __('Rückmeldung') }}</label>
    <textarea
        id="response"
        name="response"
        rows="4"
        class="textarea textarea-bordered textarea-sm w-full"
        placeholder="{{ __('Antwort oder Notiz (optional) ...') }}"
    >{{ old('response', $entry?->response) }}</textarea>
</div>

<div>
    <label for="status" class="mb-2 block text-sm font-medium text-base-content">{{ __('Status') }}<span class="text-error">*</span></label>
    <select
        id="status"
        name="status"
        class="select select-bordered select-sm w-full @error('status') ring-2 ring-error/30 @enderror"
    >
        <option value="2" @selected(old('status', $entry?->status ?? 2) == 2)>{{ __('Offen') }}</option>
        <option value="3" @selected(old('status', $entry?->status) == 3)>{{ __('Problem') }}</option>
        <option value="1" @selected(old('status', $entry?->status) == 1)>{{ __('Bestätigt') }}</option>
        <option value="-1" @selected(old('status', $entry?->status) == -1)>{{ __('Erledigt') }}</option>
    </select>
    @error('status')
        <p class="mt-2 text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<x-date-range
    layout="split"
    type="datetime-local"
    fromName="start_at"
    toName="end_at"
    fromId="start_at"
    toId="end_at"
    :from="old('start_at', $entry?->start_at?->format('Y-m-d\TH:i') ?? $prefillStartAt)"
    :to="old('end_at', $entry?->end_at?->format('Y-m-d\TH:i'))"
    labelClass="mb-2 block text-sm font-medium text-base-content"
    :fromError="$errors->first('start_at')"
    :toError="$errors->first('end_at')"
    gridClass="grid gap-4 sm:grid-cols-2"
/>

<div>
    <label class="mb-2 block text-sm font-medium text-base-content">{{ __('Tags') }}</label>
    @php($currentTagIds = old('tag_ids', $selectedTagIds ?? []))
    @if (($allTags ?? collect())->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-3">
            @foreach ($allTags as $tag)
                <label class="cursor-pointer">
                    <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                        @checked(in_array((string) $tag->id, array_map('strval', (array) $currentTagIds), true))
                        class="peer sr-only">
                    <span class="badge badge-outline peer-checked:badge-primary peer-checked:text-primary-content"
                        @if ($tag->color) style="border-color: {{ $tag->color }};" @endif>
                        {{ $tag->name }}
                    </span>
                </label>
            @endforeach
        </div>
    @endif
    <input type="text" name="new_tags" value="{{ old('new_tags') }}"
        class="input input-bordered input-sm w-full"
        placeholder="{{ __('Neue Tags durch Komma getrennt') }}">
</div>
