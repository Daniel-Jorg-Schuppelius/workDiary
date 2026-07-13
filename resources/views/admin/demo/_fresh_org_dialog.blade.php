{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fresh_org_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  freshDemoOrg-Dialog (MVP-349, demo-mandant.md §2): Plattform-Admin legt eine
  neue, isolierte Demo-Organisation aus einer Musterbranche an — Modal-first
  nach dem Muster der Mandantenverwaltung (_form_dialog).
--}}
@php
    /** @var array<int, \App\Enums\Demo\DemoIndustry> $industries */
    /** @var \App\Enums\Demo\DemoIndustry $defaultIndustry */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $platformAdmins */
@endphp
<x-modal
    :title="__('Demo-Organisation anlegen')"
    icon="science"
    tone="primary"
    :action="route('admin.demo.fresh-org.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')"
>
    <p class="text-sm text-base-content/70">
        {{ __('Erzeugt eine neue, isolierte Demo-Organisation mit vollständigen Beispieldaten der gewählten Musterbranche. Der Mandant ist als Demo markiert und kann jederzeit zurückgesetzt oder endgültig gelöscht werden.') }}
    </p>

    <x-select-field name="industry" :label="__('Musterbranche')" required>
        @foreach ($industries as $industry)
            <option value="{{ $industry->value }}" @selected($defaultIndustry->value === $industry->value)>
                {{ $industry->label() }}
            </option>
        @endforeach
    </x-select-field>

    <x-select-field name="member"
                    :label="__('Plattform-Admin als Mitglied zuweisen')"
                    :hint="__('Der ausgewählte Plattform-Admin wird der neuen Demo-Organisation als Mitglied zugeordnet und kann sie direkt einsehen. Der Wechsel zurück ist jederzeit über den Org-Switcher möglich.')">
        <option value="">{{ __('Keine Zuweisung') }}</option>
        @foreach ($platformAdmins as $admin)
            <option value="{{ $admin->sqid }}">{{ $admin->name }} ({{ $admin->email }})</option>
        @endforeach
    </x-select-field>
</x-modal>
