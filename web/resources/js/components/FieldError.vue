<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        class="text-sm text-red-700"
    >
        {{ message }}
    </p>
</template>
