<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import FieldError from '@/components/FieldError.vue';
import StatusMessage from '@/components/StatusMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    settings: {
        name: string;
        timezone: string;
        contact_email: string | null;
        cancellation_window_hours: number;
        currency: string;
        membership_price: string | null;
        class_pack_size: number | null;
        class_pack_price: string | null;
    };
    timezones: string[];
    currencies: string[];
    status?: string | null;
}>();

const form = useForm({
    name: props.settings.name,
    timezone: props.settings.timezone,
    contact_email: props.settings.contact_email ?? '',
    cancellation_window_hours: props.settings.cancellation_window_hours,
    currency: props.settings.currency,
    membership_price: props.settings.membership_price ?? '',
    class_pack_size: props.settings.class_pack_size ?? '',
    class_pack_price: props.settings.class_pack_price ?? '',
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
            <fieldset class="flex flex-col gap-3 rounded border border-stone-200 p-4">
                <legend class="px-1 text-sm font-medium">
                    Prices (leave empty to not sell it; a studio that sells nothing is free to book)
                </legend>
                <label class="flex flex-col gap-1 text-sm">
                    Currency
                    <select
                        v-model="form.currency"
                        name="currency"
                        class="rounded border border-stone-300 px-3 py-2"
                    >
                        <option
                            v-for="currency in currencies"
                            :key="currency"
                            :value="currency"
                        >
                            {{ currency.toUpperCase() }}
                        </option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Monthly membership price
                    <input
                        v-model="form.membership_price"
                        type="number"
                        name="membership_price"
                        min="1"
                        step="0.01"
                        class="rounded border border-stone-300 px-3 py-2"
                    >
                </label>
                <FieldError :message="form.errors.membership_price" />
                <div class="flex gap-3">
                    <label class="flex flex-1 flex-col gap-1 text-sm">
                        Class pack: classes
                        <input
                            v-model="form.class_pack_size"
                            type="number"
                            name="class_pack_size"
                            min="1"
                            max="100"
                            class="rounded border border-stone-300 px-3 py-2"
                        >
                    </label>
                    <label class="flex flex-1 flex-col gap-1 text-sm">
                        Class pack: price
                        <input
                            v-model="form.class_pack_price"
                            type="number"
                            name="class_pack_price"
                            min="1"
                            step="0.01"
                            class="rounded border border-stone-300 px-3 py-2"
                        >
                    </label>
                </div>
                <FieldError :message="form.errors.class_pack_size ?? form.errors.class_pack_price" />
            </fieldset>
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
