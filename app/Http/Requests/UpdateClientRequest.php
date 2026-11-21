<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Edit a client. Data consent is kept as it was; it is not withdrawn by an edit (see DECISIONS).
 */
class UpdateClientRequest extends ClientRequest
{
    public function authorize(): bool
    {
        return Gate::allows('client.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules() + [
            'status' => ['required', Rule::enum(ClientStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        $client = $this->route('client');
        $existing = $client instanceof Client ? $client : null;

        return parent::clientData() + [
            'consent_photos_at' => $this->photoConsentAt($existing),
            'status' => $this->string('status')->value(),
        ];
    }
}
