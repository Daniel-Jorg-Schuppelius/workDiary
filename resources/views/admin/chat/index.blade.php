{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('chat.title'))
@section('nav-title', __('chat.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        <x-page-toolbar :subtitle="__('chat.intro')">
            <x-slot:actions>
                <x-icon-btn icon="rule" tone="ghost" size="sm" :href="route('admin.notification-rules.index')" show-label>{{ __('chat.to_matrix') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>

        {{-- Vorhandene Kanäle --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('chat.channels_heading') }}</h2>
            @if ($webhooks->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('chat.no_channels') }}</p>
            @else
                <x-table>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('chat.field.name') }}</th>
                                <th>{{ __('chat.field.kind') }}</th>
                                <th>{{ __('chat.col.status') }}</th>
                                <th></th>
                            </tr>
                    </x-slot:head>
                            @foreach ($webhooks as $webhook)
                                <tr>
                                    <td>{{ $webhook->name }}</td>
                                    <td class="text-base-content/60">{{ __('chat.kind.' . $webhook->kind) }}</td>
                                    <td>
                                        @if ($webhook->isActive())
                                            <span class="badge badge-success badge-sm">{{ __('chat.status.active') }}</span>
                                        @elseif ($webhook->disabled_at)
                                            <span class="badge badge-error badge-sm">{{ __('chat.status.auto_disabled') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('chat.status.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($webhook->isActive())
                                            <div class="flex justify-end gap-1">
                                                <form method="POST" action="{{ route('admin.chat.test') }}">
                                                    @csrf
                                                    <input type="hidden" name="webhook" value="{{ $webhook->sqid }}">
                                                    <button type="submit" class="btn btn-ghost btn-xs">{{ __('chat.action.test') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.chat.disconnect') }}">
                                                    @csrf
                                                    <input type="hidden" name="webhook" value="{{ $webhook->sqid }}">
                                                    <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('chat.action.disconnect') }}</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- Kanal hinzufügen --}}
        <form method="POST" action="{{ route('admin.chat.connection.store') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            @csrf
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('chat.add_heading') }}</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('chat.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('chat.field.kind') }}</span>
                    <select name="kind" class="select select-bordered select-sm">
                        @foreach ($kinds as $kind)
                            <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ __('chat.kind.' . $kind) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control md:col-span-2">
                    <span class="label-text">{{ __('chat.field.webhook_url') }}</span>
                    <input type="url" name="webhook_url" value="{{ old('webhook_url') }}" placeholder="https://…" class="input input-bordered input-sm" required>
                    <span class="label-text-alt text-base-content/50">{{ __('chat.field.webhook_url_help') }}</span>
                </label>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('chat.action.save') }}</button>
            </div>
        </form>
    </div>
</x-page-shell>
@endsection
