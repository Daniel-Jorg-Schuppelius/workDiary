{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : focus-dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Arbeitsbereich-Dialog (Feature 082, MVP-378): Großkachel-Auswahl der
    schaltbaren Fokus-Ansichten. Jede Kachel ist ein POST-Formular auf
    me.focus.switch — server-seitig, kein AJAX (wie mode.switch). Öffnet über
    data-open-dialog="focus-dialog", schließt über data-entry-modal-close.

    Erwartet aus dem Layout: $navFocusAvailable (Liste {key,label,description,
    icon,personal}), $navFocusActive (aktiver Schlüssel). Eigene
    Arbeitsbereiche (Feature 082 Phase 2, MVP-731) stehen als eigener Block
    darunter — sie gehören nur ihrer Person, deshalb kuratiert die
    Organisation sie nicht mit.
--}}
<x-modal
    id="focus-dialog"
    :embedded="false"
    size="wide"
    tone="primary"
    icon="dashboard_customize"
    :eyebrow="__('scope.focus.dialog.eyebrow')"
    :title="__('scope.focus.dialog.title')">

    <p class="mb-4 text-sm text-base-content/70">{{ __('scope.focus.dialog.subtitle') }}</p>

    @php
        $_focusProduct = array_values(array_filter($navFocusAvailable, static fn (array $f): bool => empty($f['personal'])));
        $_focusPersonal = array_values(array_filter($navFocusAvailable, static fn (array $f): bool => ! empty($f['personal'])));
    @endphp

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($_focusProduct as $focus)
            @php $active = $focus['key'] === $navFocusActive; @endphp
            <form method="POST" action="{{ route('me.focus.switch', $focus['key']) }}" class="m-0">
                @csrf
                <button type="submit"
                        class="group relative flex h-full w-full flex-col gap-3 rounded-box border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md
                               {{ $active ? 'border-primary bg-primary/10 ring-1 ring-primary' : 'border-base-300 bg-base-100 hover:border-primary/40 hover:bg-base-200' }}">
                    @if ($active)
                        <span class="absolute right-3 top-3 badge badge-primary badge-sm">{{ __('scope.focus.active') }}</span>
                    @endif
                    <span class="flex size-12 items-center justify-center rounded-field {{ $active ? 'bg-primary text-primary-content' : 'bg-base-200 text-primary' }}">
                        <x-icon :name="$focus['icon']" class="text-[1.6rem]" />
                    </span>
                    <span class="font-semibold leading-tight">{{ $focus['label'] }}</span>
                    <span class="text-xs leading-snug text-base-content/65">{{ $focus['description'] }}</span>
                </button>
            </form>
        @endforeach
    </div>

    <div class="mt-6 flex items-center gap-2 border-t border-base-300 pt-4">
        <h3 class="text-sm font-semibold">{{ __('scope.focus.personal.heading') }}</h3>
        <a href="{{ route('me.workspaces.index') }}" class="btn btn-ghost btn-xs ml-auto gap-1">
            <x-icon name="tune" class="text-[1rem]" />
            {{ __('scope.focus.personal.manage') }}
        </a>
    </div>

    @if ($_focusPersonal === [])
        <p class="mt-2 text-xs text-muted">{{ __('scope.workspace.empty') }}</p>
    @else
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($_focusPersonal as $focus)
                @php $active = $focus['key'] === $navFocusActive; @endphp
                <form method="POST" action="{{ route('me.focus.switch', $focus['key']) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="group relative flex h-full w-full flex-col gap-3 rounded-box border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md
                                   {{ $active ? 'border-primary bg-primary/10 ring-1 ring-primary' : 'border-base-300 bg-base-100 hover:border-primary/40 hover:bg-base-200' }}">
                        @if ($active)
                            <span class="absolute right-3 top-3 badge badge-primary badge-sm">{{ __('scope.focus.active') }}</span>
                        @endif
                        <span class="flex size-12 items-center justify-center rounded-field {{ $active ? 'bg-primary text-primary-content' : 'bg-base-200 text-primary' }}">
                            <x-icon :name="$focus['icon']" class="text-[1.6rem]" />
                        </span>
                        <span class="font-semibold leading-tight">{{ $focus['label'] }}</span>
                        <span class="text-xs leading-snug text-base-content/65">{{ $focus['description'] }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <x-slot:footerExtra>
        <span class="flex items-start gap-2 text-left text-xs text-muted">
            <x-icon name="info" class="mt-0.5 shrink-0 text-[1.05rem] text-primary" />
            <span>{{ __('scope.focus.dialog.footnote') }}</span>
        </span>
    </x-slot:footerExtra>

    <x-slot:actions>
        <x-button type="button" tone="ghost" data-entry-modal-close icon="close">{{ __('Schließen') }}</x-button>
    </x-slot:actions>
</x-modal>
