{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cells.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zwei Zellen je Stunde wie im Altportal: Auftrag und Zustandsfarbe. Erwartet: $entry (darf null sein), $onCall, $notdienst, $even, $showRoute --}}
@php
    $tint = $onCall ? 'b' : ($notdienst ? 'n' : '');
    $contentClass = ($even ? 'ro' : 'rog') . $tint;
    $statusClass = match ($entry?->gelesen) {
        -1 => 'ok',
        1 => 'work',
        2 => 'off',
        3 => 'neu',
        default => ($even ? 'lo' : 'log') . $tint,
    };
    $statusTitle = match ($entry?->gelesen) {
        -1 => __('legacy_classic.status.done'),
        1 => __('legacy_classic.status.confirmed'),
        2 => __('legacy_classic.status.unread'),
        3 => trim(__('legacy_classic.status.problem') . "\n" . (string) $entry?->antwort),
        default => $entry !== null ? __('legacy_classic.status.undefined') : null,
    };
@endphp
<td class="{{ $contentClass }} tb-slot">@if ($entry !== null)<a href="{{ route($showRoute, $entry) }}" class="kp-normal" title="{{ $entry->inhalt }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate((string) $entry->inhalt, 10, '') }}</a>@else&nbsp;@endif</td>
<td class="{{ $statusClass }}" @if ($statusTitle !== null) title="{{ $statusTitle }}" @endif>@if ($statusTitle !== null)<span class="kp-sr-only">{{ $statusTitle }}</span>@endif&nbsp;</td>
