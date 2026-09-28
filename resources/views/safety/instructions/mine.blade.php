{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : mine.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Meine Unterweisungen (MVP-986): offene Nachweise mit Bestätigung, erledigte
  mit Fälligkeit, dazu die eigenen Vorsorgetermine (ohne Gesundheitsdaten).
  Variablen: $open, $done (SafetyInstructionParticipant), $checkups (MedicalCheckup)
--}}
@extends('layouts.app')
@section('title', __('safety.register.title.mine'))
@section('nav-title', __('safety.register.title.mine'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('safety.register.subtitle.mine')"
                        :badge="$open->isNotEmpty() ? trans_choice('safety.register.mine.open_count', $open->count(), ['count' => $open->count()]) : null"
                        badgeTone="warning" />
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('safety.register.mine.open')" icon="pending_actions">
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('safety.register.field.topic') }}</th>
                            <th>{{ __('safety.register.field.held_on') }}</th>
                            <th>{{ __('safety.register.field.instructor') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($open as $row)
                        <tr class="hover">
                            <td class="font-medium"><a class="link link-hover" href="{{ route('safety.instructions.show', $row->instruction) }}">{{ $row->instruction?->topic }}</a></td>
                            <td class="text-sm">{{ $row->instruction?->held_on->format('d.m.Y') }}</td>
                            <td class="text-sm text-base-content/70">{{ $row->instruction?->instructor?->name ?? '–' }}</td>
                            <td class="text-right">
                                @can('sign', $row)
                                    <x-action-form :action="route('safety.instructions.participants.sign', [$row->instruction, $row])"
                                                   :confirm="__('safety.register.confirm.sign')"
                                                   confirm-icon="draw" confirm-tone="primary"
                                                   :confirm-label="__('safety.register.action.sign')">
                                        <x-icon-btn icon="draw" tone="primary" size="xs" type="submit" show-label>{{ __('safety.register.action.sign') }}</x-icon-btn>
                                    </x-action-form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="4" :title="__('safety.register.empty.mine_open')" compact />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('safety.register.mine.done')" icon="task_alt">
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('safety.register.field.topic') }}</th>
                            <th>{{ __('safety.register.field.signed_at') }}</th>
                            <th>{{ __('safety.register.field.method') }}</th>
                            <th>{{ __('safety.register.field.next_due_on') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($done as $row)
                        <tr class="hover">
                            <td class="font-medium"><a class="link link-hover" href="{{ route('safety.instructions.show', $row->instruction) }}">{{ $row->instruction?->topic }}</a></td>
                            <td class="text-sm">{{ $row->signed_at?->orgTz()->format('d.m.Y H:i') }}</td>
                            <td class="text-sm text-base-content/70">{{ $row->method?->label() ?? '–' }}</td>
                            <td class="text-sm {{ $row->isDueOverdue() ? 'text-error font-semibold' : 'text-base-content/70' }}">{{ $row->next_due_on?->format('d.m.Y') ?? '–' }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="4" :title="__('safety.register.empty.mine_done')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('safety.register.mine.checkups')" icon="medical_services">
                <p class="mb-2 text-xs text-muted">{{ __('safety.register.hint.no_health_data') }}</p>
                @forelse ($checkups as $checkup)
                    <div class="flex items-start justify-between gap-2 border-b border-base-200 py-2 text-sm last:border-0">
                        <div>
                            <div class="font-medium">{{ $checkup->occasion ?? $checkup->kind->label() }}</div>
                            <div class="text-xs text-muted">{{ $checkup->kind->label() }} · {{ $checkup->performed_on->format('d.m.Y') }}</div>
                        </div>
                        <div class="text-right text-xs {{ $checkup->isDueOverdue() ? 'text-error font-semibold' : 'text-muted' }}">
                            {{ __('safety.register.field.next_due_on') }}<br>{{ $checkup->next_due_on?->format('d.m.Y') ?? '–' }}
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('safety.register.empty.mine_checkups') }}</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
