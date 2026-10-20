<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import StatusMessage from '@/components/StatusMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface ClassTemplate {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    capacity: number;
    canUpdate: boolean;
}

defineProps<{
    templates: ClassTemplate[];
    canCreate: boolean;
    status?: string | null;
}>();
</script>

<template>
    <Head title="Classes" />
    <AppLayout title="Classes">
        <StatusMessage :status="status" />
        <Link
            v-if="canCreate"
            href="/classes/create"
            class="self-start rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white"
        >
            New class
        </Link>
        <p
            v-if="templates.length === 0"
            class="text-stone-600"
        >
            No classes yet.
        </p>
        <ul class="flex flex-col gap-3">
            <li
                v-for="template in templates"
                :key="template.id"
                class="rounded border border-stone-200 bg-white p-4"
                data-test="class-template"
            >
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="font-medium">
                        {{ template.name }}
                    </h2>
                    <Link
                        v-if="template.canUpdate"
                        :href="`/classes/${template.id}/edit`"
                        class="text-sm text-stone-600 hover:text-stone-900"
                    >
                        Edit
                    </Link>
                </div>
                <p class="text-sm text-stone-500">
                    {{ template.duration_minutes }} minutes · {{ template.capacity }} places
                </p>
                <p
                    v-if="template.description"
                    class="mt-1 text-sm text-stone-600"
                >
                    {{ template.description }}
                </p>
            </li>
        </ul>
    </AppLayout>
</template>
