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

// --- Receipt / camera state ---
// The camera capture feature is only offered on phones. `capture="environment"`
// opens the rear camera directly on mobile; on desktop it is meaningless, so we
// hide the whole affordance there.
const isMobile = detectMobile();
const receiptFile = ref(null);       // File selected/captured this session
const receiptPreview = ref(null);    // Object URL for the freshly picked file
const existingReceiptUrl = ref(null); // Receipt already stored on the expense
const removeReceipt = ref(false);    // Flag to detach an existing receipt
const cameraInput = ref(null);       // Hidden <input capture> used by quick action
const scanning = ref(false);         // True while OpenAI reads the receipt
const scanNotice = ref('');          // Feedback shown after a scan attempt

function detectMobile() {
    if (typeof navigator === 'undefined') return false;
    const uaMobile = /Android|iPhone|iPad|iPod|Windows Phone|webOS|BlackBerry|Opera Mini|IEMobile/i
        .test(navigator.userAgent);
    const coarsePointer = typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(pointer: coarse)').matches;
    const hasTouch = typeof navigator !== 'undefined' && navigator.maxTouchPoints > 0;
    return uaMobile || (coarsePointer && hasTouch);
}

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

function resetReceiptState() {
    if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value);
    receiptFile.value = null;
    receiptPreview.value = null;
    existingReceiptUrl.value = null;
    removeReceipt.value = false;
    scanning.value = false;
    scanNotice.value = '';
}

function resetForm() {
    Object.assign(form, {
        description: '', amount: '', spent_on: today(), scope: 'apartment',
        category_id: '', payment_method: '', notes: '',
    });
}

function openCreate() {
    editing.value = null;
    errors.value = {};
    resetForm();
    resetReceiptState();
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
    resetReceiptState();
    existingReceiptUrl.value = expense.receipt_url ?? null;
    showModal.value = true;
}

// Quick action (mobile): tap the camera button, snap a photo, and jump straight
// into a pre-filled "new expense" form with the receipt already attached.
function startCameraCapture() {
    cameraInput.value?.click();
}

function onReceiptSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    // If we weren't already editing/creating, open a fresh create form.
    if (!showModal.value) {
        openCreate();
    }

    if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value);
    receiptFile.value = file;
    receiptPreview.value = URL.createObjectURL(file);
    removeReceipt.value = false;
    // Reset the input so selecting the same file again still fires change.
    event.target.value = '';

    // Read the receipt and pre-fill the form (user reviews before saving).
    scanReceipt(file);
}

// Send the photo to the backend, which asks OpenAI to extract the fields.
async function scanReceipt(file) {
    scanning.value = true;
    scanNotice.value = '';
    try {
        const fd = new FormData();
        fd.append('receipt', file);
        const { data } = await api.post('/api/expenses/scan-receipt', fd);
        applySuggestions(data.suggestions);
    } catch (e) {
        scanNotice.value = e?.response?.data?.message
            || 'Não foi possível ler o recibo. Preencha os campos manualmente.';
    } finally {
        scanning.value = false;
    }
}

// Only overwrite fields the model actually returned; leave the rest untouched.
function applySuggestions(s) {
    if (!s || typeof s !== 'object') return;
    const filled = [];
    if (s.description) { form.description = s.description; filled.push('descrição'); }
    if (s.amount != null) { form.amount = s.amount; filled.push('valor'); }
    if (s.spent_on) { form.spent_on = s.spent_on; filled.push('data'); }
    if (s.payment_method) { form.payment_method = s.payment_method; filled.push('pagamento'); }
    if (s.category_id != null) { form.category_id = s.category_id; filled.push('categoria'); }

    scanNotice.value = filled.length
        ? `Campos preenchidos a partir do recibo: ${filled.join(', ')}. Reveja antes de guardar.`
        : 'Não foi possível extrair dados do recibo. Preencha manualmente.';
}

function clearReceipt() {
    if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value);
    receiptFile.value = null;
    receiptPreview.value = null;
    // If there was a stored receipt, mark it for removal on save.
    if (existingReceiptUrl.value) {
        removeReceipt.value = true;
        existingReceiptUrl.value = null;
    }
}

function buildPayload() {
    const fd = new FormData();
    fd.append('description', form.description ?? '');
    fd.append('amount', form.amount ?? '');
    fd.append('spent_on', form.spent_on ?? '');
    fd.append('scope', form.scope ?? '');
    if (form.category_id) fd.append('category_id', form.category_id);
    if (form.payment_method) fd.append('payment_method', form.payment_method);
    if (form.notes) fd.append('notes', form.notes);
    if (receiptFile.value) fd.append('receipt', receiptFile.value);
    if (removeReceipt.value) fd.append('remove_receipt', '1');
    return fd;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const fd = buildPayload();
        if (editing.value) {
            // Laravel reads multipart bodies only on POST, so spoof the method.
            fd.append('_method', 'PUT');
            await api.post(`/api/expenses/${editing.value.id}`, fd);
        } else {
            await api.post('/api/expenses', fd);
        }
        showModal.value = false;
        resetReceiptState();
        await load(editing.value ? pagination.current_page : 1);
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
            <div class="flex items-center gap-2">
                <!-- Mobile-only: capture a receipt photo straight from the camera. -->
                <button
                    v-if="isMobile"
                    class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                    @click="startCameraCapture"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Fotografar recibo
                </button>
                <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700" @click="openCreate">
                    + Nova despesa
                </button>
            </div>
        </div>

        <!-- Hidden camera input — only rendered on mobile. accept+capture opens the rear camera. -->
        <input
            v-if="isMobile"
            ref="cameraInput"
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            @change="onReceiptSelected"
        />

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
                            <p class="font-medium text-slate-800 flex items-center gap-1.5">
                                <a
                                    v-if="e.receipt_url"
                                    :href="e.receipt_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-emerald-600"
                                    title="Ver recibo"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </a>
                                {{ e.description }}
                            </p>
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

                <!-- Receipt: capture available only on mobile; preview/removal always available. -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Recibo</label>
                    <div class="flex items-center gap-3">
                        <div v-if="receiptPreview || existingReceiptUrl" class="relative">
                            <img
                                :src="receiptPreview || existingReceiptUrl"
                                alt="Recibo"
                                class="h-20 w-20 rounded-lg border border-slate-200 object-cover"
                            />
                            <button
                                type="button"
                                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white"
                                title="Remover recibo"
                                @click="clearReceipt"
                            >×</button>
                        </div>
                        <button
                            v-if="isMobile"
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100"
                            @click="startCameraCapture"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ (receiptPreview || existingReceiptUrl) ? 'Substituir' : 'Fotografar recibo' }}
                        </button>
                        <p v-else class="text-xs text-slate-400">A captura de recibo só está disponível no telemóvel.</p>
                    </div>
                    <p v-if="scanning" class="mt-2 flex items-center gap-2 text-xs text-emerald-600">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        A ler o recibo…
                    </p>
                    <p v-else-if="scanNotice" class="mt-2 text-xs text-slate-500">{{ scanNotice }}</p>
                    <p v-if="errors.receipt" class="mt-1 text-xs text-red-600">{{ errors.receipt[0] }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm" @click="showModal = false">Cancelar</button>
                    <button type="submit" :disabled="saving || scanning" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                        {{ saving ? 'A guardar…' : (scanning ? 'A ler recibo…' : 'Guardar') }}
                    </button>
                </div>
            </form>
        </BaseModal>
    </div>
</template>
