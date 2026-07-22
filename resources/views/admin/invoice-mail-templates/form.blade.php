@extends('layouts.app')

@section('title', $template->exists ? __('Template bearbeiten') : __('Neues Template'))
@section('nav-title', __('Mail-Template'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="$template->exists ? __('Template bearbeiten') : __('Neues Template')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm"
                            :href="route('admin.invoice-mail-templates.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <form method="POST"
          action="{{ $template->exists ? route('admin.invoice-mail-templates.update', $template) : route('admin.invoice-mail-templates.store') }}"
          class="space-y-4">
        @csrf
        @if ($template->exists) @method('PUT') @endif

        <x-input-field name="name" :label="__('Name')" required maxlength="120"
                       :value="old('name', $template->name)" />

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default))
                   class="checkbox">
            <span class="label-text">{{ __('Als Standard-Template verwenden') }}</span>
        </label>

        <x-input-field name="subject" :label="__('Betreff')" required maxlength="255"
                       :value="old('subject', $template->subject)" />

        <x-textarea-field name="body_html" :label="__('HTML-Body')" required rows="10"
                          class="font-mono text-xs"
                          :value="old('body_html', $template->body_html)" />

        <x-textarea-field name="body_text" :label="__('Text-Body (Fallback)')" required rows="8"
                          class="font-mono text-xs"
                          :value="old('body_text', $template->body_text)" />

        <details class="text-sm">
            <summary class="cursor-pointer text-base-content/70">{{ __('Verfügbare Variablen') }}</summary>
            <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                @foreach ($variables as $key => $label)
                    <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> – {{ $label }}</li>
                @endforeach
            </ul>
        </details>

        <div class="flex justify-end gap-2 pt-2">
            <x-button :href="route('admin.invoice-mail-templates.index')" tone="ghost" size="md">{{ __('Abbrechen') }}</x-button>
            <x-button type="submit" tone="primary" size="md">{{ __('Speichern') }}</x-button>
        </div>
    </form>
</x-page-shell>
@endsection
