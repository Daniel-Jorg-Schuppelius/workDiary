{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : leaderboard.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bestenliste (Feature 149, MVP-781): nur bei Org-Schalter, nur Personen mit
  eigenem Opt-in (§ 87 Abs. 1 Nr. 6 BetrVG — keine Leistungsübersicht ohne
  Zustimmung). Variablen: $board, $optedIn, $ownPoints.
--}}
@extends('layouts.app')
@section('title', __('learning.title.leaderboard'))
@section('nav-title', __('learning.title.leaderboard'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('learning.subtitle.leaderboard')">
    <x-slot:actions>
        <form method="POST" action="{{ route('learning.my.leaderboard.opt-in') }}">
            @csrf
            <input type="hidden" name="opt_in" value="{{ $optedIn ? '0' : '1' }}">
            <x-icon-btn :icon="$optedIn ? 'visibility_off' : 'visibility'" :tone="$optedIn ? 'outline' : 'primary'" size="sm" type="submit"
                        show-label>{{ __($optedIn ? 'learning.action.leaderboard_opt_out' : 'learning.action.leaderboard_opt_in') }}</x-icon-btn>
        </form>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('learning.my.index')" show-label>{{ __('learning.action.back') }}</x-icon-btn>
    </x-slot:actions>

    <div class="alert alert-info text-sm" role="status">
        <x-icon name="info" />
        <span>{{ __('learning.help.leaderboard') }} {{ __('learning.field.own_points') }}: <strong>{{ $ownPoints }}</strong></span>
    </div>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th class="text-center">{{ __('learning.field.rank') }}</th>
                <th>{{ __('learning.field.learner') }}</th>
                <th class="text-right">{{ __('learning.field.points') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($board as $index => $row)
            <tr class="hover {{ $row['user']->is(auth()->user()) ? 'font-semibold' : '' }}">
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $row['user']->name }}</td>
                <td class="text-right font-mono">{{ $row['points'] }}</td>
            </tr>
        @empty
            <x-table.empty icon="emoji_events" :colspan="3" :title="__('learning.empty.leaderboard')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
