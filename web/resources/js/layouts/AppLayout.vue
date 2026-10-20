<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-stone-200 bg-white">
            <nav class="mx-auto flex max-w-4xl items-center gap-6 px-6 py-3">
                <span class="font-semibold">{{ page.props.tenant?.name }}</span>
                <Link
                    href="/dashboard"
                    class="text-sm text-stone-600 hover:text-stone-900"
                >
                    Dashboard
                </Link>
                <Link
                    v-if="user?.canViewMembers"
                    href="/members"
                    class="text-sm text-stone-600 hover:text-stone-900"
                >
                    Members
                </Link>
                <span class="ml-auto text-sm text-stone-500">{{ user?.name }} ({{ user?.role }})</span>
                <button
                    type="button"
                    class="text-sm text-stone-600 hover:text-stone-900"
                    @click="logout"
                >
                    Log out
                </button>
            </nav>
        </header>
        <main class="mx-auto flex max-w-4xl flex-col gap-6 px-6 py-8">
            <h1 class="text-2xl font-semibold">
                {{ title }}
            </h1>
            <slot />
        </main>
    </div>
</template>
