@extends('layouts.app')

@section('title', __('scope.nav.customize'))
@section('nav-title', __('scope.nav.customize'))

@php
    /** @var list<array<string, mixed>> $sections */
    /** @var list<array<string, mixed>> $createGroups */
    /** @var list<string> $hidden */
    $isHidden = fn (string $key): bool => in_array($key, $hidden, true);
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

        <form method="POST" action="{{ route('me.navigation.customize.save') }}" class="mt-4">
            @csrf

            <x-card padding="p-0">
                <div class="border-b border-base-300 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.customize.sidebar_heading') }}</h2>
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($sections as $section)
                        @php $sectionKey = $sectionPrefix . $section['key']; @endphp
                        <li class="p-3">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="hidden[]" value="{{ $sectionKey }}"
                                       class="checkbox checkbox-sm" @checked($isHidden($sectionKey))>
                                <span class="font-semibold">{{ $section['label'] }}</span>
                                <span class="text-xs opacity-60">{{ __('scope.customize.hide_section') }}</span>
                            </label>
                            <ul class="mt-2 space-y-1 pl-8">
                                @foreach (($section['items'] ?? []) as $item)
                                    @php $itemKey = $itemPrefix . $item['route']; @endphp
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-3">
                                            <input type="checkbox" name="hidden[]" value="{{ $itemKey }}"
                                                   class="checkbox checkbox-xs" @checked($isHidden($itemKey))>
                                            <x-icon :name="$item['icon'] ?? 'circle'" class="text-[1rem] opacity-70" />
                                            <span class="text-sm">{{ $item['label'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                                @foreach (($section['groups'] ?? []) as $group)
                                    @php $groupKey = $groupPrefix . $group['key']; @endphp
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-3">
                                            <input type="checkbox" name="hidden[]" value="{{ $groupKey }}"
                                                   class="checkbox checkbox-xs" @checked($isHidden($groupKey))>
                                            <x-icon :name="$group['icon'] ?? 'label'" class="text-[1rem] opacity-70" />
                                            <span class="text-sm font-medium">{{ $group['label'] }}</span>
                                            <span class="text-xs opacity-60">{{ __('scope.customize.hide_group') }}</span>
                                        </label>
                                        <ul class="mt-1 space-y-1 pl-8">
                                            @foreach (($group['items'] ?? []) as $item)
                                                @php $itemKey = $itemPrefix . $item['route']; @endphp
                                                <li>
                                                    <label class="flex cursor-pointer items-center gap-3">
                                                        <input type="checkbox" name="hidden[]" value="{{ $itemKey }}"
                                                               class="checkbox checkbox-xs" @checked($isHidden($itemKey))>
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

            <x-card padding="p-0" class="mt-4">
                <div class="border-b border-base-300 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.customize.create_heading') }}</h2>
                    <p class="mt-1 text-xs text-base-content/60">{{ __('scope.customize.create_hint') }}</p>
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($createGroups as $group)
                        @php $createKey = $createPrefix . $group['key']; @endphp
                        <li class="p-3">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="hidden[]" value="{{ $createKey }}"
                                       class="checkbox checkbox-sm" @checked($isHidden($createKey))>
                                <span class="font-semibold">{{ $group['label'] }}</span>
                                <span class="text-xs opacity-60">({{ collect($group['items'])->pluck('label')->implode(', ') }})</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <div class="mt-4 flex items-center justify-between gap-3">
                <span class="text-xs text-base-content/60">{{ __('scope.customize.checkbox_hint') }}</span>
                <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
            </div>
        </form>
    </x-page-shell>
@endsection
