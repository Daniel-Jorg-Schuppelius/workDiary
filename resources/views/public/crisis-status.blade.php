{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : crisis-status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-public-page :title="__('crisis.status_page.public_title', ['org' => $orgName])" main="max-w-2xl mx-auto p-4 flex flex-col gap-4" icons>
    <x-card class="flex flex-col gap-1">
        <h1 class="card-title">{{ __('crisis.status_page.public_title', ['org' => $orgName]) }}</h1>
        <p class="text-sm opacity-70">{{ __('crisis.status_page.public_intro') }}</p>
    </x-card>

    @if ($notices === [])
        <div role="status" class="alert alert-success">
            <x-icon name="check_circle" />
            <span>{{ __('crisis.status_page.all_clear') }}</span>
        </div>
    @else
        <x-portal-notices :notices="$notices" />
    @endif
</x-public-page>
