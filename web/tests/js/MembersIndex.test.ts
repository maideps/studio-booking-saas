import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

import MembersIndex from '@/pages/Members/Index.vue';

const patch = vi.fn();
const remove = vi.fn();
const invite = reactive({
    email: '',
    role: 'staff',
    processing: false,
    errors: {} as Record<string, string>,
    post: vi.fn(),
    reset: vi.fn(),
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { patch: (...args: unknown[]) => patch(...args), delete: (...args: unknown[]) => remove(...args), post: vi.fn() },
    useForm: () => invite,
    usePage: () => ({
        props: {
            appName: 'Studio Booking',
            tenant: { name: 'Studio A', slug: 'studio-a' },
            auth: {
                user: {
                    id: 1,
                    name: 'Olivia',
                    email: 'owner@example.com',
                    role: 'owner',
                    can: { viewMembers: true, manageClasses: true, updateSettings: true },
                },
            },
        },
    }),
}));

const roles = [
    { value: 'owner', label: 'Owner' },
    { value: 'staff', label: 'Staff' },
    { value: 'customer', label: 'Customer' },
] as const;

function mountPage(extra: Record<string, unknown> = {}) {
    return mount(MembersIndex, {
        props: {
            roles: [...roles],
            members: [
                { id: 1, name: 'Olivia', email: 'owner@example.com', role: 'owner', canUpdateRole: false },
                { id: 2, name: 'Casey', email: 'customer@example.com', role: 'customer', canUpdateRole: true },
            ],
            ...extra,
        },
    });
}

describe('Members page', () => {
    it('offers a role picker only where the server allows the change', () => {
        const rows = mountPage().findAll('[data-test="member"]');

        expect(rows).toHaveLength(2);
        expect(rows[0]?.find('select').exists()).toBe(false);
        expect(rows[0]?.text()).toContain('Owner');
        expect(rows[1]?.find('select').exists()).toBe(true);
    });

    it('sends the new role to the server', async () => {
        const select = mountPage().findAll('[data-test="member"]')[1]?.find('select');

        await select?.setValue('staff');

        expect(patch).toHaveBeenCalledWith('/members/2/role', { role: 'staff' }, { preserveScroll: true });
    });

    it('shows the invite form and pending invitations only when the server allows inviting', async () => {
        expect(mountPage().find('[data-test="invite-form"]').exists()).toBe(false);

        const wrapper = mountPage({
            canInvite: true,
            invitations: [{ id: 7, email: 'nina@example.com', role: 'Staff', expiresAt: '2026-10-27T09:00' }],
        });

        expect(wrapper.find('[data-test="invite-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="invitation"]').text()).toContain('nina@example.com');

        await wrapper.find('[data-test="invitation"] button').trigger('click');
        expect(remove).toHaveBeenCalledWith('/invitations/7', { preserveScroll: true });
    });
});
