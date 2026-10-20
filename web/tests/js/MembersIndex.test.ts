import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import MembersIndex from '@/pages/Members/Index.vue';

const patch = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { patch: (...args: unknown[]) => patch(...args), post: vi.fn() },
    usePage: () => ({
        props: {
            appName: 'Studio Booking',
            tenant: { name: 'Studio A', slug: 'studio-a' },
            auth: { user: { id: 1, name: 'Olivia', email: 'owner@example.com', role: 'owner', canViewMembers: true } },
        },
    }),
}));

const roles = [
    { value: 'owner', label: 'Owner' },
    { value: 'staff', label: 'Staff' },
    { value: 'customer', label: 'Customer' },
] as const;

function mountPage() {
    return mount(MembersIndex, {
        props: {
            roles: [...roles],
            members: [
                { id: 1, name: 'Olivia', email: 'owner@example.com', role: 'owner', canUpdateRole: false },
                { id: 2, name: 'Casey', email: 'customer@example.com', role: 'customer', canUpdateRole: true },
            ],
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
});
