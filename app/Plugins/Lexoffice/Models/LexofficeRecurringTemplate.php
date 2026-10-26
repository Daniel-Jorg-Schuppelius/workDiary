<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRecurringTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Customer\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Lexoffice\Enums\{ExecutionInterval, ExecutionStatus};

/**
 * Gespiegelte Serienvorlage aus Lexoffice (Feature 185, MVP-1409): der Plan,
 * was dort wiederkehrend berechnet wird. Nur lesend gespiegelt
 * (`GET /v1/recurring-templates`), die Positionen aus dem Einzelabruf.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $external_id
 * @property string|null $contact_external_id
 * @property string|null $contact_name
 * @property int|null $customer_id
 * @property string|null $title
 * @property ExecutionStatus $execution_status
 * @property ExecutionInterval|null $execution_interval
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CarbonImmutable|null $next_execution_on
 * @property CarbonImmutable|null $last_execution_on
 * @property bool $is_finalized
 * @property bool $is_last_execution_failed
 * @property string|null $last_execution_error
 * @property string|null $total_net
 * @property string $currency
 * @property CarbonImmutable|null $remote_updated_at
 * @property CarbonImmutable|null $lines_synced_at
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $synced_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LexofficeRecurringTemplateLine> $lines
 * @property-read Customer|null $customer
 */
class LexofficeRecurringTemplate extends Model {
    use BelongsToOrganization;

    protected $table = 'lexoffice_recurring_templates';

    protected $fillable = [
        'organization_id',
        'external_id',
        'contact_external_id',
        'contact_name',
        'customer_id',
        'title',
        'execution_status',
        'execution_interval',
        'starts_on',
        'ends_on',
        'next_execution_on',
        'last_execution_on',
        'is_finalized',
        'is_last_execution_failed',
        'last_execution_error',
        'total_net',
        'currency',
        'remote_updated_at',
        'lines_synced_at',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'execution_status' => ExecutionStatus::class,
        'execution_interval' => ExecutionInterval::class,
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'next_execution_on' => 'immutable_date',
        'last_execution_on' => 'immutable_date',
        'is_finalized' => 'boolean',
        'is_last_execution_failed' => 'boolean',
        'remote_updated_at' => 'immutable_datetime',
        'lines_synced_at' => 'immutable_datetime',
        'payload' => 'array',
        'synced_at' => 'immutable_datetime',
    ];

    /** @return HasMany<LexofficeRecurringTemplateLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(LexofficeRecurringTemplateLine::class, 'template_id')->orderBy('position');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** Bearbeitungsseite in Lexoffice — eine Ansichtsseite für Vorlagen gibt es laut API-Doku nicht. */
    public function lexofficePermalink(): string {
        return 'https://app.lexoffice.de/permalink/recurring-templates/edit/' . $this->external_id;
    }
}
