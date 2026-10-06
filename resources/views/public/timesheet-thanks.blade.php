{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : timesheet-thanks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-public-page :title="__('Vielen Dank')" main="rounded-box bg-base-100 p-8 text-center shadow" body="grid min-h-screen place-items-center bg-base-200 p-6" :assets="['resources/css/app.css']">
    <h1 class="font-['Space_Grotesk'] text-2xl font-semibold">{{ __('Vielen Dank!') }}</h1>
    <p class="mt-2 text-base-content/70">{{ __('Ihre Unterschrift wurde erfasst.') }}</p>
</x-public-page>
