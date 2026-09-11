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
use App\Models\{Article, LexofficeArticle};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Produkt-Einstufung eines Artikels (Feature 152): `article_type` wählt
 * Lexoffice-Artikel (Default) oder lokalen Artikel, `article_id` ist die
 * Sqid des jeweiligen Modells; Rolle „auto" = Übersteuerung löschen.
 */
class ResaleReportProductRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    public const TYPE_LOCAL = 'local';

    public const TYPE_LEXOFFICE = 'lexoffice';

    /** @var array<string, class-string> */
    protected array $sqidFields = [];

    /** @return array<string, class-string> */
    protected function sqidFields(): array {
        return ['article_id' => $this->isLocal() ? Article::class : LexofficeArticle::class];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'article_type' => ['nullable', 'string', Rule::in([self::TYPE_LOCAL, self::TYPE_LEXOFFICE])],
            'article_id' => ['required', 'integer', new ExistsInCurrentOrganization($this->isLocal() ? 'articles' : 'lexoffice_articles')],
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

    public function isLocal(): bool {
        return $this->input('article_type') === self::TYPE_LOCAL;
    }

    public function article(): LexofficeArticle|Article {
        $id = (int) $this->validated('article_id');

        return $this->isLocal() ? Article::query()->findOrFail($id) : LexofficeArticle::query()->findOrFail($id);
    }

    public function role(): ?ResaleArticleRole {
        return ResaleArticleRole::tryFrom((string) ($this->validated('role') ?? ''));
    }
}
