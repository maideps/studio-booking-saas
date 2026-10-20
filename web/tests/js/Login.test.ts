import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

import Login from '@/pages/Auth/Login.vue';

const post = vi.fn();
const form = reactive({
    email: '',
    password: '',
    remember: false,
    processing: false,
    errors: {} as Record<string, string>,
    post,
    reset: vi.fn(),
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    useForm: () => form,
    usePage: () => ({ props: { appName: 'Studio Booking', tenant: { name: 'Studio A', slug: 'studio-a' }, auth: { user: null } } }),
}));

describe('Login page', () => {
    beforeEach(() => {
        form.errors = {};
        post.mockClear();
    });

    it('shows the studio name and the status message', () => {
        const wrapper = mount(Login, { props: { status: 'Your password has been reset.' } });

        expect(wrapper.text()).toContain('Studio A');
        expect(wrapper.text()).toContain('Your password has been reset.');
    });

    it('shows the error for the email field', () => {
        form.errors = { email: 'These credentials do not match our records.' };
        const wrapper = mount(Login);

        expect(wrapper.text()).toContain('These credentials do not match our records.');
    });

    it('posts the credentials to the login route', async () => {
        const wrapper = mount(Login);

        await wrapper.find('input[name="email"]').setValue('alice@example.com');
        await wrapper.find('input[name="password"]').setValue('secret-password');
        await wrapper.find('form').trigger('submit');

        expect(form.email).toBe('alice@example.com');
        expect(post).toHaveBeenCalledWith('/login', expect.objectContaining({ onFinish: expect.any(Function) }));
    });
});
