{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customize.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Dashboard anpassen'))
@section('nav-title', __('Dashboard anpassen'))

@php
    /** @var array<int, array{key:string,label:string,icon:string,sort_order:int,hidden:bool}> $items */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Reihenfolge per Pfeil-Buttons ändern und Widgets ein- oder ausblenden.')">
                <x-slot:actions>
                    <x-button href="{{ route('dashboard') }}" tone="ghost" size="sm" icon="arrow_back">{{ __('Zurück zum Dashboard') }}</x-button>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.customize.save') }}" id="dashboard-customize-form">
            @csrf

            <x-card padding="p-0">
                <ul id="dashboard-widget-list" class="divide-y divide-base-300">
                    @foreach ($items as $idx => $item)
                        <li class="flex items-center gap-3 p-3" data-widget-key="{{ $item['key'] }}">
                            <div class="flex flex-col gap-1">
                                <x-icon-btn type="button" tone="ghost" size="xs" icon="keyboard_arrow_up" :label="__('Nach oben')" class="widget-move-up" />
                                <x-icon-btn type="button" tone="ghost" size="xs" icon="keyboard_arrow_down" :label="__('Nach unten')" class="widget-move-down" />
                            </div>
                            <x-icon name="{{ $item['icon'] }}" class="text-base-content/70" />
                            <span class="flex-1 font-semibold">{{ $item['label'] }}</span>
                            <label class="label cursor-pointer gap-2">
                                <span class="label-text text-xs">{{ __('Sichtbar') }}</span>
                                <input type="checkbox" class="toggle toggle-primary toggle-sm widget-visible-toggle"
                                       @if (! $item['hidden']) checked @endif>
                            </label>
                            <input type="hidden" name="widgets[{{ $idx }}][key]" value="{{ $item['key'] }}" class="widget-key-input">
                            <input type="hidden" name="widgets[{{ $idx }}][hidden]" value="{{ $item['hidden'] ? '1' : '0' }}" class="widget-hidden-input">
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <div class="mt-4 flex justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
            </div>
        </form>
    </x-page-shell>

    @push('scripts')
        <script @cspNonce>
            document.addEventListener('DOMContentLoaded', () => {
                const list = document.getElementById('dashboard-widget-list');
                if (! list) return;

                const reindex = () => {
                    list.querySelectorAll('li').forEach((li, index) => {
                        const keyInput = li.querySelector('.widget-key-input');
                        const hiddenInput = li.querySelector('.widget-hidden-input');
                        if (keyInput) keyInput.name = `widgets[${index}][key]`;
                        if (hiddenInput) hiddenInput.name = `widgets[${index}][hidden]`;
                    });
                };

                list.addEventListener('click', (e) => {
                    const upBtn = e.target.closest('.widget-move-up');
                    const downBtn = e.target.closest('.widget-move-down');
                    if (! upBtn && ! downBtn) return;

                    const li = e.target.closest('li');
                    if (! li) return;

                    if (upBtn && li.previousElementSibling) {
                        li.parentNode.insertBefore(li, li.previousElementSibling);
                        reindex();
                    } else if (downBtn && li.nextElementSibling) {
                        li.parentNode.insertBefore(li.nextElementSibling, li);
                        reindex();
                    }
                });

                list.querySelectorAll('.widget-visible-toggle').forEach((toggle) => {
                    toggle.addEventListener('change', (e) => {
                        const hiddenInput = e.target.closest('li').querySelector('.widget-hidden-input');
                        if (hiddenInput) hiddenInput.value = e.target.checked ? '0' : '1';
                    });
                });
            });
        </script>
    @endpush
@endsection
