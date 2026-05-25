@extends('layouts.app')

@section('title', $template->exists ? __('Template bearbeiten') : __('Neues Template'))
@section('nav-title', __('Mail-Template'))

@section('content')
<x-page-shell>
    <x-page-toolbar :title="$template->exists ? __('Template bearbeiten') : __('Neues Template')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm"
                        :href="route('admin.invoice-mail-templates.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <form method="POST"
          action="{{ $template->exists ? route('admin.invoice-mail-templates.update', $template) : route('admin.invoice-mail-templates.store') }}"
          class="space-y-4">
        @csrf
        @if ($template->exists) @method('PUT') @endif

        <label class="form-control w-full">
            <span class="label label-text">{{ __('Name') }} <span class="text-error">*</span></span>
            <input type="text" name="name" required maxlength="120"
                   value="{{ old('name', $template->name) }}"
                   class="input input-bordered w-full">
        </label>

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default))
                   class="checkbox">
            <span class="label-text">{{ __('Als Standard-Template verwenden') }}</span>
        </label>

        <label class="form-control w-full">
            <span class="label label-text">{{ __('Betreff') }} <span class="text-error">*</span></span>
            <input type="text" name="subject" required maxlength="255"
                   value="{{ old('subject', $template->subject) }}"
                   class="input input-bordered w-full">
        </label>

        <label class="form-control w-full">
            <span class="label label-text">{{ __('HTML-Body') }} <span class="text-error">*</span></span>
            <textarea name="body_html" rows="10" required
                      class="textarea textarea-bordered w-full font-mono text-xs">{{ old('body_html', $template->body_html) }}</textarea>
        </label>

        <label class="form-control w-full">
            <span class="label label-text">{{ __('Text-Body (Fallback)') }} <span class="text-error">*</span></span>
            <textarea name="body_text" rows="8" required
                      class="textarea textarea-bordered w-full font-mono text-xs">{{ old('body_text', $template->body_text) }}</textarea>
        </label>

        <details class="text-sm">
            <summary class="cursor-pointer text-base-content/70">{{ __('Verfügbare Variablen') }}</summary>
            <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                @foreach ($variables as $key => $label)
                    <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> – {{ $label }}</li>
                @endforeach
            </ul>
        </details>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('admin.invoice-mail-templates.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
        </div>
    </form>
</x-page-shell>
@endsection
