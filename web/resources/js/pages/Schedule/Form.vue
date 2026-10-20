<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import FieldError from '@/components/FieldError.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    session: {
        id: number;
        class_template_id: number;
        instructor_id: number;
        starts_at: string;
        capacity: number;
    } | null;
    templates: { id: number; name: string; capacity: number }[];
    instructors: { id: number; name: string }[];
    timezone: string;
}>();

const title = computed(() => (props.session ? 'Edit session' : 'Schedule a session'));

const form = useForm({
    class_template_id: props.session?.class_template_id ?? props.templates[0]?.id ?? null,
    instructor_id: props.session?.instructor_id ?? props.instructors[0]?.id ?? null,
    starts_at: props.session?.starts_at ?? '',
    capacity: props.session?.capacity ?? props.templates[0]?.capacity ?? 12,
});

// A new session takes the template's default capacity until it is changed by hand.
watch(
    () => form.class_template_id,
    (id) => {
        const template = props.templates.find((candidate) => candidate.id === id);
        if (!props.session && template) {
            form.capacity = template.capacity;
        }
    },
);

function submit(): void {
    if (props.session) {
        form.put(`/schedule/${props.session.id}`);
    } else {
        form.post('/schedule');
    }
}
</script>

<template>
    <Head :title="title" />
    <AppLayout :title="title">
        <p
            v-if="templates.length === 0"
            class="text-stone-600"
        >
            Create a class first, then schedule sessions of it.
        </p>
        <form
            v-else
            class="flex max-w-md flex-col gap-4"
            @submit.prevent="submit"
        >
            <label class="flex flex-col gap-1 text-sm">
                Class
                <select
                    v-model="form.class_template_id"
                    name="class_template_id"
                    class="rounded border border-stone-300 px-3 py-2"
                >
                    <option
                        v-for="template in templates"
                        :key="template.id"
                        :value="template.id"
                    >
                        {{ template.name }}
                    </option>
                </select>
            </label>
            <FieldError :message="form.errors.class_template_id" />
            <label class="flex flex-col gap-1 text-sm">
                Instructor
                <select
                    v-model="form.instructor_id"
                    name="instructor_id"
                    class="rounded border border-stone-300 px-3 py-2"
                >
                    <option
                        v-for="instructor in instructors"
                        :key="instructor.id"
                        :value="instructor.id"
                    >
                        {{ instructor.name }}
                    </option>
                </select>
            </label>
            <FieldError :message="form.errors.instructor_id" />
            <label class="flex flex-col gap-1 text-sm">
                Starts at ({{ timezone }})
                <input
                    v-model="form.starts_at"
                    type="datetime-local"
                    name="starts_at"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <FieldError :message="form.errors.starts_at" />
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
                {{ session ? 'Save session' : 'Schedule session' }}
            </button>
        </form>
    </AppLayout>
</template>
