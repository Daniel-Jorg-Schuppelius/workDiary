@extends('layouts.app')
@section('title', __('Stundenzettel') . ' #' . $timesheet->id)
@section('nav-title', __('Stundenzettel') . ' #' . $timesheet->id)

@section('content')
@php
    $editable = $timesheet->canEdit();
    $canSign = auth()->user()?->can('sign', $timesheet) ?? false;
    $fmtMin = fn(int $min) => intdiv($min, 60) . ':' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT);
    $entryKindLabel = static function (?\App\Enums\TimeEntry\TimeEntryKind $kind): string {
        return $kind?->label() ?? '';
    };
@endphp

<x-page-shell>

    <x-page-toolbar :title="__('Stundenzettel') . ' – ' . optional($timesheet->work_date)->fdate()"
                    :badge="$timesheet->statusLabel()"
                    :badge-tone="$timesheet->statusTone()">
        <div class="text-sm text-base-content/70">
            <a href="{{ route('projects.show', $project) }}#timesheets" class="link">{{ $project->name }}</a>
            · {{ $timesheet->user?->name }}
        </div>
        <x-slot:actions>
            <x-icon-btn icon="picture_as_pdf" size="sm"
                        :href="route('projects.timesheets.pdf', [$project, $timesheet])"
                        target="_blank"
                        show-label>{{ __('PDF') }}</x-icon-btn>
            @if($editable)
                <x-icon-btn icon="edit" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.timesheets.edit', [$project, $timesheet])"
                            show-label>{{ __('Kopfdaten') }}</x-icon-btn>
                <x-action-form :action="route('projects.timesheets.magic-link', [$project, $timesheet])">
                    <x-icon-btn icon="link" tone="secondary" size="sm" type="submit" show-label>{{ __('Sign-Link an Kunden') }}</x-icon-btn>
                </x-action-form>
                <x-action-form :action="route('projects.timesheets.submit', [$project, $timesheet])">
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Einreichen') }}</x-icon-btn>
                </x-action-form>
            @endif
            @can('lock', $timesheet)
                @if(! $timesheet->isLocked())
                    <x-action-form :action="route('projects.timesheets.lock', [$project, $timesheet])">
                        <x-icon-btn icon="lock" tone="warning" size="sm" type="submit" show-label>{{ __('Sperren') }}</x-icon-btn>
                    </x-action-form>
                @else
                    <x-action-form :action="route('projects.timesheets.unlock', [$project, $timesheet])">
                        <x-icon-btn icon="lock_open" size="sm" type="submit" show-label>{{ __('Entsperren') }}</x-icon-btn>
                    </x-action-form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-kpi-tile :label="__('Arbeit')"         :value="$fmtMin((int)$timesheet->total_work_minutes) . ' h'" />
        <x-kpi-tile :label="__('Pause')"          :value="$fmtMin((int)$timesheet->total_break_minutes) . ' h'" />
        <x-kpi-tile :label="__('Material netto')" :value="number_format((float)$timesheet->total_material_net, 2, ',', '.') . ' €'" />
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Zeiteinträge') }}</span>
            @if($editable)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.timesheets.entries.create', [$project, $timesheet])"
                            show-label>{{ __('Zeile hinzufügen') }}</x-icon-btn>
            @endif
        </header>

        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort>{{ __('Start') }}</x-table.th>
                    <x-table.th sort>{{ __('Ende') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Pause') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Dauer') }}</x-table.th>
                    <x-table.th sort>{{ __('Art') }}</x-table.th>
                    <x-table.th sort>{{ __('Aufgabe') }}</x-table.th>
                    <x-table.th sort>{{ __('Beschreibung') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse($timesheet->entries as $e)
                <tr>
                    <td class="tabular-nums">{{ $e->started_at?->ftime() }}</td>
                    <td class="tabular-nums">{{ $e->ended_at?->ftime() }}</td>
                    <td class="text-right tabular-nums">{{ (int)$e->break_minutes }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ (int) $e->minutes }}">{{ $fmtMin((int)$e->minutes) }}</td>
                    <td>{{ $entryKindLabel($e->kind) }}</td>
                    <td>{{ $e->task?->title }}</td>
                    <td>{{ $e->description }}</td>
                    <td class="text-right">
                        @if($editable)
                            <x-action-form :action="route('projects.timesheets.entries.destroy', [$project, $timesheet, $e])" method="DELETE"
                                  :confirm="__('Löschen?')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :icon="false" :colspan="8" compact />
            @endforelse
        </x-table>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Verbrauchsmaterial') }}</span>
            @if($editable)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.timesheets.materials.create', [$project, $timesheet])"
                            show-label>{{ __('Material erfassen') }}</x-icon-btn>
            @endif
        </header>

        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort>{{ __('Bezeichnung') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                    <x-table.th sort>{{ __('Einheit') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('EP netto') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Summe netto') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse($timesheet->materialUsages as $u)
                <tr>
                    <td>{{ $u->description }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ (float) $u->quantity }}">{{ rtrim(rtrim(number_format((float)$u->quantity, 3, ',', '.'), '0'), ',') }}</td>
                    <td>{{ $u->unit }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ (float) ($u->unit_price ?? 0) }}">{{ $u->unit_price !== null ? number_format((float)$u->unit_price, 4, ',', '.').' €' : '—' }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ (float) $u->line_total_net }}">{{ number_format((float)$u->line_total_net, 2, ',', '.') }} €</td>
                    <td class="text-right">
                        @if($editable)
                            <x-action-form :action="route('projects.timesheets.materials.destroy', [$project, $timesheet, $u])" method="DELETE"
                                  :confirm="__('Löschen?')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :icon="false" :colspan="6" compact />
            @endforelse
        </x-table>
    </div>

    {{-- Signatur — kompakte Karte unterhalb des Stundenzettels --}}
    <x-card :title="__('Kundenfreigabe')">
        @if($timesheet->isSigned() || $timesheet->isLocked())
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="text-sm">
                    <div><strong>{{ $timesheet->customer_name ?: __('Unbekannt') }}</strong> @if($timesheet->customer_role) ({{ $timesheet->customer_role }}) @endif</div>
                    @if($timesheet->customer_email)<div>{{ $timesheet->customer_email }}</div>@endif
                    @if($timesheet->signed_at || $timesheet->signed_ip)
                        <div class="mt-1 text-base-content/60">
                            @if($timesheet->signed_at)
                                {{ __('Signiert am') }}: {{ $timesheet->signed_at?->fdatetime() }}
                            @endif
                            @if($timesheet->signed_ip)
                                @if($timesheet->signed_at) · @endif
                                IP {{ $timesheet->signed_ip }}
                            @endif
                        </div>
                    @endif
                    @if($timesheet->signature_hash)
                        <div class="text-xs break-all text-base-content/50">SHA-256: {{ $timesheet->signature_hash }}</div>
                    @endif
                </div>
                @if($timesheet->signatureAttachment)
                    <div>
                        <img src="{{ \App\Http\Controllers\AttachmentController::downloadUrl($timesheet->signatureAttachment) }}"
                             alt="signature" class="max-h-32 rounded border border-base-300 bg-white p-2">
                    </div>
                @endif
            </div>
        @elseif($canSign)
            @include('timesheets._signature_pad', [
                'action'        => route('projects.timesheets.sign', [$project, $timesheet]),
                'timesheet'     => $timesheet,
            ])
        @else
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">description</span>' :message="__('Stundenzettel ist noch nicht zur Signatur freigegeben.')" />
        @endif
    </x-card>
</x-page-shell>
@endsection
