<?php

declare(strict_types=1);

use App\Http\Middleware\PortalSession;
use App\Models\Client;
use Illuminate\Http\Request;

// The portal session (spec 7.5): 30 days from sign-in, and it ends after 24 hours without a request.

beforeEach(function () {
    $this->client = makeClient(['mobile' => '09121234567']);
    $this->gateway = new class implements App\Services\Sms\SmsGateway
    {
        /** @var list<App\Services\Sms\SmsMessage> */
        public array $sent = [];

        public function name(): string
        {
            return 'fake';
        }

        public function send(App\Services\Sms\SmsMessage $message): App\Services\Sms\SmsResult
        {
            $this->sent[] = $message;

            return App\Services\Sms\SmsResult::sent('fake');
        }
    };
    app()->instance(App\Services\Sms\SmsGateway::class, $this->gateway);
});

/** Signs the client in through the real OTP flow. */
function portalSignIn($test, Client $client): void
{
    $test->post(route('portal.login.store'), ['mobile' => $client->mobile]);
    preg_match('/(\d{5})/', (string) end($test->gateway->sent)->body, $matches);
    $test->post(route('portal.verify.store'), ['code' => $matches[1]]);
}

test('the portal session lasts 30 days, and other pages keep the normal lifetime', function () {
    $middleware = new PortalSession;

    $middleware->handle(Request::create('/my-account/payments'), fn () => response('ok'));
    expect(config('session.lifetime'))->toBe(30 * 24 * 60);

    config(['session.lifetime' => 120]);
    $middleware->handle(Request::create('/admin/clients'), fn () => response('ok'));
    expect(config('session.lifetime'))->toBe(120);
});

test('a client who stays active keeps the session; one idle for more than 24 hours is signed out', function () {
    portalSignIn($this, $this->client);

    $this->travel(23)->hours();
    $this->get(route('portal.home'))->assertOk();

    $this->travel(23)->hours();
    $this->get(route('portal.home'))->assertOk();

    $this->travel(25)->hours();
    $this->get(route('portal.home'))
        ->assertRedirect(route('portal.login'))
        ->assertSessionHas('error');

    $this->assertGuest('client');
});

test('a session older than 30 days is signed out even when it was used every day', function () {
    portalSignIn($this, $this->client);

    $days = 0;

    for ($i = 0; $i < 40; $i++) {
        $this->travel(23)->hours();
        $response = $this->get(route('portal.home'));

        if ($response->isRedirect()) {
            break;
        }

        $days++;
    }

    // 23 hours a step: the 30-day limit is passed on step 31 at the latest.
    expect($days)->toBeGreaterThanOrEqual(30)->and($days)->toBeLessThan(33);
    $this->assertGuest('client');
});
