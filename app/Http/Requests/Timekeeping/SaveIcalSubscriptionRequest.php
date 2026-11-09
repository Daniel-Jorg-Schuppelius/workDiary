<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveIcalSubscriptionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Timekeeping;

use App\Enums\Import\ImportEntity;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Time\IcalSubscription;
use App\Rules\ExistsInCurrentOrganization;
use App\Support\UrlSafety;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Validation\{Rule, Validator};

/**
 * Kalender-Abo anlegen oder ändern (MVP-1516). `webcal://` ist die übliche
 * Abo-Form und wird als https gelesen; private Ziele nur mit ausdrücklicher
 * Freigabe (Laufzeitprüfung samt DNS im Abruf).
 */
class SaveIcalSubscriptionRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => \App\Models\Platform\User::class,
    ];

    protected function prepareForValidation(): void {
        $url = StringHelper::trimToNull((string) $this->input('url', ''));
        if ($url !== null && str_starts_with(strtolower($url), 'webcal://')) {
            $url = 'https://' . substr($url, 9);
        }
        $categories = array_values(array_filter(array_map('trim', explode(',', (string) $this->input('category_allowlist', ''))), static fn (string $c): bool => $c !== ''));

        $this->merge([
            'url' => $url,
            'category_allowlist' => $categories !== [] ? $categories : null,
            'user_id' => $this->input('user_id') === '' ? null : $this->input('user_id'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $editing = $this->route('icalSubscription') instanceof IcalSubscription;

        return [
            'name' => ['required', 'string', 'max:120'],
            'import_entity' => ['required', Rule::enum(ImportEntity::class)->only([ImportEntity::ProjectTimes, ImportEntity::Attendances])],
            'url' => [$editing ? 'nullable' : 'required', 'string', 'max:2048', 'url:http,https'],
            'user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization()],
            'category_allowlist' => ['nullable', 'array', 'max:20'],
            'category_allowlist.*' => ['string', 'max:60'],
            'allow_private_network' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $v): void {
            $url = $this->input('url');
            if ($v->errors()->has('url') || ! is_string($url) || $this->boolean('allow_private_network')) {
                return;
            }
            if (! UrlSafety::isAcceptableExternalHttpUrl($url)) {
                $v->errors()->add('url', (string) __('import.ical_subscription.error.private'));
            }
        });
    }
}
