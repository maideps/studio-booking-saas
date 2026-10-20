<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard">
        <p class="text-stone-600">
            Welcome back, {{ user?.name }}. Your schedule and bookings will appear here.
        </p>
    </AppLayout>
</template>
