<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Document, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory {
    protected $model = Document::class;

    public function definition(): array {
        return [
            'documentable_type' => null,
            'documentable_id' => null,
            'title' => fake()->sentence(3),
            'document_type' => DocumentType::Other->value,
            'status' => DocumentStatus::Active->value,
            'valid_from' => null,
            'valid_until' => null,
            'description' => null,
            'created_by_user_id' => User::factory(),
            'current_version_id' => null,
        ];
    }

    public function certificate(): self {
        return $this->state(fn() => [
            'document_type' => DocumentType::Certificate->value,
        ]);
    }

    public function expired(): self {
        return $this->state(fn() => [
            'valid_until' => now()->subDays(10)->toDateString(),
        ]);
    }

    public function expiringInDays(int $days): self {
        return $this->state(fn() => [
            'valid_until' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function archived(): self {
        return $this->state(fn() => [
            'status' => DocumentStatus::Archived->value,
        ]);
    }

    /** Personalakten-Dokument eines Mitglieds (Feature 141): vertraulich, HR-Kategorie. */
    public function personnelFile(User $member, \App\Enums\Hr\HrDocumentCategory $category = \App\Enums\Hr\HrDocumentCategory::Contract): self {
        return $this->state(fn() => [
            'organization_id' => $member->organization_id,
            'documentable_type' => User::class,
            'documentable_id' => $member->id,
            'document_type' => $category->documentType()->value,
            'confidential' => true,
            'hr_category' => $category->value,
        ]);
    }

    /** Fürs Kundenportal freigegeben (Welle D — Dokument-Spiegelung). */
    public function releasedToCustomer(): self {
        return $this->state(fn() => [
            'customer_visible' => true,
            'customer_released_at' => now(),
        ]);
    }
}
