{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : posting-state.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungsstand einer Quelle (Feature 125, MVP-673/681). Derselbe Stand steht
  in der Buchungs-Inbox und an den Bestandsseiten — er wird aus dem Journal
  gelesen, nirgends zweitgespeichert.
--}}
@props(['state', 'blockers' => []])

@php
    $classes = match ($state) {
        'blocked' => 'badge-error',
        'ready' => 'badge-info',
        'posted' => 'badge-success',
        default => 'badge-ghost',
    };
@endphp

<span @class(['tooltip' => $blockers !== []]) @if ($blockers !== []) data-tip="{{ implode(' · ', $blockers) }}" @endif>
    <span class="badge badge-sm {{ $classes }}">{{ __('accounting.inbox.state.' . $state) }}</span>
</span>
