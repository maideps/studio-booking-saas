<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import GuestLayout from '@/layouts/GuestLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Create an account" />
    <GuestLayout title="Create an account">
        <form
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <label class="flex flex-col gap-1 text-sm">
                Name
                <input
                    v-model="form.name"
                    type="text"
                    name="name"
                    autocomplete="name"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <p
                v-if="form.errors.name"
                class="text-sm text-red-700"
            >
                {{ form.errors.name }}
            </p>
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
                    autocomplete="new-password"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <p
                v-if="form.errors.password"
                class="text-sm text-red-700"
            >
                {{ form.errors.password }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                Confirm password
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                Create account
            </button>
        </form>
        <p class="text-sm text-stone-600">
            Already a member?
            <Link href="/login">
                Log in
            </Link>
        </p>
    </GuestLayout>
</template>
