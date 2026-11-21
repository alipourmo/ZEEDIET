<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\AuditAction;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Payment;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Payments (spec sections 6.5 and 8.2). Recording needs payment.record (admin and secretary). Reversing needs
 * payment.reverse (admin only). A payment is never changed or deleted: a mistake is fixed by a reversing entry.
 * Every write is audited. The audit row holds the amount and the method, never the reference or the notes.
 */
final class PaymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{client_id: int, appointment_id: ?int, package_id: ?int, amount: int, method: PaymentMethod, paid_at: CarbonImmutable, reference: ?string, note: ?string}  $data
     */
    public function record(array $data, User $actor): Payment
    {
        if (! Gate::forUser($actor)->allows('payment.record')) {
            throw new AuthorizationException;
        }

        if ($data['amount'] < 1) {
            throw ValidationException::withMessages(['amount' => __('payments.errors.amount_invalid')]);
        }

        return DB::transaction(function () use ($data, $actor): Payment {
            // Lock the client first, the same order PackageService and SessionRecorder use.
            Client::query()->whereKey($data['client_id'])->lockForUpdate()->firstOrFail();

            if ($data['package_id'] !== null && ! SessionPackage::query()
                ->whereKey($data['package_id'])
                ->where('client_id', $data['client_id'])
                ->exists()) {
                throw ValidationException::withMessages(['package_id' => __('payments.errors.package_invalid')]);
            }

            /** @var Payment $payment */
            $payment = Payment::query()->create($data + [
                'reverses_id' => null,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log(AuditAction::Create, $payment, $data['client_id'], ['new' => [
                'amount' => $data['amount'],
                'method' => $data['method']->value,
                'paid_at' => $data['paid_at']->toDateString(),
                'package_id' => $data['package_id'],
                'appointment_id' => $data['appointment_id'],
            ]], $actor);

            return $payment;
        });
    }

    /**
     * Why a payment cannot be reversed, as a translation key, or null when it can.
     */
    public function reversalRefusal(Payment $payment): ?string
    {
        if ($payment->isReversal()) {
            return __('payments.errors.not_reversible');
        }

        if ($payment->isReversed()) {
            return __('payments.errors.already_reversed');
        }

        return null;
    }

    /**
     * A reversing entry: the same client, method and package with the negative amount, dated today. The original
     * row is never touched. The reason is stored on the reversing entry.
     */
    public function reverse(Payment $payment, string $note, User $actor): Payment
    {
        if (! Gate::forUser($actor)->allows('payment.reverse')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($payment, $note, $actor): Payment {
            $clientId = (int) $payment->getAttribute('client_id');
            Client::query()->whereKey($clientId)->lockForUpdate()->firstOrFail();

            $original = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $refusal = $this->reversalRefusal($original);
            if ($refusal !== null) {
                throw ValidationException::withMessages(['payment' => $refusal]);
            }

            /** @var Payment $reversal */
            $reversal = Payment::query()->create([
                'client_id' => $clientId,
                'appointment_id' => $original->getAttribute('appointment_id'),
                'package_id' => $original->getAttribute('package_id'),
                'amount' => -(int) $original->getAttribute('amount'),
                'method' => $original->method,
                'paid_at' => now(),
                'reference' => null,
                'note' => $note,
                'reverses_id' => $original->getKey(),
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log(AuditAction::Create, $reversal, $clientId, ['new' => [
                'amount' => $reversal->amount,
                'reverses_id' => $original->getKey(),
            ]], $actor);

            return $reversal;
        });
    }

    /**
     * The payments and reversing entries that match the filters, newest first. Every filter is optional. Dates are
     * Y-m-d in clinic time.
     *
     * @param  array{client_id: ?int, from: ?string, to: ?string}  $filters
     * @return Builder<Payment>
     */
    public function listing(array $filters): Builder
    {
        $query = Payment::query()
            ->with([
                'client:id,first_name,last_name,code',
                'package.service:id,name',
                'reversal:id,reverses_id',
            ])
            ->latest('paid_at')
            ->latest('id');

        if ($filters['client_id'] !== null) {
            $query->where('client_id', $filters['client_id']);
        }

        if ($filters['from'] !== null) {
            $query->where('paid_at', '>=', Carbon::parse($filters['from'], 'Asia/Tehran')->startOfDay());
        }

        if ($filters['to'] !== null) {
            $query->where('paid_at', '<=', Carbon::parse($filters['to'], 'Asia/Tehran')->endOfDay());
        }

        return $query;
    }

    /**
     * The net total of the filtered rows: payments minus reversals, in Toman.
     *
     * @param  array{client_id: ?int, from: ?string, to: ?string}  $filters
     */
    public function netTotal(array $filters): int
    {
        return (int) $this->listing($filters)->reorder()->sum('amount');
    }

    /**
     * Opening a client's ledger is audited (spec 6.5): financial history is sensitive. The row holds the section only.
     */
    public function openLedger(Client $client, User $actor): void
    {
        $this->audit->log(AuditAction::View, $client, $client->getKey(), ['sections' => ['payments']], $actor);
    }
}
