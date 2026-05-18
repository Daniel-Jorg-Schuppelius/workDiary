{{--
    Lock/window status alert + comment thread for an existing TimeEntry edit dialog.

    Required:  $entry  (\App\Models\TimeEntry|null — partial does nothing if null)
--}}
@php
    /** @var \App\Models\TimeEntry|null $entry */
    $entry = $entry ?? null;
    $hasEntry = $entry !== null && $entry->exists;

    $blockReason = null;
    $reasonLabel = null;
    $isAdmin = auth()->check() && auth()->user()->isAdmin();

    if ($hasEntry) {
        $editPolicy = app(\App\Services\Timekeeping\TimeEntryEditPolicy::class);
        $blockReason = $editPolicy->blockReason($entry);
        $reasonLabel = $editPolicy->reasonLabel($blockReason);
    }
@endphp

@if ($hasEntry)
    @if ($blockReason)
        <div class="alert alert-{{ $isAdmin ? 'warning' : 'error' }} mb-2">
            <x-icon name="lock" />
            <span>
                @if ($isAdmin)
                    {{ __('Eintrag ist gesperrt (:reason). Du bearbeitest als Admin.', ['reason' => $reasonLabel]) }}
                @else
                    {{ __('Eintrag ist gesperrt (:reason). Eine Bearbeitung durch dich ist nicht mehr möglich; Kommentare sind weiterhin erlaubt.', ['reason' => $reasonLabel]) }}
                @endif
            </span>
        </div>
    @endif

    <x-form-group :legend="__('Kommentare')" icon="forum" tone="ghost">
        @include('comments._thread', [
            'parent' => $entry,
            'storeRoute' => route('time-entries.comments.store', $entry),
            'showHeading' => false,
        ])
    </x-form-group>
@endif
