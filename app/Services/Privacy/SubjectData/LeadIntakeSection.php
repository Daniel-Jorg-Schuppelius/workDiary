<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Fields\FieldExtensionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Aufnahmeakten des Leads bzw. Kunden (Feature 200, MVP-1643): je Fassung
 * Stufe, Entscheidung, Pakete, Aufbewahrung mit Rechtsgrundlage (Art. 6
 * Abs. 1 lit. c DSGVO i. V. m. der Regel des Pakets, etwa § 8 Abs. 4 GwG)
 * und die erfassten Angaben. Ausweis- und Gesundheitsangaben erscheinen nur,
 * wenn die Person, die die Auskunft erstellt, ihr eigenes Recht hat — sonst
 * steht dort, dass solche Angaben vorhanden sind und wer sie ausgeben darf.
 */
class LeadIntakeSection extends AbstractSubjectSection {
    public function key(): string {
        return 'lead_intakes';
    }

    public function title(): string {
        return (string) __('lead_intake.dsar.title');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $query = LeadIntake::query()->withoutGlobalScopes()->where('organization_id', $subject->getAttribute('organization_id'));
        if ($subject instanceof Lead) {
            $query->where('lead_id', $subject->id);
        } else {
            $this->expect($subject, Customer::class);
            $query->where(static fn ($q) => $q->where('customer_id', $subject->getKey())
                ->orWhereIn('lead_id', Lead::query()->withoutGlobalScopes()->where('customer_id', $subject->getKey())->select('id')));
        }
        $viewer = Auth::user();
        $topic = (string) __('lead_intake.dsar.column.topic');
        $detail = (string) __('lead_intake.dsar.column.value');

        $lists = [];
        foreach ($query->orderBy('revision')->orderBy('id')->get() as $intake) {
            $rows = [];
            $packages = $intake->packageSnapshots();
            $bases = array_values(array_filter(array_map(static fn ($p): ?string => $p->retention?->basis, $packages)));
            foreach ([
                __('lead_intake.field.stage') => $intake->stage->label(),
                __('lead_intake.field.decision') => $intake->decision?->label(),
                __('lead_intake.field.decided_at') => $this->str($intake->decided_at),
                __('lead_intake.field.packages') => implode(', ', array_map(static fn ($p): string => $p->label, $packages)),
                __('lead_intake.field.retention_until') => $intake->retention_until?->format('Y-m-d') ?? ($bases !== [] ? (string) __('lead_intake.retention.open') : null),
                __('lead_intake.dsar.legal_basis') => $bases !== [] ? (string) __('lead_intake.dsar.legal_basis_value', ['basis' => implode(', ', array_unique($bases))]) : null,
            ] as $label => $value) {
                $rows[] = [$topic => (string) $label, $detail => $this->str($value)];
            }

            $values = $intake->fieldDocumentValues();
            foreach ($intake->fullSchema() as $field) {
                if (! $field->type->hasValue() || $values->isEmpty($field)) {
                    continue;
                }
                $restriction = $intake->restrictionFor($field->key, $viewer instanceof User ? $viewer : null);
                $rows[] = [$topic => $field->label, $detail => $restriction !== null
                    ? (string) __('lead_intake.dsar.restricted', ['right' => $restriction])
                    : $values->display($field, app(FieldExtensionRegistry::class))];
            }
            $lists[(string) __('lead_intake.dsar.list_title', ['revision' => $intake->revision])] = $rows;
        }

        return ['lists' => $lists];
    }
}
