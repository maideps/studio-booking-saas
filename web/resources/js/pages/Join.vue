<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import FieldError from '@/components/FieldError.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

const props = defineProps<{
    token: string;
    email: string;
    role: string;
    /** The invited email already has an account in this studio. */
    hasAccount: boolean;
    signedInAs: string | null;
}>();

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post(`/join/${props.token}`, { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Join the studio" />
    <GuestLayout title="Join the studio">
        <p class="text-sm text-stone-600">
            {{ email }} is invited to join as {{ role.toLowerCase() }}.
        </p>

        <p
            v-if="signedInAs && signedInAs !== email"
            class="rounded bg-amber-50 px-3 py-2 text-sm text-amber-800"
            data-test="wrong-account"
        >
            You are signed in as {{ signedInAs }}. Log out to accept this invitation.
        </p>

        <template v-else-if="hasAccount">
            <p
                v-if="!signedInAs"
                class="text-sm text-stone-600"
            >
                You already have an account here. Log in, then come back to this link.
            </p>
            <Link
                v-if="!signedInAs"
                href="/login"
                class="self-start rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white"
            >
                Log in
            </Link>
            <form
                v-else
                @submit.prevent="submit"
            >
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Accept the invitation
                </button>
            </form>
        </template>

        <form
            v-else
            class="flex flex-col gap-4"
            data-test="new-account"
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
            <FieldError :message="form.errors.name" />
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
            <FieldError :message="form.errors.password" />
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
                Create account and join
            </button>
        </form>
    </GuestLayout>
</template>
