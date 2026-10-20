<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import FieldError from '@/components/FieldError.vue';
import StatusMessage from '@/components/StatusMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    settings: { name: string; timezone: string; contact_email: string | null; cancellation_window_hours: number };
    timezones: string[];
    status?: string | null;
}>();

const form = useForm({
    name: props.settings.name,
    timezone: props.settings.timezone,
    contact_email: props.settings.contact_email ?? '',
    cancellation_window_hours: props.settings.cancellation_window_hours,
});

function submit(): void {
    form.put('/settings');
}
</script>

<template>
    <Head title="Studio settings" />
    <AppLayout title="Studio settings">
        <StatusMessage :status="status" />
        <form
            class="flex max-w-md flex-col gap-4"
            @submit.prevent="submit"
        >
            <label class="flex flex-col gap-1 text-sm">
                Studio name
                <input
                    v-model="form.name"
                    type="text"
                    name="name"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <FieldError :message="form.errors.name" />
            <label class="flex flex-col gap-1 text-sm">
                Timezone
                <select
                    v-model="form.timezone"
                    name="timezone"
                    class="rounded border border-stone-300 px-3 py-2"
                >
                    <option
                        v-for="timezone in timezones"
                        :key="timezone"
                        :value="timezone"
                    >
                        {{ timezone }}
                    </option>
                </select>
            </label>
            <FieldError :message="form.errors.timezone" />
            <label class="flex flex-col gap-1 text-sm">
                Contact email
                <input
                    v-model="form.contact_email"
                    type="email"
                    name="contact_email"
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <FieldError :message="form.errors.contact_email" />
            <label class="flex flex-col gap-1 text-sm">
                Customers can cancel up to (hours before class)
                <input
                    v-model.number="form.cancellation_window_hours"
                    type="number"
                    name="cancellation_window_hours"
                    min="0"
                    max="168"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <FieldError :message="form.errors.cancellation_window_hours" />
            <button
                type="submit"
                :disabled="form.processing"
                class="self-start rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                Save settings
            </button>
        </form>
    </AppLayout>
</template>
