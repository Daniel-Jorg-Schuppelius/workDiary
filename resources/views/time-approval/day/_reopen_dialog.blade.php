{{--
  Created on   : Fri Jun 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reopen_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{--
  Admin-Reopen ohne Antrag (MVP-015, docs/tagesabschluss.md §2.6/§6):
  Standalone-<x-modal> mit Pflicht-Begründung — landet als
  `dayClose.reopened` im Audit-Protokoll. Erwartet $day, $isOwnDay und
  $targetUser aus show.blade.php.
--}}

<x-modal id="day-reopen-dialog"
         :embedded="false"
         tone="warning"
         icon="lock_open"
         :eyebrow="__('day-close.title')"
         :title="__('day-close.modal.reopen_title')"
         :action="route('day-close.reopen')"
         method="POST"
         :submitLabel="__('day-close.action.reopen')"
         submitClass="btn-warning">

    <input type="hidden" name="date" value="{{ $day->toDateString() }}" />
    @if (! $isOwnDay)
        <input type="hidden" name="user" value="{{ \App\Support\Sqid::encode(\App\Models\User::class, $targetUser->id) }}" />
    @endif

    <p class="text-sm opacity-70">{{ __('day-close.hint.reopen_intro') }}</p>

    <div class="fieldset">
        <label class="fieldset-label" for="day-reopen-reason">{{ __('day-close.field.reason') }}</label>
        <textarea id="day-reopen-reason" name="reason" required rows="4"
                  minlength="{{ \App\Services\TimeApproval\DayCloseService::REASON_MIN_LENGTH }}" maxlength="2000"
                  class="textarea textarea-bordered w-full"
                  placeholder="{{ __('day-close.field.reason_placeholder', ['min' => \App\Services\TimeApproval\DayCloseService::REASON_MIN_LENGTH]) }}"></textarea>
    </div>
</x-modal>
