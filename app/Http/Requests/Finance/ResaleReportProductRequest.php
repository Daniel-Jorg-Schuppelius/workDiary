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

namespace App\Http\Requests\Finance;

use App\Enums\Reselling\ResaleArticleRole;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\LexofficeArticle;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Produkt-Einstufung eines Lexoffice-Artikels (Feature 152): Artikel als
 * Sqid, Rolle „auto" = Übersteuerung löschen.
 */
class ResaleReportProductRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'article_id' => LexofficeArticle::class,
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'article_id' => ['required', 'integer', new ExistsInCurrentOrganization('lexoffice_articles')],
            'role' => ['nullable', 'string', Rule::in(array_merge(['auto'], array_map(static fn(ResaleArticleRole $r): string => $r->value, ResaleArticleRole::cases())))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array {
        return [
            'article_id.required' => (string) __('resale.products.flash.missing'),
            'article_id.integer' => (string) __('resale.products.flash.missing'),
        ];
    }

    public function article(): LexofficeArticle {
        return LexofficeArticle::query()->findOrFail((int) $this->validated('article_id'));
    }

    public function role(): ?ResaleArticleRole {
        return ResaleArticleRole::tryFrom((string) ($this->validated('role') ?? ''));
    }
}
