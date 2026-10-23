<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\FulfilCheckout;
use App\Actions\Billing\StartCheckout;
use App\Billing\CheckoutKind;
use App\Billing\Entitlements;
use App\Exceptions\BillingRefused;
use App\Models\Checkout;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The customer's billing page, and the purchase of the studio's membership or
 * class pack. Authorization is on the routes (CheckoutPolicy).
 */
final class BillingController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(Request $request, Entitlements $entitlements): Response
    {
        $customer = $request->user();
        assert($customer instanceof User);
        $tenant = $this->context->currentOrFail();
        $membership = $entitlements->activeMembership($customer);

        return Inertia::render('Billing/Show', [
            'offers' => [
                'currency' => $tenant->currency,
                'membershipPriceCents' => $tenant->membership_price_cents,
                'classPackSize' => $tenant->offersClassPack() ? $tenant->class_pack_size : null,
                'classPackPriceCents' => $tenant->offersClassPack() ? $tenant->class_pack_price_cents : null,
                'freeToBook' => $tenant->isFreeToBook(),
            ],
            'membership' => $membership === null ? null : [
                'activeUntil' => $membership->current_period_ends_at === null
                    ? null
                    : $tenant->localTime($membership->current_period_ends_at)->format(Tenant::LOCAL_TIME_FORMAT),
            ],
            'credits' => $entitlements->creditBalance($customer),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function checkout(Request $request, string $kind, StartCheckout $start): HttpResponse
    {
        $customer = $request->user();
        assert($customer instanceof User);
        $checkoutKind = CheckoutKind::tryFrom($kind) ?? abort(404);

        try {
            ['url' => $url] = $start->handle($customer, $checkoutKind);
        } catch (BillingRefused $refused) {
            return redirect()->route('billing.show')->withErrors(['billing' => $refused->getMessage()]);
        }

        // An external page: Inertia must leave the app for it.
        return Inertia::location($url);
    }

    public function complete(Checkout $checkout, FulfilCheckout $fulfil): RedirectResponse
    {
        $fulfil->handle($checkout);

        $done = Checkout::query()->findOrFail($checkout->id)->status->value === 'completed';

        return redirect()->route('billing.show')->with('status', $done
            ? __('Payment received. Thank you!')
            : __('We have not received the payment yet. If you paid, refresh in a moment.'));
    }
}
