<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Supplier;

use App\Enums\Procurement\{CatalogSourceFormat, PunchoutProtocol};
use App\Models\Concerns\BelongsToOrganization;
use App\Services\Procurement\OpenMasterdata\OpenMasterdataConfig;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Lieferanten-Katalogquelle (Feature 050, MVP-091): Quelle/Format/Encoding,
 * Importprotokoll-Eckdaten sowie optionaler Remote-Abruf (HTTP/FTP) mit
 * verschlüsselt gespeicherten Zugangsdaten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_id
 * @property string $name
 * @property CatalogSourceFormat $format
 * @property string $source_type
 * @property string $encoding
 * @property string $delimiter
 * @property bool $has_header
 * @property string|null $sheet_name
 * @property string $decimal_separator
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $last_imported_at
 * @property string|null $last_file_hash
 * @property string|null $remote_url
 * @property string|null $remote_host
 * @property int|null $remote_port
 * @property string|null $remote_path
 * @property string|null $remote_username
 * @property string|null $remote_password
 * @property string|null $remote_host_fingerprint
 * @property array<string, string>|null $mapping
 * @property int|null $fetch_interval_minutes
 * @property \Illuminate\Support\Carbon|null $next_fetch_at
 */
class SupplierCatalogSource extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'punchout_protocol' => 'oci',
    ];

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'name',
        'format',
        'source_type',
        'encoding',
        'delimiter',
        'has_header',
        'sheet_name',
        'expected_customer_no',
        'decimal_separator',
        'active',
        'last_imported_at',
        'last_file_hash',
        'remote_url',
        'remote_host',
        'remote_port',
        'remote_path',
        'remote_username',
        'remote_password',
        'remote_host_fingerprint',
        'punchout_url',
        'punchout_username',
        'punchout_password',
        'punchout_protocol',
        'punchout_customer_number',
        'omd_config',
        'mapping',
        'fetch_interval_minutes',
        'next_fetch_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'format' => CatalogSourceFormat::class,
        'has_header' => 'boolean',
        'active' => 'boolean',
        'last_imported_at' => 'datetime',
        'remote_port' => 'integer',
        'remote_password' => 'encrypted', // verschlüsselt at-rest (APP_KEY)
        'punchout_password' => 'encrypted', // verschlüsselt at-rest (APP_KEY)
        'punchout_protocol' => PunchoutProtocol::class,
        'omd_config' => 'encrypted:array', // Zugangsdaten zum Großhändler-Webservice (MVP-1072)
        'mapping' => 'array',
        'fetch_interval_minutes' => 'integer',
        'next_fetch_at' => 'datetime',
    ];

    /** Hat die Quelle einen automatischen Abrufweg (kein manueller Upload)? */
    public function hasRemoteFetch(): bool {
        return in_array($this->source_type, ['http', 'ftp', 'sftp'], true);
    }

    /** Open-Masterdata-Quelle mit vollständigem Zugang (MVP-1072)? */
    public function hasOpenMasterdata(): bool {
        return $this->format === CatalogSourceFormat::OpenMasterdata && OpenMasterdataConfig::fromSource($this) !== null;
    }

    /** Ist ein aktiver Shop-Absprung (OCI, IDS-Connect) konfiguriert (MVP-096, MVP-1071)? */
    public function hasPunchout(): bool {
        return trim((string) $this->punchout_url) !== '';
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<SupplierCatalogItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(SupplierCatalogItem::class);
    }

    /** @return HasMany<SupplierCatalogImport, $this> */
    public function imports(): HasMany {
        return $this->hasMany(SupplierCatalogImport::class)->latest('id');
    }
}
