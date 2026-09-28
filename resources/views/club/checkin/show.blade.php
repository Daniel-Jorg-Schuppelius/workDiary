{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  QR-Selbst-Check-in (Feature 159, MVP-1004): eigene und vertretene Mitglieder
  für den Termin einchecken. Variablen: $event, $code, $confirmed, $rows
--}}
@extends('layouts.app')
@section('title', __('club.checkin.title'))
@section('nav-title', __('club.checkin.title'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$event->title . ' · ' . $event->started_at->orgTz()->format('d.m.Y H:i')" back-route="club.my.index" :back-label="__('club.nav.my')" />
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert"><x-icon name="error" /><span>{{ $errors->first() }}</span></div>
    @endif
    @if ($confirmed)
        <div class="alert alert-info text-sm" role="status"><x-icon name="lock" /><span>{{ __('club.checkin.hint.confirmed') }}</span></div>
    @endif

    <x-card :title="__('club.checkin.card.members')" icon="how_to_reg">
        <ul class="divide-y divide-base-300">
            @foreach ($rows as $row)
                <li class="flex items-center justify-between gap-3 py-3">
                    <span class="font-medium">{{ $row['member']->fullName() }}</span>
                    @if ($row['record'] !== null && $row['record']->status === \App\Enums\Club\ClubAttendanceStatus::Present)
                        <x-status-badge tone="success" size="sm">{{ __('club.checkin.status.checked_in') }}</x-status-badge>
                    @elseif (! $row['expected'])
                        <span class="text-sm text-muted">{{ __('club.checkin.status.not_expected') }}</span>
                    @elseif (! $confirmed)
                        <x-action-form :action="route('club.checkin.store', $code)">
                            <input type="hidden" name="member" value="{{ $row['member']->sqid }}">
                            <x-icon-btn icon="login" tone="primary" type="submit" show-label>{{ __('club.checkin.action.check_in') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</x-page-shell>
@endsection
