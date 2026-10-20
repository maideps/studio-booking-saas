<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import FieldError from '@/components/FieldError.vue';
import StatusMessage from '@/components/StatusMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Role } from '@/types/inertia';

interface Member {
    id: number;
    name: string;
    email: string;
    role: Role;
    canUpdateRole: boolean;
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    expiresAt: string;
}

withDefaults(defineProps<{
    members: Member[];
    roles: { value: Role; label: string }[];
    canInvite?: boolean;
    invitations?: Invitation[];
    status?: string | null;
}>(), {
    canInvite: false,
    invitations: () => [],
    status: null,
});

const invite = useForm({
    email: '',
    role: 'staff',
});

function changeRole(member: Member, event: Event): void {
    const role = (event.target as HTMLSelectElement).value;
    router.patch(`/members/${member.id}/role`, { role }, { preserveScroll: true });
}

function sendInvitation(): void {
    invite.post('/invitations', { preserveScroll: true, onSuccess: () => invite.reset('email') });
}

function revoke(invitation: Invitation): void {
    router.delete(`/invitations/${invitation.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Members" />
    <AppLayout title="Members">
        <StatusMessage :status="status" />
        <form
            v-if="canInvite"
            class="flex flex-wrap items-end gap-3 rounded border border-stone-200 bg-white p-4"
            data-test="invite-form"
            @submit.prevent="sendInvitation"
        >
            <label class="flex flex-col gap-1 text-sm">
                Invite by email
                <input
                    v-model="invite.email"
                    type="email"
                    name="email"
                    required
                    class="rounded border border-stone-300 px-3 py-2"
                >
            </label>
            <label class="flex flex-col gap-1 text-sm">
                As
                <select
                    v-model="invite.role"
                    name="role"
                    class="rounded border border-stone-300 px-3 py-2"
                >
                    <option value="staff">
                        Staff
                    </option>
                    <option value="owner">
                        Owner
                    </option>
                </select>
            </label>
            <button
                type="submit"
                :disabled="invite.processing"
                class="rounded bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                Send invitation
            </button>
            <FieldError
                class="w-full"
                :message="invite.errors.email ?? invite.errors.role"
            />
        </form>
        <section
            v-if="invitations.length > 0"
            class="flex flex-col gap-2"
        >
            <h2 class="font-medium">
                Pending invitations
            </h2>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex items-center gap-3"
                    data-test="invitation"
                >
                    <span>{{ invitation.email }} ({{ invitation.role.toLowerCase() }}), expires {{ invitation.expiresAt.replace('T', ' ') }}</span>
                    <button
                        type="button"
                        class="text-stone-600 hover:text-stone-900"
                        @click="revoke(invitation)"
                    >
                        Revoke
                    </button>
                </li>
            </ul>
        </section>
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
