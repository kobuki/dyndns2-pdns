<template>
  <div>
    <h2 class="va-h2 mb-4">Changelog</h2>

    <!-- Filter bar -->
    <VaCard class="mb-4">
      <VaCardContent>
        <div class="filter-bar">
          <VaDateInput
            v-model="filters.from"
            label="From"
            clearable
            class="filter-item"
          />
          <VaDateInput
            v-model="filters.to"
            label="To"
            clearable
            class="filter-item"
          />
          <VaSelect
            v-model="filters.username"
            label="Username"
            :options="usernameOptions"
            clearable
            class="filter-item"
          />
          <VaInput
            v-model="filters.hostname"
            label="Hostname"
            clearable
            class="filter-item"
          />
          <VaSelect
            v-model="filters.operation"
            label="Operation"
            :options="operationOptions"
            clearable
            class="filter-item"
          />
          <VaSelect
            v-model="filters.record_type"
            label="Record Type"
            :options="recordTypeOptions"
            clearable
            class="filter-item"
          />
          <div class="filter-actions">
            <VaButton @click="applyFilters" icon="search">Filter</VaButton>
            <VaButton preset="secondary" @click="resetFilters" icon="clear">Reset</VaButton>
            <VaButton preset="secondary" @click="exportCsv" icon="download">Export CSV</VaButton>
          </div>
        </div>
      </VaCardContent>
    </VaCard>

    <VaCard>
      <VaCardContent>
        <VaDataTable
          :items="rows"
          :columns="columns"
          :loading="loading"
          striped
        >
          <template #cell(timestamp)="{ row }">
            {{ formatTimestamp(row.rowData.timestamp) }}
          </template>
          <template #cell(operation)="{ row }">
            <VaBadge
              :text="row.rowData.operation"
              :color="opColor(row.rowData.operation)"
            />
          </template>
        </VaDataTable>

        <!-- Pagination -->
        <div class="pagination-row mt-4" v-if="lastPage > 1">
          <VaPagination
            v-model="currentPage"
            :pages="lastPage"
            @update:model-value="loadChangelog"
          />
          <span class="total-info">{{ total }} total entries</span>
        </div>
        <div v-else class="total-info mt-2">{{ total }} total entries</div>
      </VaCardContent>
    </VaCard>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const rows = ref([])
const total = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = 50

const usernameOptions = ref([])

const operationOptions = ['set', 'add', 'delete']
const recordTypeOptions = ['A', 'AAAA', 'TXT']

const filters = ref({
  from: null,
  to: null,
  username: '',
  hostname: '',
  operation: '',
  record_type: '',
})

const columns = [
  { key: 'timestamp', label: 'Timestamp', sortable: true },
  { key: 'client_ip', label: 'Client IP' },
  { key: 'username', label: 'Username', sortable: true },
  { key: 'hostname', label: 'Hostname', sortable: true },
  { key: 'operation', label: 'Operation', sortable: true },
  { key: 'record_type', label: 'Record Type' },
  { key: 'record_content', label: 'Content' },
]

function formatTimestamp(ts) {
  if (!ts) return '—'
  return ts.replace('T', ' ').substring(0, 19)
}

function opColor(op) {
  if (op === 'set') return 'primary'
  if (op === 'add') return 'success'
  if (op === 'delete') return 'danger'
  return 'secondary'
}

function buildParams(page = currentPage.value) {
  const params = { page, per_page: perPage }
  if (filters.value.from) {
    const d = filters.value.from
    params.from = d instanceof Date
      ? d.toISOString().substring(0, 10)
      : String(d).substring(0, 10)
  }
  if (filters.value.to) {
    const d = filters.value.to
    params.to = d instanceof Date
      ? d.toISOString().substring(0, 10)
      : String(d).substring(0, 10)
  }
  if (filters.value.username) params.username = filters.value.username
  if (filters.value.hostname) params.hostname = filters.value.hostname
  if (filters.value.operation) params.operation = filters.value.operation
  if (filters.value.record_type) params.record_type = filters.value.record_type
  return params
}

async function loadChangelog(page) {
  if (page !== undefined) currentPage.value = page
  loading.value = true
  try {
    const res = await axios.get('/admin/api/changelog.php', { params: buildParams() })
    rows.value = res.data.data || []
    total.value = res.data.total || 0
    lastPage.value = res.data.last_page || 1
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadUsernames() {
  try {
    const res = await axios.get('/admin/api/users.php')
    usernameOptions.value = res.data.map(u => u.username)
  } catch (e) {}
}

function applyFilters() {
  currentPage.value = 1
  loadChangelog()
}

function resetFilters() {
  filters.value = { from: null, to: null, username: '', hostname: '', operation: '', record_type: '' }
  currentPage.value = 1
  loadChangelog()
}

function exportCsv() {
  const params = buildParams()
  delete params.page
  delete params.per_page
  params.export = 'csv'
  const qs = new URLSearchParams(params).toString()
  window.location.href = `/admin/api/changelog.php?${qs}`
}

onMounted(async () => {
  await Promise.all([loadChangelog(), loadUsernames()])
})
</script>

<style scoped>
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: flex-end;
}
.filter-item {
  min-width: 140px;
  flex: 1;
}
.filter-actions {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
  flex-shrink: 0;
}
.pagination-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.total-info {
  font-size: 0.85rem;
  opacity: 0.65;
}
</style>
