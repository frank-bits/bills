<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'

type Transaction = {
    id: number
    transaction: string
    account_type_id: number | null
    account_type?: { id: number; name: string }
    account_id: number | null
    account?: { id: number; name: string }
    amount: number
    date: string
    created_at: string
    updated_at: string
}

type NewTransaction = {
    transaction: string
    account_type_id: number | null
    account_id?: number | null
    amount: number
    date: string
}

const breadcrumbs = [
    { title: 'Transactions', href: '/transactions' },
]

const start = ref<string>('')
const end = ref<string>('')
const search = ref<string>('')
const perPage = ref<number>(50)
const page = ref<number>(1)
const sortBy = ref<'date' | 'transaction' | 'amount' | 'account_type' | 'account'>('date')
const sortDir = ref<'asc' | 'desc'>('desc')

const loading = ref(false)
const rows = ref<Transaction[]>([])
const total = ref<number>(0)
const lastPage = ref<number>(1)
const totalItems = ref<number>(0)
const importing = ref(false)
const importResult = ref<{ inserted: number; skipped: number; errors: string[] } | null>(null)

const newItem = ref<NewTransaction>({ transaction: '', account_type_id: null, account_id: null, amount: 0, date: '' })
const savingNew = ref(false)

const accountTypes = ref<Array<{ id: number; name: string }>>([])
const accountOptions = ref<Array<{ id: number; name: string }>>([])

async function loadAccountTypes() {
    const res = await fetch('/account-types', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    accountTypes.value = await res.json()
}

async function loadAccounts() {
    const res = await fetch('/accounts/options', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    accountOptions.value = await res.json()
}

const queryParams = computed(() => ({
    start: start.value || undefined,
    end: end.value || undefined,
    search: search.value || undefined,
    page: page.value,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
}))

function toQuery(q: Record<string, unknown>) {
    const params = new URLSearchParams()
    Object.entries(q).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') params.set(k, String(v))
    })
    const s = params.toString()
    return s ? `?${s}` : ''
}

async function fetchData() {
    loading.value = true
    try {
        const url = `/transactions/data${toQuery(queryParams.value)}`
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        const data = await res.json()
    rows.value = data.transactions.data
        total.value = data.running_total
        page.value = data.transactions.current_page
        lastPage.value = data.transactions.last_page
        totalItems.value = data.transactions.total
    } finally {
        loading.value = false
    }
}

function toggleSort(col: 'date' | 'transaction' | 'amount' | 'account_type' | 'account') {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = col
        sortDir.value = col === 'date' ? 'desc' : 'asc'
    }
    page.value = 1
    fetchData()
}

async function uploadCsv(e: Event) {
    const input = e.target as HTMLInputElement
    if (!input.files || input.files.length === 0) return
    const file = input.files[0]
    const form = new FormData()
    form.append('file', file)
    importing.value = true
    importResult.value = null
    try {
        const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
        const res = await fetch('/transactions/import', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            body: form,
        })
        importResult.value = await res.json()
        await fetchData()
    } finally {
        importing.value = false
        input.value = ''
    }
}

async function createItem() {
    savingNew.value = true
    try {
        const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
        const res = await fetch('/transactions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(newItem.value),
        })
        if (res.ok) {
            Object.assign(newItem.value, { transaction: '', account_type_id: null, account_id: null, amount: 0, date: '' })
            await fetchData()
        }
    } finally {
        savingNew.value = false
    }
}

async function updateItem(item: Transaction) {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
    await fetch(`/transactions/${item.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(item),
    })
    await fetchData()
}

async function deleteItem(item: Transaction) {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
    await fetch(`/transactions/${item.id}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token } })
    await fetchData()
}

watch([start, end, perPage], () => {
    page.value = 1
    fetchData()
})

watch(search, () => {
    page.value = 1
    // debounce search
    clearTimeout((watch as any)._t)
        ; (watch as any)._t = setTimeout(fetchData, 300)
})

watch(page, fetchData)

Promise.all([loadAccountTypes(), loadAccounts(), fetchData()])
</script>

<template>

    <Head title="Transactions" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end">
                <div class="flex flex-col">
                    <label class="text-xs text-muted-foreground" for="start">Start</label>
                    <input id="start" type="date" v-model="start" class="rounded border px-3 py-2" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-muted-foreground" for="end">End</label>
                    <input id="end" type="date" v-model="end" class="rounded border px-3 py-2" />
                </div>
                <div class="flex flex-col md:flex-1">
                    <label class="text-xs text-muted-foreground" for="search">Search</label>
                    <input id="search" type="text" placeholder="Transaction or account type…" v-model="search"
                        class="w-full rounded border px-3 py-2" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-muted-foreground" for="perPage">Per page</label>
                    <select id="perPage" v-model.number="perPage" class="rounded border px-3 py-2">
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between rounded border px-4 py-3">
                <div class="text-sm">Running total for range</div>
                <div class="text-xl font-semibold">{{ new Intl.NumberFormat(undefined, {
                    style: 'currency', currency:
                        'USD'
                }).format(total) }}</div>
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <input type="file" accept=".csv,text/csv" @change="uploadCsv" />
                    <a href="/transactions/template" class="text-sm underline">Download CSV template</a>
                    <span v-if="importing" class="text-sm text-muted-foreground">Importing…</span>
                    <span v-else-if="importResult" class="text-sm text-muted-foreground">Inserted {{
                        importResult.inserted }}, skipped {{ importResult.skipped }}</span>
                </div>
                <ul v-if="importResult?.errors?.length" class="list-disc pl-6 text-sm text-red-600">
                    <li v-for="(err, i) in (importResult?.errors || [])" :key="i">{{ err }}</li>
                </ul>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-muted text-left">
                            <th class="p-2 cursor-pointer select-none" @click="toggleSort('date')">
                                Date <span v-if="sortBy==='date'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="p-2 cursor-pointer select-none" @click="toggleSort('transaction')">
                                Transaction <span v-if="sortBy==='transaction'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="p-2 cursor-pointer select-none" @click="toggleSort('account_type')">
                                Account Type <span v-if="sortBy==='account_type'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="p-2 cursor-pointer select-none" @click="toggleSort('account')">
                                Account <span v-if="sortBy==='account'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="p-2 text-right cursor-pointer select-none" @click="toggleSort('amount')">
                                Amount <span v-if="sortBy==='amount'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="p-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="6" class="p-4 text-center text-muted-foreground">Loading…</td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="6" class="p-4 text-center text-muted-foreground">No results</td>
                        </tr>
                        <tr v-else v-for="row in rows" :key="row.id" class="border-t">
                            <td class="p-2 whitespace-nowrap">{{ new Date(row.date).toLocaleDateString() }}</td>
                            <td class="p-2"><input v-model="row.transaction" class="w-full rounded border px-2 py-1" />
                            </td>
                             <td class="p-2">
                                <select v-model.number="row.account_type_id" class="rounded border px-2 py-1">
                                    <option :value="null">-- choose type --</option>
                                    <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <div class="text-xs text-muted-foreground" v-if="row.account_type?.name">
                                    {{ row.account_type.name }}
                                </div>
                            </td>
                            <td class="p-2">
                                <select v-model.number="row.account_id" class="rounded border px-2 py-1">
                                    <option :value="null">-- no account --</option>
                                    <option v-for="a in accountOptions" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                                <div class="text-xs text-muted-foreground" v-if="row.account?.name">
                                    {{ row.account.name }}
                                </div>
                            </td>

                            <td class="p-2 text-right">
                                <input type="number" step="0.01" v-model.number="row.amount"
                                    class="w-32 rounded border px-2 py-1 text-right" />
                            </td>
                            <td class="p-2 text-right">
                                <button class="rounded border px-2 py-1 mr-2" @click="updateItem(row)">Save</button>
                                <button class="rounded border px-2 py-1" @click="deleteItem(row)">Delete</button>
                            </td>
                        </tr>
                        <tr class="border-t bg-muted/30">
                            <td class="p-2"><input type="date" v-model="newItem.date"
                                    class="rounded border px-2 py-1" /></td>
                            <td class="p-2"><input v-model="newItem.transaction" placeholder="Ref/desc"
                                    class="w-full rounded border px-2 py-1" /></td>
                            <td class="p-2">
                                <select v-model.number="newItem.account_type_id" class="rounded border px-2 py-1">
                                    <option :value="null">-- choose type --</option>
                                    <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </td>
                            <td class="p-2">
                                <select v-model.number="newItem.account_id" class="rounded border px-2 py-1">
                                    <option :value="null">-- no account --</option>
                                    <option v-for="a in accountOptions" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </td>
                            <td class="p-2 text-right"><input type="number" step="0.01" v-model.number="newItem.amount"
                                    class="w-32 rounded border px-2 py-1 text-right" /></td>
                            <td class="p-2 text-right"><button class="rounded border px-2 py-1" :disabled="savingNew"
                                    @click="createItem">Add</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between">
                <div class="text-sm text-muted-foreground">Showing page {{ page }} of {{ lastPage }} ({{ totalItems }}
                    items)</div>
                <div class="flex gap-2">
                    <button class="rounded border px-3 py-1 disabled:opacity-50" :disabled="page <= 1"
                        @click="page = Math.max(1, page - 1)">Prev</button>
                    <button class="rounded border px-3 py-1 disabled:opacity-50" :disabled="page >= lastPage"
                        @click="page = Math.min(lastPage, page + 1)">Next</button>
                </div>
            </div>
        </div>
    </AppLayout>

</template>

<style scoped>
.bg-muted {
    background-color: rgba(0, 0, 0, 0.03);
}

.text-muted-foreground {
    color: rgba(0, 0, 0, 0.6);
}
</style>
