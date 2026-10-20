import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

import Join from '@/pages/Join.vue';

const form = reactive({
    name: '',
    password: '',
    password_confirmation: '',
    processing: false,
    errors: {} as Record<string, string>,
    post: vi.fn(),
    reset: vi.fn(),
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: () => form,
    usePage: () => ({ props: { appName: 'Studio Booking', tenant: { name: 'Studio A', slug: 'studio-a' }, auth: { user: null } } }),
}));

function mountJoin(props: { hasAccount: boolean; signedInAs: string | null }) {
    return mount(Join, { props: { token: 'the-token', email: 'nina@example.com', role: 'Staff', ...props } });
}

describe('Join page', () => {
    it('asks a new invitee to create an account', async () => {
        const wrapper = mountJoin({ hasAccount: false, signedInAs: null });

        expect(wrapper.find('[data-test="new-account"]').exists()).toBe(true);
        await wrapper.find('form').trigger('submit');
        expect(form.post).toHaveBeenCalledWith('/join/the-token', expect.any(Object));
    });

    it('sends an existing member to log in first', () => {
        const wrapper = mountJoin({ hasAccount: true, signedInAs: null });

        expect(wrapper.find('[data-test="new-account"]').exists()).toBe(false);
        expect(wrapper.find('a[href="/login"]').exists()).toBe(true);
    });

    it('tells another signed-in member to log out', () => {
        const wrapper = mountJoin({ hasAccount: false, signedInAs: 'other@example.com' });

        expect(wrapper.find('[data-test="wrong-account"]').text()).toContain('other@example.com');
        expect(wrapper.find('form').exists()).toBe(false);
    });
});
