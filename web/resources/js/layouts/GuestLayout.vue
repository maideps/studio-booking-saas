<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title: string;
}>();

const page = usePage();
const studioName = computed(() => page.props.tenant?.name ?? page.props.appName);
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-6">
        <header class="flex flex-col gap-1">
            <p class="text-sm font-medium text-stone-500">
                {{ studioName }}
            </p>
            <h1 class="text-2xl font-semibold">
                {{ title }}
            </h1>
        </header>
        <slot />
    </main>
</template>
