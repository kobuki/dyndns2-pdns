<template>
  <div>
    <h2 class="va-h2 mb-4">Dashboard</h2>

    <div class="summary-row mb-4">
      <VaCard v-for="card in cards" :key="card.title" class="summary-card">
        <VaCardContent>
          <div class="card-label">{{ card.title }}</div>
          <div class="card-value">{{ card.value }}</div>
        </VaCardContent>
      </VaCard>
    </div>

    <VaCard>
      <VaCardTitle>Recent Activity (last 20 entries)</VaCardTitle>
      <VaCardContent>
        <VaDataTable
          :items="recentChangelog"
          :columns="columns"
          :loading="loading"
          striped
        >
          <template #cell(operation)="{ row }">
            <VaBadge
              :text="row.rowData.operation"
              :color="opColor(row.rowData.operation)"
            />
          </template>
        </VaDataTable>
      </VaCardContent>
    </VaCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const users = ref([])
const hostnames = ref([])
const permissions = ref([])
const recentChangelog = ref([])
const last24hCount = ref(0)

const columns = [
  { key: 'timestamp', label: 'Timestamp', sortable: true },
  { key: 'username', label: 'Username' },
  { key: 'hostname', label: 'Hostname' },
  { key: 'operation', label: 'Operation' },
  { key: 'record_type', label: 'Record Type' },
  { key: 'record_content', label: 'Content' },
  { key: 'client_ip', label: 'Client IP' },
]

const cards = computed(() => [
  { title: 'Active Users', value: users.value.filter(u => u.active).length },
  { title: 'Hostnames', value: hostnames.value.length },
  { title: 'Permissions', value: permissions.value },
  { title: 'Changelog (24h)', value: last24hCount.value },
])

function opColor(op) {
  if (op === 'set') return 'primary'
  if (op === 'add') return 'success'
  if (op === 'delete') return 'danger'
  return 'secondary'
}

async function loadData() {
  loading.value = true
  try {
    const now = new Date()
    const from = new Date(now - 24 * 3600 * 1000).toISOString().replace('T', ' ').substring(0, 19)

    const [uRes, hRes, clRes, cl24Res] = await Promise.all([
      axios.get('/api/users.php'),
      axios.get('/api/hostnames.php'),
      axios.get('/api/changelog.php?per_page=20'),
      axios.get(`/api/changelog.php?from=${from}&per_page=1`),
    ])

    users.value = uRes.data
    hostnames.value = hRes.data
    recentChangelog.value = clRes.data.data || []
    last24hCount.value = cl24Res.data.total || 0

    // Count total permissions by summing across users
    const permResults = await Promise.all(
      uRes.data.map(u => axios.get(`/api/permissions.php?user_id=${u.id}`))
    )
    permissions.value = permResults.reduce((sum, r) => sum + (r.data?.length || 0), 0)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
.summary-row {
  display: flex;
  gap: 0.75rem;
}
.summary-card {
  flex: 1;
}
.card-label {
  font-size: 0.75rem;
  opacity: 0.65;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  white-space: nowrap;
}
.card-value {
  font-size: 1.4rem;
  font-weight: 700;
  margin-top: 0.15rem;
}
</style>
