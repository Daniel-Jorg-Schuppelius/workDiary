{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('backup.title.status') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('backup.title.status'))

@php
    /** @var array<string, mixed> $status */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $restoreTests */
    $sources = $status['sources'] ?? [];
    $restore = $status['restore'] ?? ['overdue' => true, 'overdue_days' => 180, 'last_passed_on' => null, 'days_since' => null];
    $freshnessHours = $status['freshness_hours'] ?? 26;
    $hasAnyHeartbeat = $status['has_any_heartbeat'] ?? false;
    $anyOverdue = collect($sources)->contains(static fn (array $s): bool => (bool) ($s['overdue'] ?? false));
    $fmtBytes = static fn (int $bytes): string => \Illuminate\Support\Number::fileSize($bytes, precision: 1);
@endphp

@section('content')
<x-index-page :subtitle="__('backup.subtitle')">
    @can(\App\Enums\User\Permission::BackupRestoreTestLog->value)
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.backup.restore-tests.create')"
                        show-label>{{ __('backup.action.log_restore_test') }}</x-icon-btn>
        </x-slot:actions>
    @endcan

    {{-- Warn-Banner: überfälliger Heartbeat ODER überfälliger Restore-Test --}}
    @if (! $hasAnyHeartbeat)
        <div role="alert" class="alert alert-error alert-soft">
            <x-icon name="error" />
            <div>
                <h3 class="font-semibold">{{ __('backup.warn.no_heartbeat_title') }}</h3>
                <div class="text-sm">{{ __('backup.warn.no_heartbeat_body') }}</div>
            </div>
        </div>
    @elseif ($anyOverdue)
        <div role="alert" class="alert alert-error alert-soft">
            <x-icon name="warning" />
            <div>
                <h3 class="font-semibold">{{ __('backup.warn.overdue_title') }}</h3>
                <div class="text-sm">{{ __('backup.warn.overdue_body', ['hours' => $freshnessHours]) }}</div>
            </div>
        </div>
    @endif

    @if ($restore['overdue'])
        <div role="alert" class="alert alert-warning alert-soft">
            <x-icon name="restore" />
            <div>
                <h3 class="font-semibold">{{ __('backup.warn.restore_overdue_title') }}</h3>
                <div class="text-sm">
                    {{ __('backup.warn.restore_overdue_body', ['days' => $restore['overdue_days']]) }}
                </div>
            </div>
        </div>
    @endif

    {{-- Letzte Sicherung je Quelle --}}
    <x-card :title="__('backup.section.last_per_source')" icon="backup">
        @if (count($sources) > 0)
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('backup.field.source') }}</th>
                            <th>{{ __('backup.field.occurred_at') }}</th>
                            <th class="text-right">{{ __('backup.field.age') }}</th>
                            <th class="text-right">{{ __('backup.field.size') }}</th>
                            <th>{{ __('backup.field.manifest_hash') }}</th>
                            <th>{{ __('backup.field.state') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($sources as $src)
                            <tr>
                                <td class="font-mono text-xs">{{ $src['source'] ?? '—' }}</td>
                                <td class="text-sm">{{ $src['occurred_at']?->translatedFormat('d.m.Y H:i') ?? '—' }}</td>
                                <td class="text-right font-mono text-xs">
                                    {{ $src['age_hours'] !== null ? __('backup.value.hours', ['n' => (int) $src['age_hours']]) : '—' }}
                                </td>
                                <td class="text-right font-mono text-xs">
                                    {{ $src['size_bytes'] !== null ? $fmtBytes((int) $src['size_bytes']) : '—' }}
                                </td>
                                <td class="font-mono text-xs text-muted">
                                    {{ $src['manifest_hash'] !== null ? \Illuminate\Support\Str::substr((string) $src['manifest_hash'], 0, 12) . '…' : '—' }}
                                </td>
                                <td>
                                    @if ($src['overdue'])
                                        <span class="badge badge-error badge-sm">{{ __('backup.badge.overdue') }}</span>
                                    @else
                                        <span class="badge badge-success badge-sm">{{ __('backup.badge.fresh') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
            </x-table>
        @else
            <x-empty-state
                icon="cloud_off"
                :title="__('backup.empty.no_heartbeat')"
                :description="__('backup.empty.no_heartbeat_hint')" />
        @endif
        <p class="mt-2 text-xs text-muted">{{ __('backup.hint.freshness', ['hours' => $freshnessHours]) }}</p>
    </x-card>

    {{-- Restore-Test-Register --}}
    <x-card :title="__('backup.section.restore_register')" icon="restore">
        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
            @if ($restore['last_passed_on'] !== null)
                <span class="badge {{ $restore['overdue'] ? 'badge-warning' : 'badge-success' }} badge-sm">
                    {{ __('backup.field.last_passed') }}: {{ $restore['last_passed_on']->translatedFormat('d.m.Y') }}
                    @if ($restore['days_since'] !== null)
                        ({{ __('backup.value.days_ago', ['n' => $restore['days_since']]) }})
                    @endif
                </span>
            @else
                <span class="badge badge-warning badge-sm">{{ __('backup.field.no_passed_test') }}</span>
            @endif
        </div>

        <div role="note" class="alert alert-info alert-soft mb-3 text-sm">
            <x-icon name="info" />
            <span>{{ __('backup.hint.register_manual') }}</span>
        </div>

        @if ($restoreTests->total() > 0)
            <x-table>
                <x-slot:head>
                        <tr>
                            <th>{{ __('backup.field.tested_on') }}</th>
                            <th>{{ __('backup.field.source') }}</th>
                            <th>{{ __('backup.field.result') }}</th>
                            <th>{{ __('backup.field.scope') }}</th>
                            <th class="text-right">{{ __('backup.field.restored_size') }}</th>
                            <th class="text-right">{{ __('backup.field.duration') }}</th>
                            <th>{{ __('backup.field.next_due') }}</th>
                            <th>{{ __('backup.field.performed_by') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($restoreTests as $test)
                            <tr>
                                <td class="text-sm">{{ $test->tested_on?->translatedFormat('d.m.Y') ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $test->source }}</td>
                                <td>
                                    <span class="badge badge-{{ $test->result->tone() }} badge-sm">{{ $test->result->label() }}</span>
                                </td>
                                <td class="text-sm text-base-content/70">{{ $test->scope ?? '—' }}</td>
                                <td class="text-right font-mono text-xs">
                                    {{ $test->restored_size_bytes?->format() ?? '—' }}
                                </td>
                                <td class="text-right font-mono text-xs">
                                    {{ $test->duration_minutes !== null ? __('backup.value.minutes', ['n' => $test->duration_minutes]) : '—' }}
                                </td>
                                <td class="text-sm">{{ $test->next_due_on?->translatedFormat('d.m.Y') ?? '—' }}</td>
                                <td class="text-sm text-base-content/70">{{ $test->performedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
            </x-table>
            <div class="mt-3">
                <x-pagination :paginator="$restoreTests" standing />
            </div>
        @else
            <x-empty-state
                icon="restore"
                :title="__('backup.empty.no_restore_tests')" />
        @endif
    </x-card>

    {{-- Retention-Hinweis (Verweis auf docs/backup-restore.md, kein Duplikat) --}}
    <x-card :title="__('backup.section.retention')" icon="schedule">
        <p class="text-sm text-base-content/70">{{ __('backup.hint.retention') }}</p>
        <p class="mt-1 text-xs text-muted">{{ __('backup.hint.see_docs') }}</p>
        <p class="mt-2">
            {{-- Öffnet den Hilfe-Drawer; route('help.topics.show') ist ein JSON-Endpunkt, kein Link-Ziel. --}}
            <button type="button" class="link link-primary text-sm"
                    data-help-trigger data-help-topic="admin.backups">
                <x-icon name="help" class="align-text-bottom" /> {{ __('backup.action.open_help') }}
            </button>
        </p>
    </x-card>

    <p class="text-xs text-muted">
        {{ __('backup.generated_at', ['at' => ($status['generated_at'] ?? now())->translatedFormat('d.m.Y H:i:s')]) }}
    </p>
</x-index-page>
@endsection
