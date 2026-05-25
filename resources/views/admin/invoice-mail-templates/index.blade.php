@extends('layouts.app')

@section('title', __('Rechnungs-Mail-Templates'))
@section('nav-title', __('Mail-Templates'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-page-toolbar :title="__('Rechnungs-Mail-Templates')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        :href="route('admin.invoice-mail-templates.create')"
                        show-label>{{ __('Neues Template') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Betreff') }}</th>
                <th>{{ __('Scope') }}</th>
                <th>{{ __('Standard') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $tpl)
            <tr>
                <td>{{ $tpl->name }}</td>
                <td class="text-sm">{{ $tpl->subject }}</td>
                <td>{{ $tpl->organization_id === null ? __('Global') : __('Organisation') }}</td>
                <td>
                    @if ($tpl->is_default)
                        <span class="badge badge-success">{{ __('Ja') }}</span>
                    @endif
                </td>
                <td class="text-right">
                    <x-icon-btn icon="edit" size="xs"
                                :href="route('admin.invoice-mail-templates.edit', $tpl)"/>
                    <form method="POST" action="{{ route('admin.invoice-mail-templates.destroy', $tpl) }}" class="inline"
                          data-confirm-dialog
                          data-confirm-message="{{ __('Template wirklich löschen?') }}"
                          data-confirm-tone="error"
                          data-confirm-label="{{ __('Löschen') }}">
                        @csrf @method('DELETE')
                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"/>
                    </form>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">mail</span>'
                           :colspan="5" :title="__('Noch keine Templates angelegt.')" compact/>
        @endforelse
    </x-table>

    <div class="mt-6 text-sm text-base-content/70">
        <strong>{{ __('Verfügbare Variablen') }}:</strong>
        <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
            @foreach ($variables as $key => $label)
                <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> – {{ $label }}</li>
            @endforeach
        </ul>
    </div>
</x-page-shell>
@endsection
