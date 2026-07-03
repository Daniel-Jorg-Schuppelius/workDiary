{{--
  Created on   : Fri Jun 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _correction_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{--
  Korrektur anfordern (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md §5):
  Standalone-<x-modal> mit Pflicht-Begründung (≥ REASON_MIN_LENGTH Zeichen);
  serverseitig erzwungen in DayCloseController::requestCorrection().
  Erwartet $day (CarbonImmutable) aus show.blade.php.
--}}

<x-modal id="day-correction-dialog"
         :embedded="false"
         tone="warning"
         icon="edit_note"
         :eyebrow="__('day-close.title')"
         :title="__('day-close.modal.correction_title')"
         :action="route('day-close.request-correction')"
         method="POST"
         :submitLabel="__('day-close.action.request_correction')"
         submitClass="btn-warning">

    <input type="hidden" name="date" value="{{ $day->toDateString() }}" />

    <p class="text-sm opacity-70">{{ __('day-close.hint.correction_intro') }}</p>

    <div class="fieldset">
        <label class="fieldset-label" for="day-correction-reason">{{ __('day-close.field.reason') }}</label>
        <textarea id="day-correction-reason" name="reason" required rows="4"
                  minlength="{{ \App\Services\TimeApproval\DayCloseService::REASON_MIN_LENGTH }}" maxlength="2000"
                  class="textarea textarea-bordered w-full"
                  placeholder="{{ __('day-close.field.reason_placeholder', ['min' => \App\Services\TimeApproval\DayCloseService::REASON_MIN_LENGTH]) }}"></textarea>
    </div>
</x-modal>
