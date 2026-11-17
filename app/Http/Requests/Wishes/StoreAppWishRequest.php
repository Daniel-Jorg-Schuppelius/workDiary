<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreAppWishRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Wishes;

use App\Enums\Wishes\AppWishKind;
use App\Http\Requests\BaseFormRequest;
use App\Services\Wishes\AppWishService;
use Illuminate\Validation\Rule;

/**
 * Wunsch äußern oder ändern (Feature 206, MVP-1658). Die Seite kommt als
 * Routenname aus der Kopfleiste; einen unbekannten Namen verwirft der Dienst.
 */
class StoreAppWishRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'kind' => ['required', Rule::enum(AppWishKind::class)],
            'body' => ['required', 'string', 'min:3', 'max:' . AppWishService::MAX_BODY],
            'route_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array {
        return [
            'kind' => (string) __('wishes.field.kind'),
            'body' => (string) __('wishes.field.body'),
            'route_name' => (string) __('wishes.field.page'),
        ];
    }
}
