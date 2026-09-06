<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../lib/axios';
import BaseModal from '../components/BaseModal.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const users = ref([]);
const loading = ref(true);

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const form = reactive({ name: '', email: '', role: 'manager', password: '' });

const roleLabels = { admin: 'Administrador', manager: 'Gestor' };
const roleStyles = { admin: 'bg-indigo-100 text-indigo-700', manager: 'bg-slate-100 text-slate-600' };

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/users');
        users.value = data;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    errors.value = {};
    Object.assign(form, { name: '', email: '', role: 'manager', password: '' });
    showModal.value = true;
}

function openEdit(u) {
    editing.value = u;
    errors.value = {};
    Object.assign(form, { name: u.name, email: u.email, role: u.role, password: '' });
    showModal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { ...form };
        if (editing.value && !payload.password) delete payload.password;
        if (editing.value) {
            await api.put(`/api/users/${editing.value.id}`, payload);
        } else {
            await api.post('/api/users', payload);
        }
        showModal.value = false;
        await load();
    } catch (e) {
        if (e?.response?.status === 422) errors.value = e.response.data.errors || {};
    } finally {
        saving.value = false;
    }
}

async function remove(u) {
    if (u.id === auth.user?.id) {
        alert('Não pode remover a sua própria conta.');
        return;
    }
    if (!confirm(`Remover o utilizador "${u.name}"?`)) return;
    try {
        await api.delete(`/api/users/${u.id}`);
        await load();
    } catch (e) {
        alert(e?.response?.data?.message || 'Não foi possível remover.');
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Utilizadores</h1>
                <p class="text-sm text-slate-500">Gestão de contas de acesso (apenas administradores).</p>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700" @click="openCreate">
                + Novo utilizador
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">E-mail</th>
                        <th class="px-4 py-3">Perfil</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading"><td colspan="4" class="px-4 py-10 text-center text-slate-400">A carregar…</td></tr>
                    <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                                    {{ u.name.charAt(0).toUpperCase() }}
                                </span>
                                <span class="font-medium text-slate-800">{{ u.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ u.email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="roleStyles[u.role]">{{ roleLabels[u.role] }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button class="text-indigo-600 hover:underline" @click="openEdit(u)">Editar</button>
                            <button v-if="u.id !== auth.user?.id" class="ml-3 text-red-500 hover:underline" @click="remove(u)">Remover</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal :show="showModal" :title="editing ? 'Editar utilizador' : 'Novo utilizador'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email[0] }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Perfil</label>
                    <select v-model="form.role" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="manager">Gestor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        {{ editing ? 'Nova palavra-passe (deixe em branco para manter)' : 'Palavra-passe' }}
                    </label>
                    <input v-model="form.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password[0] }}</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm" @click="showModal = false">Cancelar</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                        {{ saving ? 'A guardar…' : 'Guardar' }}
                    </button>
                </div>
            </form>
        </BaseModal>
    </div>
</template>
