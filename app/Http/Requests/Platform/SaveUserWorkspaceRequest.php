<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveUserWorkspaceRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\{User, UserWorkspace};
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Anlegen/Bearbeiten eines eigenen Arbeitsbereichs (Feature 082 Phase 2,
 * MVP-731).
 *
 * Der entscheidende Teil ist die **serverseitige** Prüfung der Auswahl: Ein
 * Arbeitsbereich darf nur Menüpunkte enthalten, die die Person laut NavGate
 * ohnehin sehen darf ({@see NavigationRegistry::selectableKeys()}). Sonst
 * wäre der Editor ein bequemer Weg, sich fremde Menüpunkte einzutragen —
 * folgenlos zwar (der Fokus filtert nur), aber es stünde in der Datenbank.
 */
class SaveUserWorkspaceRequest extends FormRequest {
    public function authorize(): bool {
        return Auth::check();
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var User $user */
        $user = $this->user();
        $workspace = $this->route('workspace');
        $ignoreId = $workspace instanceof UserWorkspace ? $workspace->getKey() : null;

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('user_workspaces', 'name')
                    ->where('user_id', $user->getKey())
                    ->ignore($ignoreId),
            ],
            'icon' => ['nullable', 'string', 'max:40'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items' => ['required', 'array', 'min:1'],
            // Nur Schlüssel, die der Nutzer sehen darf — die Whitelist kommt
            // aus derselben Quelle wie die Menüanpassung.
            'items.*' => ['string', 'max:160', Rule::in(app(NavigationRegistry::class)->selectableKeys())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'items.required' => (string) __('scope.workspace.error.no_items'),
            'items.min' => (string) __('scope.workspace.error.no_items'),
            'items.*.in' => (string) __('scope.workspace.error.unknown_item'),
        ];
    }

    /**
     * Doppelte Schlüssel entfernen, Reihenfolge der Auswahl beibehalten —
     * die Reihenfolge ist die Aussage des Editors.
     */
    protected function prepareForValidation(): void {
        $items = $this->input('items');
        if (is_array($items)) {
            $this->merge([
                'items' => array_values(array_unique(array_map(
                    static fn ($value): string => is_scalar($value) ? (string) $value : '',
                    $items,
                ))),
            ]);
        }
    }
}
