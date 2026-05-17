<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\Customer;
use App\Models\ExternalReference;
use App\Models\TimeEntry;
use App\Plugins\Contracts\ContactSyncer;
use App\Plugins\Contracts\Plugin;
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Contracts\TimeExporter;
use Carbon\CarbonImmutable;

/**
 * Lexoffice integration plugin.
 *
 * - Pushes workDiary customers as Lexoffice contacts (ContactSyncer)
 * - Aggregates billable time per customer + period and creates a Lexoffice
 *   sales invoice voucher representing the service transaction (TimeExporter)
 *
 * Mappings between local entities and Lexoffice ids are persisted in the
 * external_references table. The plugin id is "lexoffice".
 */
class LexofficePlugin implements ContactSyncer, Plugin, TimeExporter
{
    public const ID = 'lexoffice';

    public const EXT_TYPE_CONTACT = 'contact';

    public const EXT_TYPE_VOUCHER = 'voucher';

    public function __construct(
        private readonly LexofficeService $service,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function name(): string
    {
        return 'Lexoffice';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function description(): string
    {
        return __('Synchronisiert Kunden mit Lexoffice und überträgt erfasste Zeiten als Beleg.');
    }

    public function isEnabled(): bool
    {
        return (bool) config('plugins.lexoffice.enabled') && $this->service->isConfigured();
    }

    public function capabilities(): array
    {
        return [
            PluginCapability::CONTACT_SYNC,
            PluginCapability::TIME_EXPORT,
        ];
    }

    public function adminPanel(): ?array
    {
        return null; // Settings live in .env / config/plugins.php for now.
    }

    public function pushContact(Customer $customer): string
    {
        $externalId = $this->service->createContact($customer);

        ExternalReference::updateOrCreate(
            [
                'plugin_id' => self::ID,
                'external_type' => self::EXT_TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $customer->organization_id,
                'external_id' => $externalId,
                'synced_at' => now(),
            ],
        );

        return $externalId;
    }

    public function exportCustomerTime(Customer $customer, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $entries = TimeEntry::query()
            ->whereHas('project', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('billable', true)
            ->where('exported', false)
            ->with('project')
            ->get();

        if ($entries->isEmpty()) {
            return [
                'external_id' => '',
                'external_type' => self::EXT_TYPE_VOUCHER,
                'payload' => ['skipped' => true, 'reason' => 'no billable entries'],
            ];
        }

        $contactRef = ExternalReference::query()
            ->where('plugin_id', self::ID)
            ->where('external_type', self::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first();

        $contactExternalId = $contactRef !== null ? $contactRef->external_id : $this->pushContact($customer);

        $result = $this->service->createTimeVoucher($customer, $entries, $from, $to, $contactExternalId);

        $voucherRef = ExternalReference::create([
            'organization_id' => $customer->organization_id,
            'plugin_id' => self::ID,
            'external_type' => self::EXT_TYPE_VOUCHER,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => $result['external_id'],
            'payload' => $result['payload'],
            'synced_at' => now(),
        ]);

        // Mark exported so the same entries are not transmitted twice.
        TimeEntry::query()
            ->whereIn('id', $entries->pluck('id'))
            ->update(['exported' => true]);

        return [
            'external_id' => $voucherRef->external_id,
            'external_type' => self::EXT_TYPE_VOUCHER,
            'payload' => $result['payload'],
        ];
    }
}
