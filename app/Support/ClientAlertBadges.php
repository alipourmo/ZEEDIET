<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PregnancyStatus;
use App\Models\Client;
use App\Models\ClientMedical;
use Illuminate\Support\Facades\DB;

/**
 * The alert badges for a client card (spec section 7.7): allergy yes/no, contraindication keys, pregnancy status.
 *
 * The allergy text never leaves the database. medicalSelect() turns it into a yes/no in SQL, so list pages and
 * secretary screens load no free-text medical field. Free text is shown only on the medical record (medical.view).
 */
final readonly class ClientAlertBadges
{
    /**
     * @param  list<string>  $contraindications  Keys from config/clinic.php
     */
    public function __construct(
        public bool $hasAllergy,
        public array $contraindications,
        public PregnancyStatus $pregnancy,
    ) {}

    /**
     * Columns to select from client_medical when loading badges. Use it in eager loads, e.g.
     * `Client::with(['medical' => fn ($q) => $q->select(ClientAlertBadges::medicalSelect())])`.
     *
     * @return list<mixed>
     */
    public static function medicalSelect(): array
    {
        return [
            'id',
            'client_id',
            'device_contraindications',
            DB::raw("(food_allergies IS NOT NULL AND TRIM(food_allergies) <> '') AS has_allergy"),
        ];
    }

    /**
     * Uses the medical row already loaded with medicalSelect(), or loads it. A full medical row loaded elsewhere is
     * reloaded with the restricted select, so no free text is kept on the model for the badges.
     */
    public static function fromClient(Client $client): self
    {
        $medical = $client->medical;

        if ($medical === null || ! array_key_exists('has_allergy', $medical->getAttributes())) {
            $client->load(['medical' => fn ($query) => $query->select(self::medicalSelect())]);
            $medical = $client->medical;
        }

        /** @var PregnancyStatus $pregnancy the enum cast on Client */
        $pregnancy = $client->pregnancy_status;

        /** @var array<mixed> $keys */
        $keys = $medical instanceof ClientMedical ? (array) $medical->device_contraindications : [];

        return new self(
            hasAllergy: (bool) $medical?->getAttribute('has_allergy'),
            contraindications: array_values(array_map('strval', $keys)),
            pregnancy: $pregnancy,
        );
    }
}
