<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleReportProductRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Reselling;

use App\Enums\Reselling\ResaleArticleRole;
use App\Http\Requests\BaseFormRequest;
use App\Models\Platform\Organization;
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use Illuminate\Validation\Rule;

/**
 * Produkt-Einstufung eines Katalogartikels (Feature 152, MVP-1025): `article`
 * ist der Formularschlüssel der Katalogquelle; Rolle „auto" = Übersteuerung
 * löschen.
 */
class ResaleReportProductRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            // Katalogartikel als Formularschlüssel (`art:<sqid>`, `lex:<sqid>`, MVP-1025).
            'article' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->catalogArticle() === null) {
                    $fail((string) __('resale.products.flash.missing'));
                }
            }],
            'role' => ['nullable', 'string', Rule::in(array_merge(['auto'], array_map(static fn(ResaleArticleRole $r): string => $r->value, ResaleArticleRole::cases())))],
        ];
    }

    public function catalogArticle(): ?CatalogArticle {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $value = $this->input('article');

        return $organization instanceof Organization && is_string($value) && $value !== ''
            ? app(ArticleCatalog::class)->fromFormKey((int) $organization->id, $value)
            : null;
    }

    public function role(): ?ResaleArticleRole {
        return ResaleArticleRole::tryFrom((string) ($this->validated('role') ?? ''));
    }
}
