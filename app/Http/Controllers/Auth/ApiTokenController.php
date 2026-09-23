<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiTokenController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Api\ApiAbility;
use App\Models\User;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller {
    public function index(Request $request): View {
        /** @var User $user */
        $user = $request->user();
        $tokens = $user->tokens()->orderByDesc('created_at')->get();

        return view('profile.api-tokens', [
            'tokens' => $tokens,
            'newToken' => session('newToken'),
            'newTokenName' => session('newTokenName'),
        ]);
    }

    /** Modal-Dialog zum Anlegen eines Tokens (Standard: Formulare im Dialog). */
    public function create(): View {
        return view('profile.api-tokens._form_dialog', [
            'abilities' => ApiAbility::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', Rule::in(ApiAbility::values())],
        ]);

        // Ohne gewählte Ability: voller Zugriff (Sanctum-Default `*`) — so bleiben
        // bestehende Integrationen und das reine Namensformular abwärtskompatibel.
        $abilities = ! empty($data['abilities'])
            ? array_values(array_unique($data['abilities']))
            : ['*'];

        $token = $user->createToken($data['name'], $abilities);

        return redirect()
            ->route('profile.api-tokens.index')
            ->with('newToken', $token->plainTextToken)
            ->with('newTokenName', $data['name']);
    }

    public function destroy(Request $request, string $id): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        // Sqid statt roher Token-ID (Enumeration-Schutz); Löschung bleibt auf
        // die eigenen Tokens des angemeldeten Nutzers gescopt.
        $tokenId = Sqid::decodeOrAbort(PersonalAccessToken::class, $id);
        $user->tokens()->where('id', $tokenId)->delete();

        return redirect()->route('profile.api-tokens.index')
            ->with('success', __('Token widerrufen.'));
    }
}
