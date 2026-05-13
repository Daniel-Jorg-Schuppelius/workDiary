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
    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($project) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Status') }}</label>
                <select name="status" class="select select-bordered w-full">
                    @foreach (\App\Models\Project::STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status', $project?->status ?? 'active') === $status)>
                            {{ ['active' => __('Aktiv'), 'paused' => __('Pausiert'), 'archived' => __('Archiviert')][$status] }}
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
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ $project ? __('Speichern') : __('Anlegen') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('projects.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>
