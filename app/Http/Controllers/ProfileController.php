<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesUserContactDetails;
use App\Models\{Attachment, User};
use App\Services\Attachments\ImageMetaUploader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Validation\Rule;

class ProfileController extends Controller {
    use ManagesUserContactDetails;

    public function __construct(private readonly ImageMetaUploader $avatarUploader) {}

    public function edit(): View {
        /** @var User $user */
        $user = $this->authUser();
        $user->loadMissing(['attachments', 'addresses', 'bankAccounts']);

        return view('account._profile_dialog', [
            'user' => $user,
            'isDialog' => true,
        ]);
    }

    public function update(Request $request): RedirectResponse {
        /** @var User $user */
        $user = $this->authUser();

        $themes = (array) config('personalization.themes', []);
        $startpages = (array) config('personalization.startpages', []);
        $avatarMaxKb = (int) config('branding.limits.avatar_kb', 1024);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'preferences' => ['sometimes', 'array'],
            'preferences.theme' => ['nullable', 'string', Rule::in($themes)],
            'preferences.locale' => ['nullable', 'string', 'max:10'],
            'preferences.date_format' => ['nullable', 'string', 'max:32'],
            'preferences.time_format' => ['nullable', 'string', 'max:32'],
            'preferences.startpage' => ['nullable', 'string', Rule::in($startpages)],
            'avatar' => ['nullable', 'file'],
            'remove_avatar' => ['nullable', 'boolean'],
        ] + $this->contactDetailRules());

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        $this->fillUserContactFields($user, $data);

        if ($request->has('preferences')) {
            $clean = array_filter(
                (array) ($data['preferences'] ?? []),
                static fn($v) => $v !== null && $v !== ''
            );
            $user->preferences = $clean === [] ? null : $clean;
        }

        $user->save();

        $this->syncUserAddress($user, (array) ($data['address'] ?? []));
        $this->syncUserBankAccount($user, (array) ($data['bank'] ?? []));

        $avatar = $request->file('avatar');
        if ($avatar instanceof UploadedFile) {
            $this->avatarUploader->replace($user, Attachment::META_AVATAR, $avatar, $avatarMaxKb, 'avatar');
        } elseif ($request->boolean('remove_avatar')) {
            $this->avatarUploader->delete($user, Attachment::META_AVATAR);
        }

        return back()->with('success', __('Profil aktualisiert.'));
    }
}
