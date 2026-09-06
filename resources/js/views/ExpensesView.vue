<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../lib/axios';
import BaseModal from '../components/BaseModal.vue';
import { formatCurrency, formatDate, today } from '../lib/format';

const expenses = ref([]);
const categories = ref([]);
const loading = ref(true);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });

const filters = reactive({ scope: '', category_id: '', search: '', from: '', to: '' });

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const form = reactive({
    description: '', amount: '', spent_on: today(), scope: 'apartment',
    category_id: '', payment_method: '', notes: '',
});

const scopeLabels = { apartment: 'Apartamento', other: 'Outros' };

async function loadCategories() {
    const { data } = await api.get('/api/categories', { params: { type: 'expense' } });
    categories.value = data;
}

async function load(page = 1) {
    loading.value = true;
    try {
        const params = { page, per_page: 15 };
        Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
        const { data } = await api.get('/api/expenses', { params });
        expenses.value = data.data;
        pagination.current_page = data.current_page;
        pagination.last_page = data.last_page;
        pagination.total = data.total;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    errors.value = {};
    Object.assign(form, {
        description: '', amount: '', spent_on: today(), scope: 'apartment',
        category_id: '', payment_method: '', notes: '',
    });
    showModal.value = true;
}

function openEdit(expense) {
    editing.value = expense;
    errors.value = {};
    Object.assign(form, {
        description: expense.description,
        amount: expense.amount,
        spent_on: expense.spent_on,
        scope: expense.scope,
        category_id: expense.category_id ?? '',
        payment_method: expense.payment_method ?? '',
        notes: expense.notes ?? '',
    });
    showModal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { ...form, category_id: form.category_id || null };
        if (editing.value) {
            await api.put(`/api/expenses/${editing.value.id}`, payload);
        } else {
            await api.post('/api/expenses', payload);
        }
        showModal.value = false;
        await load(pagination.current_page);
    } catch (e) {
        if (e?.response?.status === 422) errors.value = e.response.data.errors || {};
    } finally {
        saving.value = false;
    }
}

async function remove(expense) {
    if (!confirm(`Remover a despesa "${expense.description}"?`)) return;
    await api.delete(`/api/expenses/${expense.id}`);
    await load(pagination.current_page);
}

onMounted(() => { loadCategories(); load(); });
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Despesas</h1>
                <p class="text-sm text-slate-500">{{ pagination.total }} despesa(s) registada(s).</p>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700" @click="openCreate">
                + Nova despesa
            </button>
        </div>

        <!-- Filters -->
        <div class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input v-model="filters.search" type="text" placeholder="Pesquisar…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @input="load(1)" />
            <select v-model="filters.scope" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="load(1)">
                <option value="">Todos os âmbitos</option>
                <option value="apartment">Apartamento</option>
                <option value="other">Outros</option>
            </select>
            <select v-model="filters.category_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="load(1)">
                <option value="">Todas as categorias</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <input v-model="filters.from" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="load(1)" />
            <input v-model="filters.to" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="load(1)" />
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Descrição</th>
                        <th class="px-4 py-3">Categoria</th>
                        <th class="px-4 py-3">Âmbito</th>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3 text-right">Valor</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading"><td colspan="6" class="px-4 py-10 text-center text-slate-400">A carregar…</td></tr>
                    <tr v-else-if="!expenses.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">Sem despesas.</td></tr>
                    <tr v-for="e in expenses" :key="e.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ e.description }}</p>
                            <p v-if="e.notes" class="text-xs text-slate-400">{{ e.notes }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="e.category" class="inline-flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: e.category.color }" />
                                {{ e.category.name }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ scopeLabels[e.scope] }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDate(e.spent_on) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ formatCurrency(e.amount) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button class="text-indigo-600 hover:underline" @click="openEdit(e)">Editar</button>
                            <button class="ml-3 text-red-500 hover:underline" @click="remove(e)">Remover</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-center gap-2">
            <button
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40"
                :disabled="pagination.current_page <= 1"
                @click="load(pagination.current_page - 1)"
            >Anterior</button>
            <span class="text-sm text-slate-500">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
            <button
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="load(pagination.current_page + 1)"
            >Seguinte</button>
        </div>

        <!-- Modal -->
        <BaseModal :show="showModal" :title="editing ? 'Editar despesa' : 'Nova despesa'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
                    <input v-model="form.description" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="errors.description" class="mt-1 text-xs text-red-600">{{ errors.description[0] }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Valor (R$)</label>
                        <input v-model="form.amount" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="errors.amount" class="mt-1 text-xs text-red-600">{{ errors.amount[0] }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Data</label>
                        <input v-model="form.spent_on" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="errors.spent_on" class="mt-1 text-xs text-red-600">{{ errors.spent_on[0] }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Âmbito</label>
                        <select v-model="form.scope" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="apartment">Apartamento</option>
                            <option value="other">Outros</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Categoria</label>
                        <select v-model="form.category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Sem categoria</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Forma de pagamento</label>
                    <input v-model="form.payment_method" type="text" placeholder="Ex.: Cartão, PIX, Dinheiro" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Notas</label>
                    <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
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
