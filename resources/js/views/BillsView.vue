<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../lib/axios';
import BaseModal from '../components/BaseModal.vue';
import { formatCurrency, formatDate, today } from '../lib/format';

const bills = ref([]);
const categories = ref([]);
const users = ref([]);
const loading = ref(true);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });

const filters = reactive({ status: '', scope: '', category_id: '', search: '' });

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const form = reactive({
    name: '', amount: '', due_date: today(), status: 'pending', paid_at: '',
    owner_label: '', responsible_user_id: '', category_id: '', scope: 'apartment',
    recurrence: 'none', notes: '',
});

const scopeLabels = { apartment: 'Apartamento', other: 'Outros' };
const recurrenceLabels = { none: 'Nenhuma', weekly: 'Semanal', monthly: 'Mensal', yearly: 'Anual' };

const canLoadUsers = computed(() => users.value.length > 0);

function effectiveStatus(bill) {
    if (bill.status === 'paid') return 'paid';
    if (bill.due_date && new Date(bill.due_date + 'T00:00:00') < new Date(new Date().toDateString())) return 'overdue';
    return 'pending';
}
const statusStyles = {
    overdue: 'bg-red-100 text-red-700',
    pending: 'bg-amber-100 text-amber-700',
    paid: 'bg-emerald-100 text-emerald-700',
};
const statusLabels = { overdue: 'Vencida', pending: 'Pendente', paid: 'Paga' };

async function loadRefs() {
    const [cats, us] = await Promise.allSettled([
        api.get('/api/categories', { params: { type: 'bill' } }),
        api.get('/api/users'),
    ]);
    if (cats.status === 'fulfilled') categories.value = cats.value.data;
    // /api/users is admin-only; managers get 403, which is fine.
    if (us.status === 'fulfilled') users.value = us.value.data;
}

async function load(page = 1) {
    loading.value = true;
    try {
        const params = { page, per_page: 15 };
        Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
        const { data } = await api.get('/api/bills', { params });
        bills.value = data.data;
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
        name: '', amount: '', due_date: today(), status: 'pending', paid_at: '',
        owner_label: '', responsible_user_id: '', category_id: '', scope: 'apartment',
        recurrence: 'none', notes: '',
    });
    showModal.value = true;
}

function openEdit(bill) {
    editing.value = bill;
    errors.value = {};
    Object.assign(form, {
        name: bill.name,
        amount: bill.amount,
        due_date: bill.due_date,
        status: bill.status,
        paid_at: bill.paid_at ?? '',
        owner_label: bill.owner_label ?? '',
        responsible_user_id: bill.responsible_user_id ?? '',
        category_id: bill.category_id ?? '',
        scope: bill.scope,
        recurrence: bill.recurrence,
        notes: bill.notes ?? '',
    });
    showModal.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            ...form,
            category_id: form.category_id || null,
            responsible_user_id: form.responsible_user_id || null,
            paid_at: form.status === 'paid' ? (form.paid_at || today()) : null,
        };
        if (editing.value) {
            await api.put(`/api/bills/${editing.value.id}`, payload);
        } else {
            await api.post('/api/bills', payload);
        }
        showModal.value = false;
        await load(pagination.current_page);
    } catch (e) {
        if (e?.response?.status === 422) errors.value = e.response.data.errors || {};
    } finally {
        saving.value = false;
    }
}

async function togglePaid(bill) {
    const paid = bill.status !== 'paid';
    await api.patch(`/api/bills/${bill.id}/pay`, { paid });
    await load(pagination.current_page);
}

async function remove(bill) {
    if (!confirm(`Remover a conta "${bill.name}"?`)) return;
    await api.delete(`/api/bills/${bill.id}`);
    await load(pagination.current_page);
}

onMounted(() => { loadRefs(); load(); });
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Contas a pagar</h1>
                <p class="text-sm text-slate-500">{{ pagination.total }} conta(s) registada(s).</p>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700" @click="openCreate">
                + Nova conta
            </button>
        </div>

        <!-- Filters -->
        <div class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            <input v-model="filters.search" type="text" placeholder="Pesquisar…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @input="load(1)" />
            <select v-model="filters.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="load(1)">
                <option value="">Todos os estados</option>
                <option value="overdue">Vencidas</option>
                <option value="pending">Pendentes</option>
                <option value="paid">Pagas</option>
            </select>
            <select v-model="filters.scope" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="load(1)">
                <option value="">Todos os âmbitos</option>
                <option value="apartment">Apartamento</option>
                <option value="other">Outros</option>
            </select>
            <select v-model="filters.category_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="load(1)">
                <option value="">Todas as categorias</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Conta</th>
                        <th class="px-4 py-3">Pertence a</th>
                        <th class="px-4 py-3">Vencimento</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Valor</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading"><td colspan="6" class="px-4 py-10 text-center text-slate-400">A carregar…</td></tr>
                    <tr v-else-if="!bills.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">Sem contas.</td></tr>
                    <tr v-for="b in bills" :key="b.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span v-if="b.category" class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: b.category.color }" />
                                <p class="font-medium text-slate-800">{{ b.name }}</p>
                            </div>
                            <p class="text-xs text-slate-400">
                                {{ scopeLabels[b.scope] }}<span v-if="b.recurrence !== 'none'"> · {{ recurrenceLabels[b.recurrence] }}</span>
                            </p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ b.owner_label || b.responsible_user?.name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDate(b.due_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusStyles[effectiveStatus(b)]">
                                {{ statusLabels[effectiveStatus(b)] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ formatCurrency(b.amount) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button
                                class="rounded-md px-2 py-1 text-xs font-medium"
                                :class="b.status === 'paid' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                                @click="togglePaid(b)"
                            >
                                {{ b.status === 'paid' ? 'Reabrir' : 'Pagar' }}
                            </button>
                            <button class="ml-2 text-indigo-600 hover:underline" @click="openEdit(b)">Editar</button>
                            <button class="ml-2 text-red-500 hover:underline" @click="remove(b)">Remover</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-center gap-2">
            <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</button>
            <span class="text-sm text-slate-500">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
            <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Seguinte</button>
        </div>

        <!-- Modal -->
        <BaseModal :show="showModal" :title="editing ? 'Editar conta' : 'Nova conta'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nome da conta</label>
                    <input v-model="form.name" type="text" placeholder="Ex.: Condomínio, Luz, Água" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name[0] }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Valor (R$)</label>
                        <input v-model="form.amount" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="errors.amount" class="mt-1 text-xs text-red-600">{{ errors.amount[0] }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Vencimento</label>
                        <input v-model="form.due_date" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="errors.due_date" class="mt-1 text-xs text-red-600">{{ errors.due_date[0] }}</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Pertence a</label>
                    <input v-model="form.owner_label" type="text" placeholder="Nome de quem é responsável" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <select v-if="canLoadUsers" v-model="form.responsible_user_id" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Sem utilizador associado</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
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
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
                        <select v-model="form.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="pending">Pendente</option>
                            <option value="paid">Paga</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Recorrência</label>
                        <select v-model="form.recurrence" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="none">Nenhuma</option>
                            <option value="weekly">Semanal</option>
                            <option value="monthly">Mensal</option>
                            <option value="yearly">Anual</option>
                        </select>
                    </div>
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
