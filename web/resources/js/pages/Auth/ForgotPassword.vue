<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import GuestLayout from '@/layouts/GuestLayout.vue';

defineProps<{
    status?: string | null;
}>();

const form = useForm({
    email: '',
});

function submit(): void {
    form.post('/forgot-password');
}
</script>

<template>
    <Head title="Reset your password" />
    <GuestLayout title="Reset your password">
        <p
            v-if="status"
            class="rounded bg-green-50 px-3 py-2 text-sm text-green-800"
        >
            {{ status }}
        </p>
        <form
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <label class="flex flex-col gap-1 text-sm">
                Email
                <input
                    v-model="form.email"
                    type="email"
                    name="email"
                    autocomplete="username"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <p
                v-if="form.errors.email"
                class="text-sm text-red-700"
            >
                {{ form.errors.email }}
            </p>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                Email me a reset link
            </button>
        </form>
        <p class="text-sm text-stone-600">
            <Link href="/login">
                Back to log in
            </Link>
        </p>
    </GuestLayout>
</template>
