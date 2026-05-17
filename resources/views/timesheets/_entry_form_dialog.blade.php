{{-- Dialog for adding a TimeEntry row to a Timesheet --}}
<x-modal
    :title="__('Zeile hinzufügen')"
    :eyebrow="__('Zeiteintrag')"
    icon="schedule"
    tone="primary"
    :action="route('projects.timesheets.entries.store', [$project, $timesheet])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Hinzufügen')"
>
    <x-form-group :legend="__('Zeit')" icon="schedule" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Start') }}</label>
            <input type="datetime-local" name="started_at" required class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Ende') }}</label>
            <input type="datetime-local" name="ended_at" required class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Pause (Min.)') }}</label>
            <input type="number" name="break_minutes" value="0" min="0" max="480" class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Art') }}</label>
            <select name="kind" class="select select-bordered w-full">
                <option value="work">{{ __('Arbeit') }}</option>
                <option value="travel">{{ __('Anfahrt') }}</option>
                <option value="standby">{{ __('Bereitschaft') }}</option>
            </select>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Bezug')" icon="task" tone="info" cols="1">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Aufgabe') }}</label>
            <select name="task_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($tasks as $t)
                    <option value="{{ $t->id }}">{{ $t->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Beschreibung') }}</label>
            <input type="text" name="description" maxlength="500" class="input input-bordered w-full">
        </div>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
