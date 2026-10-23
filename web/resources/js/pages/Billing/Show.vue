<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import StatusMessage from '@/components/StatusMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    offers: {
        currency: string;
        membershipPriceCents: number | null;
        classPackSize: number | null;
        classPackPriceCents: number | null;
        freeToBook: boolean;
    };
    membership: { activeUntil: string | null } | null;
    credits: number;
    status?: string | null;
}>();

const page = usePage();
const billingError = computed(() => page.props.errors.billing);

function price(cents: number): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: props.offers.currency.toUpperCase() }).format(cents / 100);
}

function buy(kind: 'membership' | 'class_pack'): void {
    router.post(`/billing/checkout/${kind}`);
}
</script>

<template>
    <Head title="Billing" />
    <AppLayout title="Billing">
        <StatusMessage :status="status" />
        <p
            v-if="billingError"
            class="rounded bg-red-50 px-3 py-2 text-sm text-red-800"
            data-test="billing-error"
        >
            {{ billingError }}
        </p>

        <p
            v-if="offers.freeToBook"
            class="text-stone-600"
            data-test="free"
        >
            This studio is free to book. There is nothing to buy.
        </p>

        <section
            v-else
            class="flex flex-col gap-2"
        >
            <h2 class="font-medium">
                Your access
            </h2>
            <p
                v-if="membership"
                data-test="membership"
            >
                Membership active until {{ membership.activeUntil?.replace('T', ' ') }}.
            </p>
            <p data-test="credits">
                {{ credits }} class {{ credits === 1 ? 'credit' : 'credits' }} left.
            </p>
        </section>

        <div class="flex flex-wrap gap-4">
            <div
                v-if="offers.membershipPriceCents !== null"
                class="flex flex-col gap-2 rounded border border-stone-200 bg-white p-4"
                data-test="membership-offer"
            >
                <h2 class="font-medium">
                    Monthly membership
                </h2>
                <p class="text-sm text-stone-600">
                    Book as many classes as you like. {{ price(offers.membershipPriceCents) }} a month.
                </p>
                <button
                    v-if="!membership"
                    type="button"
                    class="self-start rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white"
                    data-test="buy-membership"
                    @click="buy('membership')"
                >
                    Subscribe
                </button>
            </div>
            <div
                v-if="offers.classPackSize !== null && offers.classPackPriceCents !== null"
                class="flex flex-col gap-2 rounded border border-stone-200 bg-white p-4"
                data-test="class-pack-offer"
            >
                <h2 class="font-medium">
                    {{ offers.classPackSize }}-class pack
                </h2>
                <p class="text-sm text-stone-600">
                    One credit per class, refunded if you cancel in time. {{ price(offers.classPackPriceCents) }}.
                </p>
                <button
                    type="button"
                    class="self-start rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white"
                    data-test="buy-class-pack"
                    @click="buy('class_pack')"
                >
                    Buy pack
                </button>
            </div>
        </div>
    </AppLayout>
</template>
