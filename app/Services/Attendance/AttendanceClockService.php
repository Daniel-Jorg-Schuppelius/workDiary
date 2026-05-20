<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceClockService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service for the "Stempeluhr" (clock-in/clock-out) workflow.
 *
 * Guarantees:
 *  - Only one open attendance per user at any time.
 *  - Auto-close detects forgotten clock-outs (after configurable maximum
 *    or at end of day boundaries) and creates a closed attendance with
 *    source=auto_close.
 */
class AttendanceClockService
{
    public function __construct(
        /** Maximum allowed open session length in minutes before auto-close. */
        protected int $maxOpenMinutes = 16 * 60,
    ) {}

    /**
     * Returns the currently open attendance for a user, if any.
     */
    public function current(User $user): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    /**
     * Starts a new attendance for the user. Fails if one is already open.
     *
     * @param  array<string, mixed>  $context  optional: lat, lng, device, note, source, started_at
     */
    public function clockIn(User $user, array $context = []): Attendance
    {
        return DB::transaction(function () use ($user, $context) {
            if ($this->current($user)) {
                throw new RuntimeException('User already has an open attendance.');
            }

            $start = isset($context['started_at'])
                ? CarbonImmutable::parse($context['started_at'])
                : CarbonImmutable::now();

            return Attendance::create([
                'organization_id' => $user->organization_id ?? null,
                'user_id' => $user->id,
                'started_at' => $start,
                'ended_at' => null,
                'date' => $start->startOfDay(),
                'source' => $context['source'] ?? AttendanceSource::Clock->value,
                'status' => AttendanceStatus::Open->value,
                'started_lat' => $context['lat'] ?? null,
                'started_lng' => $context['lng'] ?? null,
                'started_device' => $context['device'] ?? null,
                'note' => $context['note'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    /**
     * Closes the currently open attendance. Returns the closed model, or
     * null if no open attendance existed.
     *
     * @param  array<string, mixed>  $context  optional: lat, lng, device, note, ended_at, break_minutes
     */
    public function clockOut(User $user, array $context = []): ?Attendance
    {
        return DB::transaction(function () use ($user, $context) {
            $attendance = $this->current($user);
            if (! $attendance) {
                return null;
            }

            $end = isset($context['ended_at'])
                ? CarbonImmutable::parse($context['ended_at'])
                : CarbonImmutable::now();

            if ($end->lessThan($attendance->started_at)) {
                throw new RuntimeException('ended_at must be after started_at.');
            }

            $attendance->ended_at = Carbon::instance($end);
            $attendance->ended_lat = $context['lat'] ?? null;
            $attendance->ended_lng = $context['lng'] ?? null;
            $attendance->ended_device = $context['device'] ?? null;
            if (isset($context['note']) && $context['note'] !== '') {
                $attendance->note = trim(($attendance->note ?? '')."\n".$context['note']);
            }
            if (isset($context['break_minutes'])) {
                $attendance->break_minutes_manual = (int) $context['break_minutes'];
            }
            $attendance->status = AttendanceStatus::Closed;
            $attendance->closed_by = $user->id;
            $attendance->updated_by = $user->id;
            $attendance->save();

            return $attendance->refresh();
        });
    }

    /**
     * Cancels (soft-discards) the current open attendance — used for accidental clock-ins.
     */
    public function cancel(User $user, ?string $reason = null): ?Attendance
    {
        $attendance = $this->current($user);
        if (! $attendance) {
            return null;
        }
        $attendance->ended_at = $attendance->started_at; // zero-length
        $attendance->status = AttendanceStatus::Cancelled;
        $attendance->note = trim(($attendance->note ?? '')."\nCancelled: ".($reason ?? ''));
        $attendance->closed_by = $user->id;
        $attendance->updated_by = $user->id;
        $attendance->save();

        return $attendance->refresh();
    }

    /**
     * Adds a manual break to the currently open attendance.
     */
    public function addBreak(User $user, int $minutes): ?Attendance
    {
        $attendance = $this->current($user);
        if (! $attendance) {
            return null;
        }
        $attendance->break_minutes_manual = (int) $attendance->break_minutes_manual + max(0, $minutes);
        $attendance->save();

        return $attendance->refresh();
    }

    /**
     * Automatically closes any open attendance that has been running longer
     * than maxOpenMinutes. Called by a scheduled console command.
     *
     * @return int Number of attendances that were auto-closed.
     */
    public function autoCloseStaleSessions(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $threshold = $now->copy()->subMinutes($this->maxOpenMinutes);

        $count = 0;
        Attendance::query()
            ->whereNull('ended_at')
            ->where('started_at', '<', $threshold)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$count): void {
                foreach ($chunk as $attendance) {
                    /** @var Attendance $attendance */
                    \assert($attendance->started_at !== null);
                    $attendance->ended_at = $attendance->started_at
                        ->copy()
                        ->addMinutes($this->maxOpenMinutes);
                    $attendance->status = AttendanceStatus::AutoClosed;
                    $attendance->source = AttendanceSource::AutoClose;
                    $attendance->note = trim(($attendance->note ?? '')."\nAuto-closed by system.");
                    $attendance->save();
                    $count++;
                }
            });

        return $count;
    }
}
