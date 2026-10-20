<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import GuestLayout from '@/layouts/GuestLayout.vue';

defineProps<{
    status?: string | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Log in" />
    <GuestLayout title="Log in">
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
            <label class="flex flex-col gap-1 text-sm">
                Password
                <input
                    v-model="form.password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    name="remember"
                >
                Remember me
            </label>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                Log in
            </button>
        </form>
        <p class="flex justify-between text-sm text-stone-600">
            <Link href="/forgot-password">
                Forgot your password?
            </Link>
            <Link href="/register">
                Create an account
            </Link>
        </p>
    </GuestLayout>
</template>
