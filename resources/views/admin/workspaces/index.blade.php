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

        <div class="alert alert-info rounded-2xl px-5 py-3 text-sm shadow-xs">
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
                                        <span class="badge badge-ghost badge-sm">{{ __('scope.focus.admin.mandatory') }}</span>
                                    @endif
                                    @if ($row['key'] === $default)
                                        <span class="badge badge-primary badge-sm">{{ __('scope.focus.admin.is_default') }}</span>
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

            <div class="mt-4 flex justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
            </div>
        </form>
    </x-page-shell>
@endsection
