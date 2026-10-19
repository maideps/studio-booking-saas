import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import Welcome from '@/pages/Welcome.vue';

describe('Welcome page', () => {
    it('shows the application name as the heading', () => {
        const wrapper = mount(Welcome, { props: { appName: 'Studio Booking' } });

        expect(wrapper.get('h1').text()).toBe('Studio Booking');
    });
});
