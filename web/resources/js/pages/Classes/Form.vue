<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import FieldError from '@/components/FieldError.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    template: {
        id: number;
        name: string;
        description: string | null;
        duration_minutes: number;
        capacity: number;
    } | null;
}>();

const title = computed(() => (props.template ? `Edit ${props.template.name}` : 'New class'));

const form = useForm({
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    duration_minutes: props.template?.duration_minutes ?? 60,
    capacity: props.template?.capacity ?? 12,
});

function submit(): void {
    if (props.template) {
        form.put(`/classes/${props.template.id}`);
    } else {
        form.post('/classes');
    }
}
</script>

<template>
    <Head :title="title" />
    <AppLayout :title="title">
        <form
            class="flex max-w-md flex-col gap-4"
            @submit.prevent="submit"
        >
            <label class="flex flex-col gap-1 text-sm">
                Name
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
                Description
                <textarea
                    v-model="form.description"
                    name="description"
                    rows="3"
                    class="rounded border border-stone-300 px-3 py-2"
                />
            </label>
            <FieldError :message="form.errors.description" />
            <label class="flex flex-col gap-1 text-sm">
                Duration (minutes)
                <input
                    v-model.number="form.duration_minutes"
                    type="number"
                    name="duration_minutes"
                    min="5"
                    max="480"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <FieldError :message="form.errors.duration_minutes" />
            <label class="flex flex-col gap-1 text-sm">
                Places
                <input
                    v-model.number="form.capacity"
                    type="number"
                    name="capacity"
                    min="1"
                    max="500"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <FieldError :message="form.errors.capacity" />
            <button
                type="submit"
                :disabled="form.processing"
                class="self-start rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                {{ template ? 'Save class' : 'Create class' }}
            </button>
        </form>
    </AppLayout>
</template>
