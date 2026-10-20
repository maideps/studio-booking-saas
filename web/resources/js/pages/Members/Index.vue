<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import type { Role } from '@/types/inertia';

interface Member {
    id: number;
    name: string;
    email: string;
    role: Role;
    canUpdateRole: boolean;
}

defineProps<{
    members: Member[];
    roles: { value: Role; label: string }[];
    status?: string | null;
}>();

function changeRole(member: Member, event: Event): void {
    const role = (event.target as HTMLSelectElement).value;
    router.patch(`/members/${member.id}/role`, { role }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Members" />
    <AppLayout title="Members">
        <p
            v-if="status"
            class="rounded bg-green-50 px-3 py-2 text-sm text-green-800"
        >
            {{ status }}
        </p>
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 text-stone-500">
                <tr>
                    <th class="py-2 font-medium">
                        Name
                    </th>
                    <th class="py-2 font-medium">
                        Email
                    </th>
                    <th class="py-2 font-medium">
                        Role
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="member in members"
                    :key="member.id"
                    class="border-b border-stone-100"
                    data-test="member"
                >
                    <td class="py-2">
                        {{ member.name }}
                    </td>
                    <td class="py-2">
                        {{ member.email }}
                    </td>
                    <td class="py-2">
                        <select
                            v-if="member.canUpdateRole"
                            :value="member.role"
                            :aria-label="`Role of ${member.name}`"
                            class="rounded border border-stone-300 px-2 py-1"
                            @change="changeRole(member, $event)"
                        >
                            <option
                                v-for="role in roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                        <span v-else>{{ roles.find((role) => role.value === member.role)?.label ?? member.role }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </AppLayout>
</template>
