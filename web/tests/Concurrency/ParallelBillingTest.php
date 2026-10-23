<?php

declare(strict_types=1);

use App\Billing\CheckoutKind;
use App\Billing\FakePaymentGateway;
use App\Models\Booking;
use App\Models\Checkout;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use Tests\Support\Concurrency\ParallelAttempts;
use Tests\Support\StudioFixture;

// Spending and buying class-pack credits under parallel requests on
// PostgreSQL, in separate PHP processes (D-012). Run by tools/test-postgres.mjs.

it('spends a single credit once when one customer books four sessions at the same moment', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    $sessions = $studio->tenant->run(function () use ($studio): array {
        CreditEntry::factory()->create(['customer_id' => $studio->customer->id, 'change' => 1]);

        return array_values(ClassSession::factory()->count(4)->create([
            'class_template_id' => $studio->classTemplate->id,
            'instructor_id' => $studio->staff->id,
        ])->all());
    });

    $results = ParallelAttempts::run(
        $studio->tenant,
        array_map(fn (ClassSession $session): array => ['book', $session->id, $studio->customer->id], $sessions),
    );

    expect(array_count_values($results))->toBe([
        'booked' => 1,
        'refused: Buy a membership or a class pack to book.' => 3,
    ])->and($studio->tenant->run(fn () => [
        'bookings' => Booking::query()->active()->where('customer_id', $studio->customer->id)->count(),
        'balance' => (int) CreditEntry::query()->where('customer_id', $studio->customer->id)->sum('change'),
    ]))->toBe(['bookings' => 1, 'balance' => 0]);
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);

it('credits a paid class pack once when its fulfilment runs four times at the same moment', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    $checkout = $studio->tenant->run(fn () => Checkout::factory()->create([
        'customer_id' => $studio->customer->id,
        'gateway_session_id' => FakePaymentGateway::paidSessionId(CheckoutKind::ClassPack),
    ]));

    $results = ParallelAttempts::run(
        $studio->tenant,
        array_fill(0, 4, ['fulfil', $checkout->id, $studio->customer->id]),
    );

    expect(array_count_values($results))->toBe([
        'already fulfilled' => 3,
        'fulfilled' => 1,
    ])->and($studio->tenant->run(fn () => (int) CreditEntry::query()->where('customer_id', $studio->customer->id)->sum('change')))
        ->toBe(10);
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);
