<?php

declare(strict_types=1);

namespace App\Billing;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A payment gateway without a network (D-004).
 *
 * Tests bind one instance with $autoPay = false, start checkouts through it
 * and pay them with markPaid(); those sessions live in memory.
 *
 * The local demo uses $autoPay = true: the "hosted page" is the success URL
 * itself and the checkout is paid. Requests are separate processes there, so
 * an auto-paid session carries its result in its id ("cs_fake_paid_<kind>_…")
 * instead of in memory.
 *
 * Never used outside the local and testing environments (AppServiceProvider).
 */
final class FakePaymentGateway implements PaymentGateway
{
    private const string PAID_PREFIX = 'cs_fake_paid_';

    /** @var array<string, array{request: CheckoutRequest, paid: bool}> */
    private array $sessions = [];

    public function __construct(private readonly bool $autoPay = false) {}

    /**
     * A session id that every fake gateway reports as paid, as an auto-paid
     * checkout would have. For tests that fulfil in another process.
     */
    public static function paidSessionId(CheckoutKind $kind): string
    {
        return self::PAID_PREFIX.$kind->value.'_'.Str::random(16);
    }

    public function startCheckout(CheckoutRequest $request): StartedCheckout
    {
        $sessionId = $this->autoPay ? self::paidSessionId($request->kind) : 'cs_fake_'.Str::random(24);
        $this->sessions[$sessionId] = ['request' => $request, 'paid' => $this->autoPay];

        $url = $this->autoPay
            ? str_replace('{CHECKOUT_SESSION_ID}', $sessionId, $request->successUrl)
            : 'https://checkout.fake.test/'.$sessionId;

        return new StartedCheckout($sessionId, $url);
    }

    public function checkoutResult(string $sessionId): CheckoutResult
    {
        $kind = $this->paidKind($sessionId);

        if ($kind === null) {
            $session = $this->sessions[$sessionId] ?? throw new InvalidArgumentException("Unknown checkout session {$sessionId}.");

            if (! $session['paid']) {
                return new CheckoutResult(paid: false);
            }

            $kind = $session['request']->kind;
        }

        return $kind === CheckoutKind::Membership
            ? new CheckoutResult(paid: true, subscriptionId: 'sub_fake_'.md5($sessionId), periodEndsAt: now()->toImmutable()->addMonth())
            : new CheckoutResult(paid: true);
    }

    public function markPaid(string $sessionId): void
    {
        if (! isset($this->sessions[$sessionId])) {
            throw new InvalidArgumentException("Unknown checkout session {$sessionId}.");
        }

        $this->sessions[$sessionId]['paid'] = true;
    }

    /**
     * The request a checkout was started with, for assertions.
     */
    public function requestFor(string $sessionId): CheckoutRequest
    {
        return ($this->sessions[$sessionId] ?? throw new InvalidArgumentException("Unknown checkout session {$sessionId}."))['request'];
    }

    /**
     * @return list<string>
     */
    public function sessionIds(): array
    {
        return array_keys($this->sessions);
    }

    private function paidKind(string $sessionId): ?CheckoutKind
    {
        if (! str_starts_with($sessionId, self::PAID_PREFIX)) {
            return null;
        }

        foreach (CheckoutKind::cases() as $kind) {
            if (str_starts_with($sessionId, self::PAID_PREFIX.$kind->value.'_')) {
                return $kind;
            }
        }

        return null;
    }
}
