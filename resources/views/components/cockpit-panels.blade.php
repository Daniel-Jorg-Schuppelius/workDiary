{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : cockpit-panels.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-cockpit-panels :subject="$customer" /> — Panels der Module im Cockpit
  eines Geschäftsobjekts (Feature 182); Recht und Modul prüft jedes Panel.
--}}
@props(['subject'])

@auth
    @foreach (app(\App\Services\Cockpit\CockpitPanels::class)->render($subject, auth()->user()) as $panelHtml)
        {{ $panelHtml }}
    @endforeach
@endauth
