<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

/**
 * New client. Data-storage consent is required to save (spec section 10.9).
 */
class StoreClientRequest extends ClientRequest
{
    public function authorize(): bool
    {
        return Gate::allows('client.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules() + [
            'consent_data' => ['accepted'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        return parent::clientData() + [
            'consent_data_at' => now(),
            'consent_photos_at' => $this->photoConsentAt(null),
        ];
    }
}
