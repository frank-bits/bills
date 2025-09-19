<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

type Account = {
  id: number
  name: string
  account_type_id: number
  account_type?: { id: number; name: string }
  balance: number | null
  due: string | null
  avoid_interest_date: string | null
  monthly_due_date_day?: number | null
  next_due_date?: string | null
}

type NewAccount = {
  name: string
  account_type_id?: number
  balance: number | null
  due: string | null
  avoid_interest_date: string | null
  monthly_due_date_day?: number | null
}

const breadcrumbs = [{ title: 'Accounts', href: '/accounts' }]

const accountTypes = ref<Array<{ id: number; name: string }>>([])
const rows = ref<Account[]>([])
const page = ref(1)
const perPage = ref(15)
const lastPage = ref(1)
const totalItems = ref(0)
const search = ref('')
const loading = ref(false)

const newItem = ref<NewAccount>({ name: '', account_type_id: undefined, balance: 0, due: null, avoid_interest_date: null })
const saving = ref(false)

async function loadAccountTypes() {
  const res = await fetch('/account-types', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  accountTypes.value = await res.json()
}

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
    const url = `/accounts/data${toQuery({ page: page.value, per_page: perPage.value, search: search.value || undefined })}`
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    const data = await res.json()
    rows.value = data.accounts.data
    page.value = data.accounts.current_page
    lastPage.value = data.accounts.last_page
    totalItems.value = data.accounts.total
  } finally {
    loading.value = false
  }
}

async function createAccount() {
  saving.value = true
  try {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
    const res = await fetch('/accounts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
      body: JSON.stringify(newItem.value),
    })
    if (res.ok) {
  newItem.value = { name: '', account_type_id: undefined, balance: 0, due: null, avoid_interest_date: null }
      await fetchData()
    }
  } finally {
    saving.value = false
  }
}

async function updateAccount(acc: Account) {
  const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
  await fetch(`/accounts/${acc.id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
    body: JSON.stringify(acc),
  })
  await fetchData()
}

async function deleteAccount(acc: Account) {
  const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
  await fetch(`/accounts/${acc.id}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token } })
  await fetchData()
}

watch([page, perPage], fetchData)
watch(search, () => { page.value = 1; fetchData() })

Promise.all([loadAccountTypes(), fetchData()])
</script>

<template>
  <Head title="Accounts" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
      <div class="flex items-end gap-3">
        <div class="flex flex-col md:flex-1">
          <label class="text-xs text-muted-foreground" for="search">Search</label>
          <input id="search" v-model="search" class="w-full rounded border px-3 py-2" placeholder="Name or account type" />
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

      <div class="overflow-x-auto">
        <table class="min-w-full border">
          <thead>
            <tr class="bg-muted text-left">
              <th class="p-2">Name</th>
              <th class="p-2">Type</th>
              <th class="p-2">Balance</th>
              <th class="p-2">Due</th>
              <th class="p-2">Avoid Interest Date</th>
              <th class="p-2">Monthly Due Day</th>
              <th class="p-2">Next Due</th>
              <th class="p-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="8" class="p-4 text-center text-muted-foreground">Loading…</td></tr>
            <tr v-else-if="rows.length === 0"><td colspan="8" class="p-4 text-center text-muted-foreground">No results</td></tr>
            <tr v-else v-for="acc in rows" :key="acc.id" class="border-t">
              <td class="p-2"><input v-model="acc.name" class="w-full rounded border px-2 py-1" /></td>
              <td class="p-2">
                <select v-model.number="acc.account_type_id" class="rounded border px-2 py-1">
                  <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </td>
              <td class="p-2"><input type="number" step="0.01" v-model.number="acc.balance" class="w-32 rounded border px-2 py-1 text-right" /></td>
              <td class="p-2"><input type="date" v-model="acc.due" class="rounded border px-2 py-1" /></td>
              <td class="p-2"><input type="date" v-model="acc.avoid_interest_date" class="rounded border px-2 py-1" /></td>
              <td class="p-2"><input type="number" min="1" max="31" v-model.number="acc.monthly_due_date_day" class="w-24 rounded border px-2 py-1 text-right" /></td>
              <td class="p-2">
                <span class="text-sm text-muted-foreground">{{ acc.next_due_date || '—' }}</span>
              </td>
              <td class="p-2 text-right">
                <button class="rounded border px-2 py-1 mr-2" @click="updateAccount(acc)">Save</button>
                <button class="rounded border px-2 py-1" @click="deleteAccount(acc)">Delete</button>
              </td>
            </tr>
            <tr class="border-t bg-muted/30">
              <td class="p-2"><input v-model="newItem.name" placeholder="Account name" class="w-full rounded border px-2 py-1" /></td>
              <td class="p-2">
                <select v-model.number="newItem.account_type_id" class="rounded border px-2 py-1">
                  <option :value="undefined">-- choose type --</option>
                  <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </td>
              <td class="p-2"><input type="number" step="0.01" v-model.number="newItem.balance" class="w-32 rounded border px-2 py-1 text-right" /></td>
              <td class="p-2"><input type="date" v-model="newItem.due" class="rounded border px-2 py-1" /></td>
              <td class="p-2"><input type="date" v-model="newItem.avoid_interest_date" class="rounded border px-2 py-1" /></td>
              <td class="p-2"><input type="number" min="1" max="31" v-model.number="newItem.monthly_due_date_day" class="w-24 rounded border px-2 py-1 text-right" /></td>
              <td class="p-2"><span class="text-sm text-muted-foreground">—</span></td>
              <td class="p-2 text-right"><button class="rounded border px-2 py-1" :disabled="saving" @click="createAccount">Add</button></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between">
        <div class="text-sm text-muted-foreground">Showing page {{ page }} of {{ lastPage }} ({{ totalItems }} items)</div>
        <div class="flex gap-2">
          <button class="rounded border px-3 py-1 disabled:opacity-50" :disabled="page <= 1" @click="page = Math.max(1, page - 1)">Prev</button>
          <button class="rounded border px-3 py-1 disabled:opacity-50" :disabled="page >= lastPage" @click="page = Math.min(lastPage, page + 1)">Next</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.bg-muted { background-color: rgba(0,0,0,0.03) }
.text-muted-foreground { color: rgba(0,0,0,0.6) }
</style>
