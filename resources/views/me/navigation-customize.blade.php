{{--
  Created on   : Wed Jul 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : navigation-customize.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('scope.nav.customize'))
@section('nav-title', __('scope.nav.customize'))

@php
    /** @var list<array<string, mixed>> $sections */
    /** @var list<array<string, mixed>> $createGroups */
    /** @var list<string> $hidden */
    // Schalter EIN = sichtbar. Ein Eintrag ist eingeschaltet, wenn er NICHT
    // ausgeblendet ist — beim ersten Öffnen ist damit alles an.
    $isVisible = fn (string $key): bool => ! in_array($key, $hidden, true);
    $sectionPrefix = \App\Services\Navigation\NavigationRegistry::KEY_SECTION;
    $groupPrefix = \App\Services\Navigation\NavigationRegistry::KEY_GROUP;
    $itemPrefix = \App\Services\Navigation\NavigationRegistry::KEY_ITEM;
    $createPrefix = \App\Services\Navigation\NavigationRegistry::KEY_CREATE;
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('scope.customize.subtitle')">
                <x-slot:actions>
                    <x-button href="{{ route('me.functions') }}" tone="ghost" size="sm" icon="apps">{{ __('scope.nav.functions') }}</x-button>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="alert alert-info rounded-2xl px-5 py-3 text-sm shadow-xs">
            <x-icon name="info" class="text-base" />
            <span>{{ __('scope.customize.cosmetic_hint') }}</span>
        </div>

        <form method="POST" action="{{ route('me.navigation.customize.save') }}" id="nav-customize-form" class="mt-4">
            @csrf

            {{-- Schnellerstellung zuerst — spiegelt die Sidebar, wo „Neu …" oben steht. --}}
            <x-card padding="p-0">
                <div class="border-b border-base-300 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.customize.create_heading') }}</h2>
                    <p class="mt-1 text-xs text-muted">{{ __('scope.customize.create_hint') }}</p>
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($createGroups as $group)
                        @php $createKey = $createPrefix . $group['key']; @endphp
                        <li class="p-3">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="visible[]" value="{{ $createKey }}"
                                       class="toggle toggle-sm toggle-primary" @checked($isVisible($createKey))>
                                <span class="font-semibold">{{ $group['label'] }}</span>
                                <span class="text-xs opacity-60">({{ collect($group['items'])->pluck('label')->implode(', ') }})</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card padding="p-0" class="mt-4">
                <div class="border-b border-base-300 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.customize.sidebar_heading') }}</h2>
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($sections as $section)
                        @php $sectionKey = $sectionPrefix . $section['key']; @endphp
                        <li class="p-3">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="visible[]" value="{{ $sectionKey }}"
                                       class="toggle toggle-sm toggle-primary" @checked($isVisible($sectionKey))>
                                <span class="font-semibold">{{ $section['label'] }}</span>
                            </label>
                            <ul class="mt-2 space-y-1 pl-8">
                                @foreach (($section['items'] ?? []) as $item)
                                    @php $itemKey = $itemPrefix . $item['route']; @endphp
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-3">
                                            <input type="checkbox" name="visible[]" value="{{ $itemKey }}"
                                                   class="toggle toggle-xs toggle-primary" @checked($isVisible($itemKey))>
                                            <x-icon :name="$item['icon'] ?? 'circle'" class="text-[1rem] opacity-70" />
                                            <span class="text-sm">{{ $item['label'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                                @foreach (($section['groups'] ?? []) as $group)
                                    @php $groupKey = $groupPrefix . $group['key']; @endphp
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-3">
                                            <input type="checkbox" name="visible[]" value="{{ $groupKey }}"
                                                   class="toggle toggle-xs toggle-primary" @checked($isVisible($groupKey))>
                                            <x-icon :name="$group['icon'] ?? 'label'" class="text-[1rem] opacity-70" />
                                            <span class="text-sm font-medium">{{ $group['label'] }}</span>
                                        </label>
                                        <ul class="mt-1 space-y-1 pl-8">
                                            @foreach (($group['items'] ?? []) as $item)
                                                @php $itemKey = $itemPrefix . $item['route']; @endphp
                                                <li>
                                                    <label class="flex cursor-pointer items-center gap-3">
                                                        <input type="checkbox" name="visible[]" value="{{ $itemKey }}"
                                                               class="toggle toggle-xs toggle-primary" @checked($isVisible($itemKey))>
                                                        <x-icon :name="$item['icon'] ?? 'circle'" class="text-[1rem] opacity-70" />
                                                        <span class="text-sm">{{ $item['label'] }}</span>
                                                    </label>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </form>
    </x-page-shell>

    {{-- Speichern-Balken als STEHENDER Footer (immer sichtbar, ohne Scrollen) —
         gleiches Muster wie die Pagination. Der Button liegt außerhalb des
         <form> und ist über das HTML-Attribut form="…" damit verbunden. --}}
    @push('page-footer')
        <div class="shrink-0 mt-(--sidebar-gap) max-md:px-1">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-(--panel-radius) border border-base-300 bg-base-100 px-4 py-2.5 shadow-xs">
                <span class="text-xs text-muted">{{ __('scope.customize.checkbox_hint') }}</span>
                <x-button type="submit" form="nav-customize-form" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
            </div>
        </div>
    @endpush

    {{-- Hierarchie-Kaskade der Schalter: Mutter → alle Kinder folgen; ein
         eingeschaltetes Kind hebt seine Vorfahren mit an (ein sichtbares Kind
         unter ausgeschalteter Mutter wäre in der Sidebar ohnehin verborgen). --}}
    @push('scripts')
    <script @cspNonce>
        (function () {
            var form = document.querySelector('form[action*="navigation/customize"]');
            if (!form) return;

            // Der Schalter, der direkt zu diesem <li> gehört (nicht die in
            // verschachtelten Unterlisten).
            function ownToggle(li) {
                return li.querySelector(':scope > label input[type="checkbox"]');
            }

            form.addEventListener('change', function (event) {
                var input = event.target;
                if (input.type !== 'checkbox' || input.name !== 'visible[]') return;

                var li = input.closest('li');
                if (!li) return;

                // Mutter → Kinder: alle Nachkommen-Schalter auf denselben Zustand.
                li.querySelectorAll(':scope > ul input[type="checkbox"]').forEach(function (child) {
                    child.checked = input.checked;
                });

                // Kind → Mutter: Einschalten aktiviert die Vorfahren mit.
                if (input.checked) {
                    var parentLi = li.parentElement ? li.parentElement.closest('li') : null;
                    while (parentLi) {
                        var toggle = ownToggle(parentLi);
                        if (toggle) toggle.checked = true;
                        parentLi = parentLi.parentElement ? parentLi.parentElement.closest('li') : null;
                    }
                }
            });
        })();
    </script>
    @endpush
@endsection
