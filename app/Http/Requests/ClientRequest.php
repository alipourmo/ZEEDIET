<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\PregnancyStatus;
use App\Models\Client;
use App\Support\Jalali;
use App\Support\Mobile;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Fields shared by creating and editing a client (spec section 6.1). Staff only; medical fields are in
 * UpdateClientMedicalRequest.
 */
abstract class ClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $mobile = $this->input('mobile');

        if (is_string($mobile)) {
            // Normalised before validation, so 0912..., +98912... and Persian digits all compare equal (spec section 7.9).
            $this->merge(['mobile' => Mobile::normalize($mobile) ?? trim($mobile)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'birth_date' => ['nullable', 'string', 'max:10', $this->jalaliDate()],
            'gender' => ['required', Rule::enum(Gender::class)],
            'height_cm' => ['nullable', 'numeric', 'between:50,250'],
            'pregnancy_status' => ['required', Rule::enum(PregnancyStatus::class)],
            'referral_source' => ['nullable', 'string', 'max:120'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'consent_photos' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Client fields ready to save: Gregorian birth date, and consent timestamps.
     *
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        return [
            'first_name' => $this->string('first_name')->value(),
            'last_name' => $this->string('last_name')->value(),
            'mobile' => $this->string('mobile')->value(),
            'birth_date' => $this->gregorianBirthDate(),
            'gender' => $this->string('gender')->value(),
            'height_cm' => $this->filled('height_cm') ? $this->input('height_cm') : null,
            'pregnancy_status' => $this->string('pregnancy_status')->value(),
            'referral_source' => $this->nullableString('referral_source'),
            'internal_note' => $this->nullableString('internal_note'),
        ];
    }

    /**
     * Photo consent is ticked or cleared on each save. The time of the first consent is kept.
     */
    protected function photoConsentAt(?Client $existing): ?Carbon
    {
        if (! $this->boolean('consent_photos')) {
            return null;
        }

        if ($existing !== null && $existing->consent_photos_at !== null) {
            return $existing->consent_photos_at;
        }

        return now();
    }

    protected function clientIdToIgnore(): ?int
    {
        $client = $this->route('client');

        return $client instanceof Client ? $client->getKey() : null;
    }

    /**
     * Duplicate mobile check, including archived clients. An active duplicate gets a link to its file, passed to
     * the form through the session. The link is built in Blade, not in the message text.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $mobile = $this->input('mobile');

            if (! is_string($mobile) || Mobile::normalize($mobile) === null) {
                return;
            }

            $existing = Client::withTrashed()
                ->where('mobile', $mobile)
                ->when($this->clientIdToIgnore() !== null, fn ($q) => $q->whereKeyNot($this->clientIdToIgnore()))
                ->first(['id', 'deleted_at']);

            if ($existing === null) {
                return;
            }

            if ($existing->trashed()) {
                $v->errors()->add('mobile', __('clients.duplicate_mobile_archived'));

                return;
            }

            $v->errors()->add('mobile', __('clients.duplicate_mobile'));
            $this->session()->flash('duplicate_client_id', $existing->getKey());
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.regex' => __('clients.invalid_mobile'),
            'consent_data.accepted' => __('clients.consent_required'),
        ];
    }

    protected function gregorianBirthDate(): ?string
    {
        $value = $this->input('birth_date');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Jalali::parseToGregorian($value);
    }

    protected function nullableString(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function jalaliDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && Jalali::parseToGregorian($value) === null) {
                $fail(__('clients.invalid_birth_date'));
            }
        };
    }
}
