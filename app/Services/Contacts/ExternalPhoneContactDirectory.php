<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, Organization};
use CommonToolkit\Helper\Data\PhoneNumberHelper;
use Illuminate\Support\Facades\Log;

/**
 * Aggregiert aktiv verbundene Kontaktquellen für einen Import-Lauf.
 *
 * Eine externe Rufnummer darf nur dann automatisch buchen, wenn sämtliche
 * Treffer auf genau dasselbe lokale Kunden-Ziel verknüpft sind. Unverknüpfte
 * oder widersprüchliche Treffer bleiben reine Hinweise für die Inbox.
 */
final class ExternalPhoneContactDirectory {
    /** @var list<ExternalPhoneContactSource> */
    private array $sources;

    /** @var array<int, array<string, list<ExternalPhoneContact>>> */
    private array $indexes = [];

    /** @param iterable<ExternalPhoneContactSource> $sources */
    public function __construct(iterable $sources) {
        $this->sources = array_values([...$sources]);
    }

    /** @return list<string> */
    public function availableSourceLabels(Organization $organization): array {
        $labels = [];
        foreach ($this->sources as $source) {
            try {
                if ($source->isAvailable($organization)) {
                    $labels[] = $source->label();
                }
            } catch (\Throwable $e) {
                $this->logFailure($source, $e);
            }
        }

        return array_values(array_unique($labels));
    }

    public function find(Organization $organization, string $phone): ?ExternalPhoneContactMatch {
        $e164 = PhoneNumberHelper::toE164($phone, 'DE');
        if ($e164 === null || $e164 === '') {
            return null;
        }

        $matches = $this->index($organization)[$e164] ?? [];
        if ($matches === []) {
            return null;
        }

        $targets = [];
        $allLinked = true;
        foreach ($matches as $contact) {
            $target = $this->resolveTarget($organization, $contact);
            if (! $target instanceof Customer && ! $target instanceof ForeignCustomer) {
                $allLinked = false;

                continue;
            }
            $targets[$target->getMorphClass() . ':' . $target->getKey()] = $target;
        }

        $names = array_values(array_unique(array_filter(array_map(
            static fn (ExternalPhoneContact $contact): string => trim((string) ($contact->name ?: $contact->company)),
            $matches,
        ))));
        $sources = array_values(array_unique(array_map(
            static fn (ExternalPhoneContact $contact): string => $contact->providerLabel,
            $matches,
        )));
        $target = $allLinked && count($targets) === 1 ? reset($targets) : null;
        $ambiguous = count($matches) > 1 && (count($names) > 1 || count($targets) > 1 || ! $allLinked);

        return new ExternalPhoneContactMatch(
            target: $target instanceof Customer || $target instanceof ForeignCustomer ? $target : null,
            displayName: count($names) === 1 ? $names[0] : null,
            sourceLabels: $sources,
            ambiguous: $ambiguous,
        );
    }

    /** @return array<string, list<ExternalPhoneContact>> */
    private function index(Organization $organization): array {
        if (isset($this->indexes[$organization->id])) {
            return $this->indexes[$organization->id];
        }

        $index = [];
        $seen = [];
        foreach ($this->sources as $source) {
            try {
                if (! $source->isAvailable($organization)) {
                    continue;
                }
                foreach ($source->contacts($organization) as $contact) {
                    $contactKey = $contact->providerId . '|' . $contact->externalId;
                    foreach ($contact->phones as $phone) {
                        $e164 = PhoneNumberHelper::toE164($phone, 'DE');
                        if ($e164 === null || $e164 === '' || isset($seen[$e164][$contactKey])) {
                            continue;
                        }
                        $seen[$e164][$contactKey] = true;
                        $index[$e164][] = $contact;
                    }
                }
            } catch (\Throwable $e) {
                // Ein ausgefallenes Fremdsystem darf den Anrufimport niemals
                // blockieren; die lokale Zuordnung und übrige Quellen laufen weiter.
                $this->logFailure($source, $e);
            }
        }

        return $this->indexes[$organization->id] = $index;
    }

    private function resolveTarget(Organization $organization, ExternalPhoneContact $contact): Customer|ForeignCustomer|null {
        $targets = ExternalReference::query()
            ->forPlugin($organization, $contact->providerId, 'contact')
            ->forExternalId($contact->externalId)
            ->with('referenceable')
            ->get()
            ->map(static fn (ExternalReference $reference) => $reference->referenceable)
            ->filter(static fn ($target): bool => ($target instanceof Customer || $target instanceof ForeignCustomer)
                && (int) $target->organization_id === (int) $organization->id)
            ->keyBy(static fn (Customer|ForeignCustomer $target): string => $target->getMorphClass() . ':' . $target->getKey());

        $aliasTarget = ExternalReferenceAlias::resolveModel(
            $organization->id,
            $contact->providerId,
            'contact',
            $contact->externalId,
        );
        if (($aliasTarget instanceof Customer || $aliasTarget instanceof ForeignCustomer)
            && (int) $aliasTarget->organization_id === (int) $organization->id) {
            $targets->put($aliasTarget->getMorphClass() . ':' . $aliasTarget->getKey(), $aliasTarget);
        }

        $target = $targets->count() === 1 ? $targets->first() : null;

        return $target instanceof Customer || $target instanceof ForeignCustomer ? $target : null;
    }

    private function logFailure(ExternalPhoneContactSource $source, \Throwable $e): void {
        // Keine Kontakt-/Rufnummerndaten loggen: nur Quelle und Fehlerklasse.
        Log::warning('external phone contact source failed', [
            'source' => $source->id(),
            'class' => class_basename($e),
        ]);
    }
}
