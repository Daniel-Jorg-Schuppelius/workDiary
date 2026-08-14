{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _milestone_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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

<x-modal
    :title="$milestone ? __('Milestone bearbeiten') : __('Neuer Milestone')"
    :eyebrow="__('Milestone')"
    icon="flag"
    :badge="$milestone?->statusLabel()"
    :badge-tone="$milestone?->statusTone() ?? 'ghost'"
    tone="primary"
    :action="$action"
    :method="$milestone ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$milestone ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Milestone')" icon="flag" tone="primary">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Titel') }}</label>
                <input name="title" type="text" required maxlength="200"
                       class="input input-bordered w-full"
                       value="{{ old('title', $milestone?->title) }}">
                @error('title')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Beschreibung') }}</label>
                <textarea name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description', $milestone?->description) }}</textarea>
            </div>
        </x-form-group>

        <x-form-group :legend="__('Status & Termin')" icon="event" tone="info" cols="2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Fälligkeitsdatum') }}</label>
                <input name="due_date" type="date"
                       class="input input-bordered w-full"
                       value="{{ old('due_date', $milestone?->due_date?->format('Y-m-d')) }}">
            </div>

            <div class="fieldset items-end">
                <label class="label cursor-pointer items-center gap-3">
                    <input type="hidden" name="is_completed" value="0">
                    <input type="checkbox" name="is_completed" value="1" class="checkbox checkbox-sm checkbox-success"
                           {{ old('is_completed', $milestone?->is_completed ? '1' : '0') === '1' ? 'checked' : '' }}>
                    <span class="text-sm">{{ __('Milestone abgeschlossen') }}</span>
                </label>
            </div>
        </x-form-group>
</x-modal>
