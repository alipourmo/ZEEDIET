<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PaymentFilterRequest;
use App\Http\Requests\Payments\ReversePaymentRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Models\Client;
use App\Models\Payment;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\Jalali;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Payments for admin and secretary (spec sections 6.5 and 8.2). Listing and recording need payment.view and
 * payment.record. Reversing needs payment.reverse, which only admin holds. There is no edit and no delete.
 */
class PaymentController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly PaymentService $payments) {}

    public function index(PaymentFilterRequest $request): View
    {
        $filters = $request->filters();
        $client = $filters['client_id'] === null ? null : Client::query()->find($filters['client_id']);

        if ($client !== null) {
            $this->payments->openLedger($client, $this->actor($request));
        }

        return view('admin.payments.index', [
            'payments' => $this->payments->listing($filters)->paginate(self::PER_PAGE)->withQueryString(),
            'total' => $this->payments->netTotal($filters),
            'client' => $client,
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'newUrl' => route('admin.payments.create', $client === null ? [] : ['client' => $client->getKey()]),
            'canRecord' => Gate::allows('payment.record'),
            'canReverse' => Gate::allows('payment.reverse'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.payments.form', [
            'clients' => Client::query()->orderBy('last_name')->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'code']),
            'packages' => SessionPackage::query()
                ->with(['client:id,first_name,last_name', 'service:id,name'])
                ->latest('purchased_at')
                ->latest('id')
                ->get(['id', 'client_id', 'service_id', 'total_sessions', 'purchased_at']),
            'methods' => PaymentMethod::cases(),
            'prefillClient' => $request->integer('client') ?: null,
            'today' => Jalali::formatGregorianAsJalali(now('Asia/Tehran')->toDateString()),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $payment = $this->payments->record($request->paymentData(), $this->actor($request));

        return redirect()
            ->route('admin.payments.index', ['client' => $payment->getAttribute('client_id')])
            ->with('success', __('payments.saved'));
    }

    public function reverseForm(Payment $payment): View|RedirectResponse
    {
        $refusal = $this->payments->reversalRefusal($payment);

        if ($refusal !== null) {
            return redirect()->route('admin.payments.index')->withErrors(['payment' => $refusal]);
        }

        $payment->load(['client:id,first_name,last_name,code', 'package.service:id,name']);

        return view('admin.payments.reverse', ['payment' => $payment]);
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $reversal = $this->payments->reverse($payment, $request->reason(), $this->actor($request));

        return redirect()
            ->route('admin.payments.index', ['client' => $reversal->getAttribute('client_id')])
            ->with('success', __('payments.reversed'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
