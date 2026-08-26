{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : shortcuts-dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Tastenkürzel-Übersicht (Feature 037, MVP-721). Öffnet per „?" außerhalb
    von Eingabefeldern oder über [data-shortcuts-trigger]. Die Liste rendert
    resources/js/shortcuts.js aus der zentralen Registry; hier steht nur der
    Rahmen (Titel, Hinweis, Hilfe-Link, Schließen) — serverseitig übersetzt.
--}}
<dialog id="shortcuts-dialog"
        class="modal"
        aria-labelledby="shortcuts-dialog-title"
        aria-describedby="shortcuts-dialog-hint">
    <div class="modal-box max-w-lg" data-shortcuts-root>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="shortcuts-dialog-title" class="text-lg font-semibold">{{ __('Tastenkürzel') }}</h2>
                <p id="shortcuts-dialog-hint" class="mt-1 text-sm text-muted">{{ __('Übersicht aller Tastenkürzel. Kürzel gelten nicht in Eingabefeldern.') }}</p>
            </div>
            <form method="dialog">
                <x-icon-btn icon="close" tone="ghost" size="sm" class="btn-square" :label="__('Schließen')" />
            </form>
        </div>

        <div class="mt-4" data-shortcuts-list aria-live="polite"></div>

        <div class="mt-5 flex items-center justify-between gap-3 border-t border-base-300 pt-3">
            <button type="button"
                    class="link link-primary text-sm"
                    data-help-trigger
                    data-help-topic="account.shortcuts"
                    aria-haspopup="dialog"
                    aria-controls="help-drawer">
                {{ __('Hilfe zu Tastenkürzeln') }}
            </button>
            <form method="dialog">
                <x-button type="submit" tone="ghost" size="sm">{{ __('Schließen') }}</x-button>
            </form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
