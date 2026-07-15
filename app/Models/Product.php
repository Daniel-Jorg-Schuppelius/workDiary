<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Product.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Product\ProductStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, HasTags};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\DB;

/**
 * Produkt = Typ-Ebene Hersteller-Modell (produktmodell-konzept.md, MVP-369):
 * bündelt Artikel (Handelssicht) und Assets (Instanzen im Feld) desselben
 * Produkts. Typ-Tags (HasTags) liegen hier; die Klassifikation
 * `product_group` wird über den nullable FK am Typ verankert.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $manufacturer
 * @property string $model
 * @property string $name
 * @property int|null $product_group_classification_id
 * @property string|null $notes
 * @property ProductStatus $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Article> $articles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Asset> $assets
 */
class Product extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;
    use HasTags;

    protected $fillable = [
        'organization_id',
        'manufacturer',
        'model',
        'name',
        'product_group_classification_id',
        'notes',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ProductStatus::class,
    ];

    protected static function booted(): void {
        static::saving(function (self $product): void {
            $product->manufacturer = trim($product->manufacturer);
            $product->model = trim($product->model);
            if (trim((string) $product->name) === '') {
                $product->name = trim($product->manufacturer . ' ' . $product->model);
            }
        });
    }

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany {
        return $this->hasMany(Article::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany {
        return $this->hasMany(Asset::class);
    }

    /** @return BelongsTo<Classification, $this> */
    public function productGroupClassification(): BelongsTo {
        return $this->belongsTo(Classification::class, 'product_group_classification_id');
    }

    /**
     * Backfill (MVP-369, aus der Migration aufgerufen): legt je Organisation
     * distinct getrimmte, case-insensitiv deduplizierte (manufacturer, model)-
     * Paare aus `assets` als Produkte an (erste Schreibweise gewinnt) und
     * typisiert die Assets. Idempotent — bereits typisierte Assets und
     * vorhandene Produkte bleiben unangetastet.
     *
     * Bewusst Query-Builder statt Eloquent: läuft im Migrations-Kontext ohne
     * Org-Bindung (OrganizationScope) und ohne Audit-Rauschen.
     *
     * @return int Anzahl neu angelegter Produkte
     */
    public static function backfillFromAssets(): int {
        $created = 0;

        $assets = DB::table('assets')
            ->whereNull('product_id')
            ->whereNotNull('organization_id')
            ->whereRaw("TRIM(COALESCE(manufacturer, '')) != ''")
            ->whereRaw("TRIM(COALESCE(model, '')) != ''")
            ->get(['id', 'organization_id', 'manufacturer', 'model']);

        /** @var array<string, int> $productIds Schlüssel: org|manufacturer|model (lowercase) */
        $productIds = [];

        foreach ($assets as $asset) {
            $manufacturer = trim((string) $asset->manufacturer);
            $model = trim((string) $asset->model);
            $key = $asset->organization_id . '|' . mb_strtolower($manufacturer) . '|' . mb_strtolower($model);

            if (! array_key_exists($key, $productIds)) {
                $existing = DB::table('products')
                    ->where('organization_id', $asset->organization_id)
                    ->whereRaw('LOWER(manufacturer) = ?', [mb_strtolower($manufacturer)])
                    ->whereRaw('LOWER(model) = ?', [mb_strtolower($model)])
                    ->value('id');

                if ($existing === null) {
                    $existing = DB::table('products')->insertGetId([
                        'organization_id' => $asset->organization_id,
                        'manufacturer' => $manufacturer,
                        'model' => $model,
                        'name' => trim($manufacturer . ' ' . $model),
                        'status' => ProductStatus::Active->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }

                $productIds[$key] = (int) $existing;
            }

            DB::table('assets')->where('id', $asset->id)->update(['product_id' => $productIds[$key]]);
        }

        return $created;
    }
}
