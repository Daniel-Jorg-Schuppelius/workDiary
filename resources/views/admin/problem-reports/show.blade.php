{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', $report->reference_no)
@section('nav-title', $report->reference_no)

@section('content')
<x-index-page :subtitle="$report->summary">
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.problem-reports.status', $report) }}" class="flex items-center gap-2">
            @csrf
            @method('PUT')
            <select name="status" class="select select-bordered select-sm">
                @foreach (\App\Enums\Support\ProblemReportStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($report->status === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <x-button type="submit" tone="primary" size="sm">{{ __('problemreport.action.set_status') }}</x-button>
        </form>
        <x-button :href="route('admin.problem-reports.download', $report)" tone="ghost" size="sm" icon="download">
            {{ __('problemreport.action.download') }}
        </x-button>
        @if ($canConvert)
            <form method="POST" action="{{ route('admin.problem-reports.convert', $report) }}">
                @csrf
                <x-button type="submit" tone="warning" size="sm" icon="confirmation_number">
                    {{ __('problemreport.action.convert') }}
                </x-button>
            </form>
        @elseif ($report->external_ref !== null)
            <x-status-badge tone="info">{{ __('problemreport.field.ticket') }}: {{ $report->external_ref }}</x-status-badge>
        @endif
    </x-slot:actions>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('problemreport.section.what')">
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-muted">{{ __('problemreport.field.reporter') }}</dt>
                    <dd>{{ $report->reporter?->name ?? '—' }}
                        @if ($report->contact_ok)<x-status-badge size="xs" tone="success">{{ __('problemreport.field.contact_ok_short') }}</x-status-badge>@endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-muted">{{ __('problemreport.field.description') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $report->description }}</dd>
                </div>
                @if ($report->expected_behavior)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('problemreport.field.expected') }}</dt>
                        <dd class="whitespace-pre-wrap">{{ $report->expected_behavior }}</dd>
                    </div>
                @endif
                @if ($report->actual_behavior)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('problemreport.field.actual') }}</dt>
                        <dd class="whitespace-pre-wrap">{{ $report->actual_behavior }}</dd>
                    </div>
                @endif
                @if ($report->attachments->isNotEmpty())
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">{{ __('problemreport.field.screenshots') }}</dt>
                        <dd class="flex flex-wrap gap-2">
                            @foreach ($report->attachments as $attachment)
                                <span class="badge badge-ghost">{{ $attachment->original_name }} ({{ $attachment->humanSize() }})</span>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card :title="__('problemreport.section.context')">
            <pre class="max-h-64 overflow-auto text-xs leading-tight">{{ json_encode($report->page_context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @if ($report->diagnostic_excerpt !== null)
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-medium">{{ __('problemreport.field.diagnostics') }}</summary>
                    <pre class="mt-2 max-h-72 overflow-auto text-xs leading-tight">{{ json_encode($report->diagnostic_excerpt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @else
                <p class="mt-3 text-xs text-muted">{{ __('problemreport.hint.no_diagnostics') }}</p>
            @endif
            @if ($report->delivery_error)
                <div role="alert" class="alert alert-error alert-soft mt-3 text-sm">
                    <x-icon name="error" />
                    {{ __('problemreport.field.delivery_error') }}: {{ $report->delivery_error }}
                </div>
            @endif
        </x-card>
    </div>
</x-index-page>
@endsection
