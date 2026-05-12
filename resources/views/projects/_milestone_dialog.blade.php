{{-- Erwartet: $project, $milestone (null = neu), $isDialog --}}
@php
    $isDialog  = $isDialog ?? false;
    $action    = $milestone
        ? route('projects.milestones.update', [$project, $milestone])
        : route('projects.milestones.store', $project);
    $dialogUrl = ($milestone
        ? route('projects.milestones.edit', [$project, $milestone])
        : route('projects.milestones.create', $project)) . '?dialog=1';
@endphp

<x-dialog
    :title="$milestone ? __('Milestone bearbeiten') : __('Neuer Milestone')"
    :eyebrow="__('Milestone')"
    icon="◎"
    :badge="$milestone?->statusLabel()"
    :badge-tone="$milestone?->statusTone() ?? 'ghost'"
    tone="primary">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if ($milestone) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Titel') }}</span></div>
            <input name="title" type="text" required maxlength="200"
                   class="input input-bordered w-full"
                   value="{{ old('title', $milestone?->title) }}">
            @error('title')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </label>

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Beschreibung') }}</span></div>
            <textarea name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description', $milestone?->description) }}</textarea>
        </label>

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Fälligkeitsdatum') }}</span></div>
            <input name="due_date" type="date"
                   class="input input-bordered w-full"
                   value="{{ old('due_date', $milestone?->due_date?->format('Y-m-d')) }}">
        </label>

        <label class="flex cursor-pointer items-center gap-3">
            <input type="hidden" name="is_completed" value="0">
            <input type="checkbox" name="is_completed" value="1" class="checkbox checkbox-sm checkbox-success"
                   {{ old('is_completed', $milestone?->is_completed ? '1' : '0') === '1' ? 'checked' : '' }}>
            <span class="text-sm">{{ __('Milestone abgeschlossen') }}</span>
        </label>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">
                {{ $milestone ? __('Speichern') : __('Anlegen') }}
            </button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>
