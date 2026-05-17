{{-- Dialog wrapper for WorkSchedule edit --}}
<x-modal
    :title="__('Arbeitszeit-Modell')"
    :eyebrow="$user->name"
    icon="schedule"
    tone="primary"
    :action="route('users.work-schedule.update', $user)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    @include('work-schedules._form_body')
</x-modal>
