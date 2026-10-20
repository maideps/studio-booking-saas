<script setup lang="ts">
defineProps<{
    status?: string | null;
}>();
</script>

<template>
    <p
        v-if="status"
        class="rounded bg-green-50 px-3 py-2 text-sm text-green-800"
    >
        {{ status }}
    </p>
</template>
