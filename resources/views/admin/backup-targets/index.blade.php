{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('backup_targets.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('backup_targets.title'))

@section('content')
<x-index-page :subtitle="__('backup_targets.description')">
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.backup-targets.dropbox.oauth.start') }}" class="leading-none">
            @csrf
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>Dropbox</x-icon-btn>
        </form>
        <form method="POST" action="{{ route('admin.backup-targets.microsoft.oauth.start') }}" class="leading-none">
            @csrf
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>Microsoft</x-icon-btn>
        </form>
        <form method="POST" action="{{ route('admin.backup-targets.google.oauth.start') }}" class="leading-none">
            @csrf
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>Google Drive</x-icon-btn>
        </form>
    </x-slot:actions>

    @if (session('success'))
        <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
    @endif

    @unless ($hasMasterKey)
        <div role="alert" class="alert alert-error">
            <x-icon name="key_off" />
            <span>{{ __('backup_targets.master_key_missing') }}</span>
        </div>
    @endunless

    {{-- Dauerwarnung ohne Recovery-Key (Entscheid „optional mit Warnung"). --}}
    @if ($hasMasterKey && ! $hasRecoveryKey)
        <div role="alert" class="alert alert-warning">
            <x-icon name="warning" />
            <span>{{ __('backup_targets.recovery_key_missing') }}</span>
        </div>
    @endif

    @if ($connections->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">cloud_upload</span>'
                       :title="__('backup_targets.no_connections')" />
    @else
        @foreach ($connections as $connection)
            @php /** @var \App\Models\Backup\BackupTargetConnection $connection */ @endphp
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="card-title text-base">{{ $connection->provider->label() }} — {{ $connection->name }}</h3>
                        <x-status-badge size="xs" :tone="$connection->status->tone()">{{ $connection->status->label() }}</x-status-badge>
                        <span class="text-sm text-base-content/60">{{ $connection->external_account_label ?? __('backup_targets.account') }}</span>
                        <div class="ml-auto flex items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.backup-targets.' . ($connection->provider->value === 'microsoft' ? 'microsoft' : ($connection->provider->value === 'google' ? 'google' : 'dropbox')) . '.oauth.start', ['connection' => $connection->sqid]) }}" class="leading-none">
                                @csrf
                                <x-icon-btn icon="sync" tone="ghost" size="xs" type="submit" show-label>{{ __('backup_targets.reconnect') }}</x-icon-btn>
                            </form>
                            <a href="{{ route('admin.backup-targets.cleanup.preview', $connection) }}" class="btn btn-ghost btn-xs">{{ __('backup_targets.cleanup') }}</a>
                            <x-action-form :action="route('admin.backup-targets.disconnect', $connection)"
                                  method="DELETE"
                                  :confirm="__('backup_targets.disconnect_confirm')"
                                  :confirm-label="__('backup_targets.disconnect')">
                                <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :label="__('backup_targets.disconnect')" />
                            </x-action-form>
                        </div>
                    </div>

                    @if ($connection->last_error)
                        <div role="alert" class="alert alert-warning text-sm">
                            <x-icon name="warning" />
                            <span>{{ $connection->last_error }} <span class="text-base-content/60">({{ $connection->last_error_at?->ftime() }})</span></span>
                        </div>
                    @endif

                    <p class="text-sm text-base-content/70">
                        <span class="font-medium">{{ __('backup_targets.quota') }}:</span>
                        @if ($connection->quota_used !== null && $connection->quota_total !== null)
                            {{ __('backup_targets.quota_value', ['used' => \Illuminate\Support\Number::fileSize($connection->quota_used), 'total' => \Illuminate\Support\Number::fileSize($connection->quota_total)]) }}
                        @else
                            {{ __('backup_targets.quota_unknown') }}
                        @endif
                    </p>

                    <p class="text-xs text-base-content/50">{{ __('backup_targets.pilot_note') }}</p>
                </div>
            </div>
        @endforeach
    @endif

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h3 class="card-title text-base">{{ __('backup_targets.generations.title') }}</h3>

            @if ($generations->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('backup_targets.generations.empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('backup_targets.generations.snapshot') }}</th>
                                <th>{{ __('backup_targets.generations.target') }}</th>
                                <th>{{ __('backup_targets.generations.class') }}</th>
                                <th>{{ __('backup_targets.generations.age') }}</th>
                                <th>{{ __('backup_targets.generations.size') }}</th>
                                <th>{{ __('backup_targets.generations.status') }}</th>
                                <th>{{ __('backup_targets.generations.verified') }}</th>
                                <th>{{ __('backup_targets.generations.restore_tested') }}</th>
                                <th>{{ __('backup_targets.generations.hold') }}</th>
                                <th class="text-right">{{ __('backup_targets.generations.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($generations as $generation)
                                @php /** @var \App\Models\Backup\BackupGeneration $generation */ @endphp
                                <tr>
                                    <td class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($generation->snapshot_uuid, 13, '…') }}</td>
                                    <td>{{ $generation->connection?->name ?? '—' }}</td>
                                    <td>{{ $generation->retention_class->label() }}</td>
                                    <td>{{ $generation->started_at?->ftime() }}</td>
                                    <td>{{ $generation->cipher_size !== null ? \Illuminate\Support\Number::fileSize($generation->cipher_size) : '—' }}</td>
                                    <td><x-status-badge size="xs" :tone="$generation->status->tone()">{{ $generation->status->label() }}</x-status-badge></td>
                                    <td>{{ $generation->last_verified_at?->ftime() ?? '—' }}</td>
                                    <td>
                                        @if ($generation->restore_tested_at !== null)
                                            {{ $generation->restore_tested_at->ftime() }}
                                        @else
                                            <span class="text-base-content/60">{{ __('backup_targets.generations.restore_pending') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.backup-targets.generations.hold', $generation) }}" class="leading-none">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-xs {{ $generation->legal_hold ? 'text-warning' : '' }}">
                                                {{ $generation->legal_hold ? __('backup_targets.generations.hold_release_action') : __('backup_targets.generations.hold_set_action') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-right">
                                        <x-action-form :action="route('admin.backup-targets.generations.destroy', $generation)"
                                              method="DELETE"
                                              :confirm="__('backup_targets.generations.delete_confirm')"
                                              :confirm-label="__('backup_targets.generations.delete_action')">
                                            <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('backup_targets.generations.delete_action')" />
                                        </x-action-form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-index-page>
@endsection
