{{-- Erwartet: $project, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $project ? route('projects.update', $project) : route('projects.store');
    $dialogUrl = ($project ? route('projects.edit', $project) : route('projects.create')) . '?dialog=1';
@endphp

<x-dialog
    :title="$project ? __('Projekt bearbeiten') : __('Neues Projekt')"
    :eyebrow="__('Projekt')"
    icon="▣"
    :badge="$project?->statusLabel()"
    :badge-tone="$project?->statusTone() ?? 'ghost'"
    tone="primary">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if ($project) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Name') }}</span></div>
            <input name="name" type="text" required maxlength="120"
                   class="input input-bordered w-full"
                   value="{{ old('name', $project?->name) }}">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </label>

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Beschreibung') }}</span></div>
            <textarea name="description" rows="3" maxlength="2000"
                      class="textarea textarea-bordered w-full">{{ old('description', $project?->description) }}</textarea>
            @error('description')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="form-control">
                <div class="label"><span class="label-text">{{ __('Status') }}</span></div>
                <select name="status" class="select select-bordered">
                    @foreach (\App\Models\Project::STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status', $project?->status ?? 'active') === $status)>
                            {{ ['active' => __('Aktiv'), 'paused' => __('Pausiert'), 'archived' => __('Archiviert')][$status] }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <div class="label"><span class="label-text">{{ __('Farbe') }}</span></div>
                <input name="color" type="color"
                       value="{{ old('color', $project?->color ?? '#3b82f6') }}"
                       class="input input-bordered h-10 w-20 p-1">
            </label>

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
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ $project ? __('Speichern') : __('Anlegen') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('projects.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>
