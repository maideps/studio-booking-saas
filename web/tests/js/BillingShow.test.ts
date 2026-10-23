import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import BillingShow from '@/pages/Billing/Show.vue';

const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { post: (...args: unknown[]) => post(...args) },
    usePage: () => ({
        props: {
            appName: 'Studio Booking',
            tenant: { name: 'Studio A', slug: 'studio-a' },
            auth: { user: { id: 3, name: 'Casey', email: 'casey@example.com', role: 'customer', can: { viewMembers: false, manageClasses: false, updateSettings: false, buy: true } } },
            errors: {},
        },
    }),
}));

const paidOffers = { currency: 'eur', membershipPriceCents: 4900, classPackSize: 10, classPackPriceCents: 9000, freeToBook: false };

describe('Billing page', () => {
    it('offers the membership and the class pack with their prices', async () => {
        const wrapper = mount(BillingShow, { props: { offers: paidOffers, membership: null, credits: 0 } });

        expect(wrapper.find('[data-test="membership-offer"]').text()).toContain('49');
        expect(wrapper.find('[data-test="class-pack-offer"]').text()).toContain('10-class pack');
        expect(wrapper.find('[data-test="credits"]').text()).toContain('0 class credits');

        await wrapper.find('[data-test="buy-class-pack"]').trigger('click');
        expect(post).toHaveBeenCalledWith('/billing/checkout/class_pack');
    });

    it('does not offer a second membership to a member', () => {
        const wrapper = mount(BillingShow, {
            props: { offers: paidOffers, membership: { activeUntil: '2026-11-20T10:00' }, credits: 1 },
        });

        expect(wrapper.find('[data-test="membership"]').text()).toContain('2026-11-20 10:00');
        expect(wrapper.find('[data-test="buy-membership"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="credits"]').text()).toContain('1 class credit left');
    });

    it('says a free studio has nothing to buy', () => {
        const wrapper = mount(BillingShow, {
            props: {
                offers: { currency: 'eur', membershipPriceCents: null, classPackSize: null, classPackPriceCents: null, freeToBook: true },
                membership: null,
                credits: 0,
            },
        });

        expect(wrapper.find('[data-test="free"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="membership-offer"]').exists()).toBe(false);
    });
});
