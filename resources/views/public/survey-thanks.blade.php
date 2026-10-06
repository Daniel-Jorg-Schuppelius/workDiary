{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : survey-thanks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-public-page :title="__('Vielen Dank!')" main="mx-auto max-w-2xl p-4" icons>
    <div class="rounded-box bg-base-100 p-8 text-center shadow">
        <x-icon name="check_circle" class="text-5xl text-success" />
        <h1 class="mt-2 text-xl font-semibold">{{ __('Vielen Dank!') }}</h1>
        <p class="mt-1 text-sm text-base-content/70">{{ __('Ihre Antworten zu „:title" sind angekommen.', ['title' => $survey->title]) }}</p>
    </div>
</x-public-page>
