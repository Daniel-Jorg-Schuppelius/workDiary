<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhotoConfirmationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Attachments\{Attachment, AttachmentConfirmation};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Customer\CustomerQueryService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Storage};
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fotos der Fallakte im Kundenportal (Feature 012, Rang 55): ansehen,
 * einmalig bestätigen oder beanstanden — die Beanstandung läuft über den
 * bestehenden CustomerQuery-Flow (Notification `customer.queryRaised` an die
 * Organisation).
 */
class PhotoConfirmationController extends Controller {
    /** Als Download ausgeliefert: ein inline gezeigtes SVG/HTML liefe sonst auf der App-Origin. */
    public function show(DiaryEntry $diary, Attachment $attachment): BinaryFileResponse {
        $this->authorizePhoto($diary, $attachment);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    public function confirm(DiaryEntry $diary, Attachment $attachment): RedirectResponse {
        $user = $this->authorizePhoto($diary, $attachment);

        // Einmalige Bestätigung je Foto und Portal-Benutzer (DB-Unique).
        AttachmentConfirmation::query()->firstOrCreate(
            ['attachment_id' => $attachment->id, 'user_id' => $user->id],
            [
                'organization_id' => $attachment->organization_id,
                'confirmed_at' => now(),
            ],
        );

        return back()->with('status', __('Foto bestätigt.'));
    }

    public function complain(Request $request, DiaryEntry $diary, Attachment $attachment, CustomerQueryService $queries): RedirectResponse {
        $user = $this->authorizePhoto($diary, $attachment);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        // Beanstandung = Rückfrage am Auftrag (bestehender Flow inkl.
        // Benachrichtigung customer.queryRaised an die Organisation).
        $queries->raise($diary, [
            'organization_id' => (int) $diary->organization_id,
            'customer_id' => $user->customer_id,
            'asker_name' => $user->name,
            'asker_email' => $user->email,
            'question' => __('Foto beanstandet (:file): :note', [
                'file' => $attachment->original_name,
                'note' => $data['note'],
            ]),
        ]);

        return back()->with('status', __('Beanstandung übermittelt — wir melden uns.'));
    }

    /** Foto muss kundensichtbar sein und zum eigenen Auftrag gehören. */
    private function authorizePhoto(DiaryEntry $diary, Attachment $attachment): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_unless((int) $diary->customer_id === (int) $user->customer_id, 404);
        abort_unless(
            $attachment->attachable_type === $diary->getMorphClass()
            && (int) $attachment->attachable_id === (int) $diary->id
            && (bool) $attachment->customer_visible,
            404,
        );

        return $user;
    }
}
