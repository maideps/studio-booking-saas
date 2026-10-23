<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Billing\CheckoutKind;
use App\Billing\PaymentGateway;
use App\Enums\CheckoutStatus;
use App\Enums\CreditReason;
use App\Enums\MembershipStatus;
use App\Models\Checkout;
use App\Models\CreditEntry;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;

/**
 * Gives the customer what a paid checkout bought, exactly once (D-012).
 *
 * The gateway is asked about the session stored on the checkout, never about
 * an id from the request. The checkout row is then locked: a completed
 * checkout is left alone, so a second return visit, or a feat-008 webhook for
 * the same payment, changes nothing. The unique checkout reference on credit
 * entries backs this up in the database.
 */
final readonly class FulfilCheckout
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * @return bool whether this call fulfilled it
     */
    public function handle(Checkout $checkout): bool
    {
        if ($checkout->status === CheckoutStatus::Completed || $checkout->gateway_session_id === null) {
            return false;
        }

        $result = $this->gateway->checkoutResult($checkout->gateway_session_id);

        if (! $result->paid) {
            return false;
        }

        return DB::transaction(function () use ($checkout, $result): bool {
            $locked = Checkout::query()->lockForUpdate()->findOrFail($checkout->id);

            if ($locked->status === CheckoutStatus::Completed) {
                return false;
            }

            if ($locked->kind === CheckoutKind::Membership) {
                $membership = Membership::query()->where('customer_id', $locked->customer_id)->first() ?? new Membership;
                $membership->customer_id = $locked->customer_id;
                $membership->checkout_id = $locked->id;
                $membership->status = MembershipStatus::Active;
                $membership->current_period_ends_at = $result->periodEndsAt;
                $membership->gateway_subscription_id = $result->subscriptionId;
                $membership->save();
            } else {
                $entry = new CreditEntry;
                $entry->customer_id = $locked->customer_id;
                $entry->checkout_id = $locked->id;
                $entry->change = (int) $locked->credits;
                $entry->reason = CreditReason::Purchase;
                $entry->save();
            }

            $locked->status = CheckoutStatus::Completed;
            $locked->completed_at = now()->toImmutable();
            $locked->save();

            return true;
        });
    }
}
