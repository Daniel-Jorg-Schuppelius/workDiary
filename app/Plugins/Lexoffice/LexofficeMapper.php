<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\Customer;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Translates workDiary domain models into Lexoffice JSON payloads.
 *
 * The payload structures follow the public Lexoffice REST API:
 *   https://developers.lexoffice.io/docs/
 *
 * They are intentionally produced as plain associative arrays so they can be
 * fed into Lexoffice entity classes via fromJson(json_encode($payload)) which
 * is the conventional construction pattern in the Lexoffice SDK.
 */
class LexofficeMapper
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function customerToContactPayload(Customer $customer, array $defaults = []): array
    {
        $isCompany = (bool) ($customer->company || $customer->vat_id);

        $billingAddress = [];
        $hasStructured = $customer->address_street || $customer->address_zip || $customer->address_city;
        if ($hasStructured) {
            $billingAddress = array_filter([
                'street' => $customer->address_street,
                'zip' => $customer->address_zip,
                'city' => $customer->address_city,
                'countryCode' => $customer->country ?: ($defaults['country'] ?? 'DE'),
            ]);
        } elseif ($customer->address) {
            $billingAddress = [
                'street' => $customer->address,
                'countryCode' => $customer->country ?: ($defaults['country'] ?? 'DE'),
            ];
        }

        $payload = [
            'version' => 0,
            'roles' => [
                'customer' => (object) [],
            ],
            'note' => $customer->comment ?: null,
        ];

        if ($isCompany) {
            $contactPersons = $this->buildContactPersons($customer);
            $payload['company'] = array_filter([
                'name' => $customer->company ?: $customer->name,
                'taxNumber' => $customer->vat_id,
                'vatRegistrationId' => $customer->vat_id,
                'contactPersons' => $contactPersons !== [] ? $contactPersons : null,
            ]);
        } else {
            $parts = preg_split('/\s+/', trim((string) $customer->name), 2) ?: [];
            $payload['person'] = array_filter([
                'firstName' => $parts[0] ?? null,
                'lastName' => $parts[1] ?? ($parts[0] ?? $customer->name),
            ]);
        }

        if ($billingAddress) {
            $payload['addresses'] = [
                'billing' => [$billingAddress],
            ];
        }

        if ($customer->email || $customer->phone || $customer->mobile) {
            $payload['emailAddresses'] = $customer->email
                ? ['business' => [$customer->email]]
                : null;
            $payload['phoneNumbers'] = array_filter([
                'business' => $customer->phone ? [$customer->phone] : null,
                'mobile' => $customer->mobile ? [$customer->mobile] : null,
            ]);
        }

        return array_filter($payload, static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Baut die contactPersons-Liste aus den strukturierten Kontaktpersonen
     * des Kunden, fällt auf das Legacy-Einzelfeld contact_name zurück.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildContactPersons(Customer $customer): array
    {
        $persons = $customer->contact_persons ?? [];
        $list = [];

        foreach ($persons as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '' && empty($p['email']) && empty($p['phone'])) {
                continue;
            }
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $list[] = array_filter([
                'salutation' => null,
                'firstName' => isset($parts[1]) ? $parts[0] : null,
                'lastName' => $parts[1] ?? ($parts[0] ?? $name),
                'emailAddress' => $p['email'] ?? null,
                'phoneNumber' => $p['phone'] ?? null,
                'primary' => (bool) ($p['primary'] ?? false),
            ], static fn ($v) => $v !== null && $v !== '');
        }

        if ($list === [] && $customer->contact_name) {
            $list[] = array_filter([
                'lastName' => $customer->contact_name,
                'emailAddress' => $customer->email,
                'phoneNumber' => $customer->phone ?: $customer->mobile,
                'primary' => true,
            ]);
        }

        return $list;
    }

    /**
     * Build a Lexoffice voucher payload (type=salesinvoice) summarising all
     * billable time entries of the given customer in [$from, $to], grouped
     * by project. Each project becomes one voucher item.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function timeEntriesToVoucherPayload(
        Customer $customer,
        Collection $entries,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $defaults = [],
    ): array {
        $taxType = $defaults['default_tax_type'] ?? 'net';
        $vatRate = (float) ($defaults['default_vat_rate'] ?? 19.0);
        $currency = $customer->currency ?: ($defaults['default_currency'] ?? 'EUR');

        $items = $entries
            ->groupBy(fn (TimeEntry $e) => ($e->project_id ?? 0).'|'.((string) ($e->kind ?? '')))
            ->map(function (Collection $group) use ($vatRate, $from, $to) {
                /** @var TimeEntry $first */
                $first = $group->first();
                $project = $first->project;
                $kind = $first->kind;
                $projectName = $project !== null ? $project->name : (string) __('Leistung');
                $hours = round($group->sum('minutes') / 60.0, 2);
                $revenue = round((float) $group->sum('rate'), 2);
                $unitPrice = $hours > 0 ? round($revenue / $hours, 2) : 0.0;

                $rule = $project?->resolveBillingRule($kind);

                $type = $rule?->item_type ?: 'service';
                $unitName = $rule?->unit_name ?: 'Stunde';
                $taxRate = $rule?->vat_rate !== null ? (float) $rule->vat_rate : $vatRate;
                $netAmount = $rule?->net_unit_price !== null ? (float) $rule->net_unit_price : $unitPrice;

                $kindSuffix = $kind ? ' ['.$kind.']' : '';
                $name = sprintf('%s%s (%s – %s)', $projectName, $kindSuffix, $from->format('d.m.Y'), $to->format('d.m.Y'));

                $item = [
                    'type' => $type,
                    'name' => $name,
                    'quantity' => $hours,
                    'unitName' => $unitName,
                    'unitPrice' => [
                        'currency' => 'EUR',
                        'netAmount' => $netAmount,
                        'taxRatePercentage' => $taxRate,
                    ],
                ];

                if ($rule?->lexoffice_article_id) {
                    $item['id'] = $rule->lexoffice_article_id;
                }

                return $item;
            })->values()->all();

        return [
            'voucherType' => 'salesinvoice',
            'voucherNumber' => null,
            'voucherDate' => $to->format('Y-m-d').'T00:00:00.000+01:00',
            'totalGrossAmount' => null,
            'totalTaxAmount' => null,
            'taxType' => $taxType,
            'useCollectiveContact' => false,
            'contactId' => $defaults['external_contact_id'] ?? null,
            'remark' => sprintf('Zeiterfassung %s – %s', $from->format('d.m.Y'), $to->format('d.m.Y')),
            'voucherItems' => $items,
            'currency' => $currency,
        ];
    }
}
