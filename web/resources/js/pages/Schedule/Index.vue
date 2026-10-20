<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import StatusMessage from '@/components/StatusMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Session {
    id: number;
    className: string | null;
    instructorName: string | null;
    /** Wall-clock time in the studio, `YYYY-MM-DDTHH:mm`. */
    startsAt: string;
    endsAt: string;
    capacity: number;
    placesLeft: number;
    bookedByMe: boolean;
    canBook: boolean;
    canUpdate: boolean;
}

defineProps<{
    sessions: Session[];
    timezone: string;
    canCreate: boolean;
    status?: string | null;
}>();

const page = usePage();
const bookingError = computed(() => page.props.errors.booking);

function book(session: Session): void {
    router.post(`/schedule/${session.id}/bookings`, {}, { preserveScroll: true });
}

function day(localTime: string): string {
    return localTime.slice(0, 10);
}

function time(localTime: string): string {
    return localTime.slice(11, 16);
}
</script>

<template>
    <Head title="Schedule" />
    <AppLayout title="Schedule">
        <StatusMessage :status="status" />
        <p
            v-if="bookingError"
            class="rounded bg-red-50 px-3 py-2 text-sm text-red-800"
            data-test="booking-error"
        >
            {{ bookingError }}
        </p>
        <p class="text-sm text-stone-500">
            Times are in {{ timezone }}.
        </p>
        <Link
            v-if="canCreate"
            href="/schedule/create"
            class="self-start rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white"
        >
            Schedule a session
        </Link>
        <p
            v-if="sessions.length === 0"
            class="text-stone-600"
        >
            No upcoming sessions.
        </p>
        <table
            v-else
            class="w-full text-left text-sm"
        >
            <thead class="border-b border-stone-200 text-stone-500">
                <tr>
                    <th class="py-2 font-medium">
                        Day
                    </th>
                    <th class="py-2 font-medium">
                        Time
                    </th>
                    <th class="py-2 font-medium">
                        Class
                    </th>
                    <th class="py-2 font-medium">
                        Instructor
                    </th>
                    <th class="py-2 font-medium">
                        Places left
                    </th>
                    <th class="py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="session in sessions"
                    :key="session.id"
                    class="border-b border-stone-100"
                    data-test="session"
                >
                    <td class="py-2">
                        {{ day(session.startsAt) }}
                    </td>
                    <td class="py-2">
                        {{ time(session.startsAt) }}–{{ time(session.endsAt) }}
                    </td>
                    <td class="py-2">
                        {{ session.className }}
                    </td>
                    <td class="py-2">
                        {{ session.instructorName }}
                    </td>
                    <td class="py-2">
                        {{ session.placesLeft }} of {{ session.capacity }}
                    </td>
                    <td class="flex justify-end gap-3 py-2">
                        <span
                            v-if="session.bookedByMe"
                            class="text-green-700"
                            data-test="booked"
                        >
                            Booked
                        </span>
                        <button
                            v-else-if="session.canBook && session.placesLeft > 0"
                            type="button"
                            class="rounded bg-stone-900 px-2 py-1 text-white"
                            data-test="book"
                            @click="book(session)"
                        >
                            Book
                        </button>
                        <span
                            v-else-if="session.canBook"
                            class="text-stone-500"
                        >
                            Full
                        </span>
                        <Link
                            v-if="session.canUpdate"
                            :href="`/schedule/${session.id}/edit`"
                            class="text-stone-600 hover:text-stone-900"
                        >
                            Edit
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </AppLayout>
</template>
