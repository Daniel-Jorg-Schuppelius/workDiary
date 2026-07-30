@extends('layouts.app')
@section('title', __('recipes.menu.title'))
@section('nav-title', __('recipes.menu.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        <x-validation-errors first />

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('recipes.menu.title') }}</h1>
            <p class="mb-3 text-sm text-base-content/60">{{ __('recipes.menu.intro') }}</p>

            <form method="POST" action="{{ route('recipe-menus.store') }}" class="grid gap-2 md:grid-cols-4 items-end">
                @csrf
                <label class="form-control md:col-span-2">
                    <span class="label-text">{{ __('recipes.menu.field.name') }}</span>
                    <input type="text" name="name" maxlength="160" required value="{{ old('name') }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('recipes.menu.field.event_date') }}</span>
                    <input type="date" name="event_date" value="{{ old('event_date') }}" class="input input-bordered input-sm">
                </label>
                <div class="flex items-end gap-2">
                    <label class="form-control">
                        <span class="label-text">{{ __('recipes.menu.field.guest_count') }}</span>
                        <input type="number" name="guest_count" min="1" max="100000" value="{{ old('guest_count') }}" class="input input-bordered input-sm w-28">
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('recipes.menu.action.create') }}</button>
                </div>
            </form>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            @if ($menus->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('recipes.menu.empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('recipes.menu.field.name') }}</th>
                                <th>{{ __('recipes.menu.field.event_date') }}</th>
                                <th class="text-right">{{ __('recipes.menu.field.guest_count') }}</th>
                                <th class="text-right">{{ __('recipes.menu.field.dishes') }}</th>
                                <th class="text-right">{{ __('recipes.field.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($menus as $menu)
                                <tr>
                                    <td>{{ $menu->name }}</td>
                                    <td>{{ $menu->event_date?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="text-right">{{ $menu->guest_count ?? '—' }}</td>
                                    <td class="text-right">{{ $menu->items_count }}</td>
                                    <td class="text-right">
                                        <div class="flex justify-end">
                                            <a href="{{ route('recipe-menus.show', $menu) }}" class="btn btn-xs btn-ghost">{{ __('recipes.menu.action.open') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
