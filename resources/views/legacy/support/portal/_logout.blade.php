{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _logout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abmelden der Kundensitzung im Header der Kundenseiten. --}}
<form method="POST" action="{{ route('legacy.portal.logout') }}" class="inline">
    @csrf
    <x-icon-btn icon="logout" size="sm" type="submit" show-label>{{ __('legacy_support.portal.logout') }}</x-icon-btn>
</form>
