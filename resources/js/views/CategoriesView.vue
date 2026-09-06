<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../lib/axios';
import BaseModal from '../components/BaseModal.vue';

const categories = ref([]);
const loading = ref(true);

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const form = reactive({ name: '', type: 'both', color: '#6366f1', description: '' });

const typeLabels = { expense: 'Despesas', bill: 'Contas', both: 'Ambos' };
const palette = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316', '#64748b'];

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/categories');
        categories.value = data;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    errors.value = {};
    Object.assign(form, { name: '', type: 'both', color: '#6366f1', description: '' });
    showModal.value = true;
}

function openEdit(c) {
    editing.value = c;
    errors.value = {};
    Object.assign(form, { name: c.name, type: c.type, color: c.color, description: c.description ?? '' });
    showModal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        if (editing.value) {
            await api.put(`/api/categories/${editing.value.id}`, form);
        } else {
            await api.post('/api/categories', form);
        }
        showModal.value = false;
        await load();
    } catch (e) {
        if (e?.response?.status === 422) errors.value = e.response.data.errors || {};
    } finally {
        saving.value = false;
    }
}

async function remove(c) {
    if (!confirm(`Remover a categoria "${c.name}"? As despesas/contas associadas ficarão sem categoria.`)) return;
    await api.delete(`/api/categories/${c.id}`);
    await load();
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Categorias</h1>
                <p class="text-sm text-slate-500">Organize despesas e contas por categoria.</p>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700" @click="openCreate">
                + Nova categoria
            </button>
        </div>

        <div v-if="loading" class="py-20 text-center text-slate-400">A carregar…</div>
        <div v-else-if="!categories.length" class="rounded-2xl border border-dashed border-slate-300 py-20 text-center text-slate-400">
            Ainda não há categorias. Crie a primeira.
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="c in categories" :key="c.id" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white" :style="{ backgroundColor: c.color }">
                            {{ c.name.charAt(0).toUpperCase() }}
                        </span>
                        <div>
                            <p class="font-semibold text-slate-800">{{ c.name }}</p>
                            <span class="text-xs text-slate-400">{{ typeLabels[c.type] }}</span>
                        </div>
                    </div>
                </div>
                <p v-if="c.description" class="mt-3 text-sm text-slate-500">{{ c.description }}</p>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ c.expenses_count ?? 0 }} despesa(s) · {{ c.bills_count ?? 0 }} conta(s)</span>
                    <div>
                        <button class="text-indigo-600 hover:underline" @click="openEdit(c)">Editar</button>
                        <button class="ml-3 text-red-500 hover:underline" @click="remove(c)">Remover</button>
                    </div>
                </div>
            </div>
        </div>

        <BaseModal :show="showModal" :title="editing ? 'Editar categoria' : 'Nova categoria'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tipo</label>
                    <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="both">Despesas e Contas</option>
                        <option value="expense">Apenas Despesas</option>
                        <option value="bill">Apenas Contas</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Cor</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="color in palette"
                            :key="color"
                            type="button"
                            class="h-8 w-8 rounded-full border-2 transition"
                            :class="form.color === color ? 'border-slate-800 scale-110' : 'border-transparent'"
                            :style="{ backgroundColor: color }"
                            @click="form.color = color"
                        />
                        <input v-model="form.color" type="color" class="h-8 w-8 cursor-pointer rounded" />
                    </div>
                    <p v-if="errors.color" class="mt-1 text-xs text-red-600">{{ errors.color[0] }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
                    <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
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
