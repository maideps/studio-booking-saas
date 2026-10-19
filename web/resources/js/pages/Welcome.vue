<script setup lang="ts">
defineProps<{
    appName: string;
}>();
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-2xl flex-col justify-center gap-4 px-6">
        <h1 class="text-3xl font-semibold">
            {{ appName }}
        </h1>
        <p class="text-stone-600">
            Book classes and appointments at your studio. Schedules, bookings and memberships are on the way.
        </p>
    </main>
</template>
