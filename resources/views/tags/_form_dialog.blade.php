{{-- Erwartet: $tag, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $tag ? route('tags.update', $tag) : route('tags.store');
    $dialogUrl = ($tag ? route('tags.edit', $tag) : route('tags.create')) . '?dialog=1';
@endphp

<x-dialog
    :title="$tag ? __('Tag bearbeiten') : __('Neuer Tag')"
    :eyebrow="__('Schlagwort')"
    icon="⚑"
    tone="success">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if ($tag) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Name') }}</span></div>
            <input name="name" type="text" required maxlength="60"
                   class="input input-bordered w-full"
                   value="{{ old('name', $tag?->name) }}">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </label>

        <label class="form-control w-full">
            <div class="label"><span class="label-text">{{ __('Farbe') }}</span></div>
            <input name="color" type="color"
                   value="{{ old('color', $tag?->color ?? '#3b82f6') }}"
                   class="input input-bordered h-10 w-20 p-1">
        </label>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ $tag ? __('Speichern') : __('Anlegen') }}</button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('tags.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>
