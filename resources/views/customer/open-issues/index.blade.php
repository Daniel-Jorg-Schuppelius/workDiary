{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('open-issue.title.index') }}</h1>

    @if ($issues->isEmpty())
        <x-empty-state framed icon="flag"
                       :title="__('Keine offenen Punkte für Sie freigegeben.')" />
    @else
        <ul class="space-y-3">
            @foreach ($issues as $issue)
                <li class="rounded bg-base-100 border border-base-300 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span @class([
                                    'badge badge-sm',
                                    'badge-warning' => $issue->status->value === 'open',
                                    'badge-info' => $issue->status->value === 'inProgress',
                                    'badge-error' => $issue->status->value === 'blocked',
                                    'badge-success' => $issue->status->value === 'done',
                                    'badge-ghost' => in_array($issue->status->value, ['wontDo', 'reopened'], true),
                                ])>{{ $issue->status->label() }}</span>
                                <x-status-badge size="sm" outline>{{ $issue->severity->label() }}</x-status-badge>
                                @if ($issue->category)
                                    <x-status-badge tone="ghost" size="sm">{{ $issue->category }}</x-status-badge>
                                @endif
                                @if ($issue->due_at)
                                    <x-status-badge tone="ghost" size="sm">
                                        {{ __('open-issue.field.due_at') }}: {{ $issue->due_at->fdate() }}
                                    </x-status-badge>
                                @endif
                            </div>
                            <p class="font-semibold text-base-content">{{ $issue->title }}</p>
                            @if ($issue->description)
                                <p class="mt-1 whitespace-pre-wrap text-sm text-base-content/80">{{ $issue->description }}</p>
                            @endif
                            <p class="mt-2 text-xs text-muted">
                                {{ __('open-issue.field.assignee') }}: {{ optional($issue->assignee)->name ?? '—' }}
                                @if ($issue->closed_at)
                                    · {{ __('open-issue.field.closed_at') }}: {{ $issue->closed_at->fdatetime() }}
                                @endif
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
        <x-pagination :paginator="$issues" standing />
    @endif
@endsection
