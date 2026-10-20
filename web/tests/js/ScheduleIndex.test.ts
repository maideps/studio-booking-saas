import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import ScheduleIndex from '@/pages/Schedule/Index.vue';

const post = vi.fn();
const page = {
    props: {
        appName: 'Studio Booking',
        tenant: { name: 'Studio A', slug: 'studio-a' },
        auth: { user: { id: 3, name: 'Casey', email: 'casey@example.com', role: 'customer', can: { viewMembers: false, manageClasses: false, updateSettings: false } } },
        errors: {} as Record<string, string>,
    },
};

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    router: { post: (...args: unknown[]) => post(...args) },
    usePage: () => page,
}));

function session(overrides: Record<string, unknown>) {
    return {
        id: 1,
        className: 'Morning Flow',
        instructorName: 'Sam',
        startsAt: '2026-11-10T07:30',
        endsAt: '2026-11-10T08:30',
        capacity: 12,
        placesLeft: 5,
        bookedByMe: false,
        canBook: true,
        canUpdate: false,
        ...overrides,
    };
}

function mountSchedule(sessions: ReturnType<typeof session>[]) {
    return mount(ScheduleIndex, { props: { sessions, timezone: 'Europe/Madrid', canCreate: false } });
}

describe('Schedule page', () => {
    it('books a session with places left', async () => {
        const wrapper = mountSchedule([session({ id: 7 })]);

        expect(wrapper.text()).toContain('5 of 12');
        await wrapper.find('[data-test="book"]').trigger('click');

        expect(post).toHaveBeenCalledWith('/schedule/7/bookings', {}, { preserveScroll: true });
    });

    it('shows booked and full sessions without a Book button', () => {
        const wrapper = mountSchedule([
            session({ id: 1, bookedByMe: true }),
            session({ id: 2, placesLeft: 0 }),
        ]);

        expect(wrapper.find('[data-test="booked"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="book"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Full');
    });

    it('shows why a booking was refused', () => {
        page.props.errors = { booking: 'This class is full.' };

        expect(mountSchedule([session({})]).find('[data-test="booking-error"]').text()).toBe('This class is full.');

        page.props.errors = {};
    });
});
