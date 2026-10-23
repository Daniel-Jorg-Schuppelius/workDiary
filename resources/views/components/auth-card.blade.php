{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : auth-card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Karte der Seiten außerhalb des App-Rahmens: Anmeldung, Passwort, Zwei-Faktor, SSO, Lizenzhinweis. --}}
<div {{ $attributes->class(['rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs']) }}>
    {{ $slot }}
</div>
