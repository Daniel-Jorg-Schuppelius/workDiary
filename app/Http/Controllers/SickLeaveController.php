<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SickLeaveController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveSickLeaveRequest;
use App\Models\Attachment;
use App\Models\SickLeave;
use App\Models\User;
use App\Support\LookupCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SickLeaveController extends Controller
{
    // ── Create / Store ──────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('create', SickLeave::class);

        return view('sick-leaves._form_dialog', [
            'sickLeave' => null,
            'isEdit' => false,
            'isDialog' => true,
            'canAssignOthers' => $auth->isAdmin(),
            'assignableUsers' => $auth->isAdmin() ? LookupCache::userDropdown() : collect(),
            'previousLeaves' => $this->previousLeavesFor($auth, $auth->isAdmin() ? null : (int) $auth->id),
            'prefillStart' => $request->query('start_date') ?? '',
            'prefillEnd' => $request->query('end_date') ?? '',
        ]);
    }

    public function store(SaveSickLeaveRequest $request): RedirectResponse
    {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('create', SickLeave::class);

        $data = $request->validated();
        $kasseNotified = (bool) ($data['kasse_notified'] ?? false);
        unset($data['kasse_notified'], $data['au_file']);

        if (! $auth->isAdmin() || empty($data['user_id'])) {
            $data['user_id'] = $auth->id;
        }
        $data['reported_at'] = now();
        $data['recorded_by'] = $auth->id;
        $data['kasse_notified_at'] = $kasseNotified ? now() : null;

        /** @var SickLeave $sickLeave */
        $sickLeave = SickLeave::create($data);

        $this->handleUpload($request, $sickLeave);

        return redirect()->route('duties.index', ['tab' => 'krank'])
            ->with('success', __('Krankmeldung erfasst.'));
    }

    // ── Edit / Update ───────────────────────────────────────────────────────

    public function edit(SickLeave $sickLeave): View
    {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('update', $sickLeave);

        return view('sick-leaves._form_dialog', [
            'sickLeave' => $sickLeave,
            'isEdit' => true,
            'isDialog' => true,
            'canAssignOthers' => $auth->isAdmin(),
            'assignableUsers' => $auth->isAdmin() ? LookupCache::userDropdown() : collect(),
            'previousLeaves' => $this->previousLeavesFor($auth, (int) $sickLeave->user_id, $sickLeave),
            'prefillStart' => '',
            'prefillEnd' => '',
        ]);
    }

    public function update(SaveSickLeaveRequest $request, SickLeave $sickLeave): RedirectResponse
    {
        Gate::authorize('update', $sickLeave);

        $data = $request->validated();
        $kasseNotified = (bool) ($data['kasse_notified'] ?? false);
        unset($data['kasse_notified'], $data['au_file']);

        // user_id darf nur Admin ändern
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            unset($data['user_id']);
        }

        if ($kasseNotified && $sickLeave->kasse_notified_at === null) {
            $data['kasse_notified_at'] = now();
        } elseif (! $kasseNotified) {
            $data['kasse_notified_at'] = null;
        }

        $sickLeave->update($data);

        $this->handleUpload($request, $sickLeave);

        return redirect()->route('duties.index', ['tab' => 'krank'])
            ->with('success', __('Krankmeldung aktualisiert.'));
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    public function destroy(SickLeave $sickLeave): RedirectResponse
    {
        Gate::authorize('delete', $sickLeave);

        foreach ($sickLeave->attachments as $att) {
            Storage::disk($att->disk)->delete($att->path);
            $att->delete();
        }
        $sickLeave->delete();

        return redirect()->route('duties.index', ['tab' => 'krank'])
            ->with('success', __('Krankmeldung gelöscht.'));
    }

    // ── Cancel ──────────────────────────────────────────────────────────────

    public function cancel(Request $request, SickLeave $sickLeave): RedirectResponse
    {
        Gate::authorize('cancel', $sickLeave);

        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $sickLeave->update([
            'cancelled_at' => now(),
            'cancel_reason' => $data['cancel_reason'] ?? null,
        ]);

        return redirect()->route('duties.index', ['tab' => 'krank'])
            ->with('success', __('Krankmeldung storniert.'));
    }

    // ── Attachment Download (signiert) ──────────────────────────────────────

    public function downloadAttachment(Request $request, SickLeave $sickLeave, Attachment $attachment): BinaryFileResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        Gate::authorize('downloadAttachment', [$sickLeave, $attachment]);

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    public static function attachmentDownloadUrl(SickLeave $sickLeave, Attachment $attachment): string
    {
        return URL::temporarySignedRoute(
            'sick-leaves.attachments.download',
            now()->addMinutes(15),
            ['sick_leave' => $sickLeave->id, 'attachment' => $attachment->id],
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function handleUpload(SaveSickLeaveRequest $request, SickLeave $sickLeave): void
    {
        $file = $request->file('au_file');
        if (! $file instanceof UploadedFile) {
            return;
        }

        $disk = (string) config('sickness.attachments.disk', 'local');
        $base = trim((string) config('sickness.attachments.path', 'sick-notes'), '/');
        $folder = $base.'/'.$sickLeave->user_id.'/'.$sickLeave->id;
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename = Str::uuid()->toString().'.'.$ext;
        $path = $file->storeAs($folder, $filename, $disk);

        $sickLeave->attachments()->create([
            'user_id' => Auth::id(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $this->sanitizeFilename($file->getClientOriginalName()),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
        ]);
    }

    private function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '_', $name) ?? 'datei';

        return mb_substr($name, 0, 255);
    }

    /**
     * Mögliche Vorgänger-Krankmeldungen für ein Follow-Up-Dropdown:
     * zuletzt aktive Einträge desselben Users, optional ausschließlich des
     * aktuell bearbeiteten Eintrags.
     *
     * @return Collection<int, SickLeave>
     */
    private function previousLeavesFor(User $auth, ?int $userId, ?SickLeave $exclude = null)
    {
        $query = SickLeave::query()
            ->whereNull('cancelled_at')
            ->orderByDesc('end_date')
            ->limit(20);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } elseif (! $auth->isAdmin()) {
            $query->where('user_id', $auth->id);
        }

        if ($exclude instanceof SickLeave) {
            $query->where('id', '!=', $exclude->id);
        }

        return $query->get();
    }
}
