{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('scope.focus.admin.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('scope.focus.admin.title'))

@php
    /** @var list<array{key:string,default_label:string,description:string,icon:string,offered:bool,mandatory:bool,label_override:string}> $rows */
    /** @var string $default */
    /** @var string|null $configuredAt */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('scope.focus.admin.subtitle')">
                <x-slot:actions>
                    <x-button href="{{ route('admin.scope.index') }}" tone="ghost" size="sm" icon="tune">{{ __('scope.title.index') }}</x-button>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div role="status" class="alert alert-info rounded-2xl px-5 py-3 text-sm shadow-xs">
            <x-icon name="info" class="text-base" />
            <span>{{ __('scope.focus.admin.hint') }}</span>
        </div>

        <form method="POST" action="{{ route('admin.workspaces.save') }}" class="mt-4">
            @csrf
            <x-card padding="p-0">
                <div class="flex items-center justify-between gap-3 border-b border-base-300 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.focus.admin.list_heading') }}</h2>
                    @if ($configuredAt)
                        <span class="text-xs opacity-60">{{ __('scope.focus.admin.configured_at', ['date' => \Carbon\CarbonImmutable::parse($configuredAt)->format('d.m.Y H:i')]) }}</span>
                    @endif
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($rows as $row)
                        <li class="flex flex-col gap-3 p-3 sm:flex-row sm:items-start">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-field bg-primary/10 text-primary">
                                <x-icon :name="$row['icon']" class="text-[1.35rem]" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{ $row['default_label'] }}</span>
                                    @if ($row['mandatory'])
                                        <x-status-badge>{{ __('scope.focus.admin.mandatory') }}</x-status-badge>
                                    @endif
                                    @if ($row['key'] === $default)
                                        <x-status-badge tone="primary">{{ __('scope.focus.admin.is_default') }}</x-status-badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-base-content/70">{{ $row['description'] }}</p>
                                <label class="mt-2 flex items-center gap-2">
                                    <span class="text-xs opacity-60">{{ __('scope.focus.admin.rename') }}</span>
                                    <input type="text"
                                           name="labels[{{ $row['key'] }}]"
                                           value="{{ $row['label_override'] }}"
                                           maxlength="60"
                                           placeholder="{{ $row['default_label'] }}"
                                           class="input input-bordered input-sm w-full max-w-xs">
                                </label>
                            </div>
                            <div class="flex shrink-0 items-center gap-5 sm:flex-col sm:items-end sm:gap-2">
                                <label class="flex cursor-pointer items-center gap-2 {{ $row['mandatory'] ? 'opacity-60' : '' }}">
                                    <span class="text-xs">{{ __('scope.focus.admin.offered') }}</span>
                                    <input type="checkbox"
                                           name="available[]"
                                           value="{{ $row['key'] }}"
                                           class="toggle toggle-primary toggle-sm"
                                           @checked($row['offered'])
                                           @disabled($row['mandatory'])>
                                    @if ($row['mandatory'])
                                        {{-- 'all' bleibt immer angeboten: mitsenden, da disabled sonst nicht postet. --}}
                                        <input type="hidden" name="available[]" value="{{ $row['key'] }}">
                                    @endif
                                </label>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <span class="text-xs">{{ __('scope.focus.admin.set_default') }}</span>
                                    <input type="radio"
                                           name="default"
                                           value="{{ $row['key'] }}"
                                           class="radio radio-primary radio-sm"
                                           @checked($row['key'] === $default)>
                                </label>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{-- Vorschlag je Rolle (Feature 165, E6): nur vorschlagend, die persönliche Wahl gewinnt. --}}
            <x-card class="mt-4">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.focus.admin.roles_heading') }}</h2>
                <p class="mb-3 text-sm text-base-content/70">{{ __('scope.focus.admin.roles_hint') }}</p>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($roleRows as $entry)
                        <x-select-field :name="'roles[' . $entry['role']->value . ']'" :id="'focus-role-' . $entry['role']->value"
                                        :label="$entry['role']->label()" :error="'roles.' . $entry['role']->value">
                            <option value="">{{ __('scope.focus.admin.roles_none') }}</option>
                            @foreach ($rows as $row)
                                @if ($row['offered'])
                                    <option value="{{ $row['key'] }}" @selected(old('roles.' . $entry['role']->value, $entry['key']) === $row['key'])>{{ $row['label_override'] !== '' ? $row['label_override'] : $row['default_label'] }}</option>
                                @endif
                            @endforeach
                        </x-select-field>
                    @endforeach
                </div>
            </x-card>

            <div class="mt-4 flex justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
            </div>
        </form>
    </x-page-shell>
@endsection
