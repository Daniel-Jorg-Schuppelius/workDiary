{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : driving-time-badge.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Lenkzeit-Budget eines Fahrers (Feature 144, MVP-719) als Badge für die
    Disposition — Eingabe ist das Array aus DrivingTimeBudget::remainingFor();
    ohne Budget (null) wird nichts gerendert.
--}}
@props(['budget' => null, 'size' => 'xs'])
@php
    $clock = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    $tone = 'success';
    $text = '';
    $title = '';
    if (is_array($budget)) {
        $exhausted = $budget['daily_remaining'] === 0 || $budget['weekly_remaining'] === 0 || $budget['fortnight_remaining'] === 0;
        if ($exhausted) {
            $tone = 'error';
            $text = __('compliance.driving.badge.exhausted');
        } elseif ($budget['until_break'] === 0) {
            $tone = 'error';
            $text = __('compliance.driving.badge.break_due');
        } else {
            $tone = ($budget['daily_remaining'] < 60 || $budget['until_break'] < 60) ? 'warning' : 'success';
            $text = __('compliance.driving.badge.remaining', ['remaining' => $clock($budget['daily_remaining'])])
                . ' · ' . __('compliance.driving.badge.until_break', ['until' => $clock($budget['until_break'])]);
        }
        $title = __('compliance.driving.badge.title', [
            'daily' => $clock($budget['daily_remaining']),
            'limit' => $clock($budget['daily_limit']),
            'until' => $clock($budget['until_break']),
            'weekly' => $clock($budget['weekly_remaining']),
            'fortnight' => $clock($budget['fortnight_remaining']),
        ]);
    }
@endphp
@if (is_array($budget))
    <x-status-badge :tone="$tone" :size="$size" icon="local_shipping" :title="$title" {{ $attributes }}>
        <span class="sr-only">{{ __('compliance.driving.badge.label') }}:</span> {{ $text }}
    </x-status-badge>
@endif
