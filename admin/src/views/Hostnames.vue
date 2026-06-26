<template>
  <div>
    <h2 class="va-h2 mb-4">Hostnames</h2>

    <VaCard>
      <VaCardContent>
        <div class="toolbar mb-4">
          <VaInput
            v-model="search"
            placeholder="Search hostnames..."
            clearable
            class="search-input"
          >
            <template #prependInner>
              <VaIcon name="search" />
            </template>
          </VaInput>
          <VaButton icon="add" @click="openAddModal">Add Hostname</VaButton>
        </div>

        <VaDataTable
          :items="filteredHostnames"
          :columns="columns"
          :loading="loading"
          :current-page="currentPage"
          :per-page="perPage"
          striped
        >
          <template #cell(actions)="{ row }">
            <VaButton
              icon="edit"
              preset="plain"
              size="small"
              @click="openEditModal(row.rowData)"
            />
            <VaButton
              icon="delete"
              preset="plain"
              size="small"
              color="danger"
              @click="confirmDelete(row.rowData)"
            />
          </template>
        </VaDataTable>

        <div class="pagination-row mt-4" v-if="lastPage > 1">
          <VaPagination v-model="currentPage" :pages="lastPage" />
        </div>
      </VaCardContent>
    </VaCard>

    <!-- Add/Edit Modal -->
    <VaModal
      v-model="showModal"
      :title="editingHostname ? 'Edit Hostname' : 'Add Hostname'"
      @ok="saveHostname"
      @cancel="closeModal"
      ok-text="Save"
    >
      <div class="modal-form">
        <VaInput
          v-model="form.hostname"
          label="Hostname (must end with .)"
          class="mb-4"
          @blur="onHostnameBlur"
          :error="!!errors.hostname"
          :error-messages="errors.hostname"
        />

        <div class="domain-row mb-4">
          <VaInput
            v-model="form.domain"
            label="Domain"
            class="domain-input"
            :error="!!errors.domain"
            :error-messages="errors.domain"
          />
          <VaBadge
            v-if="guessedDomain"
            :text="'guessed: ' + guessedDomain"
            color="info"
            class="ml-2 guess-badge"
          />
        </div>

        <template v-if="editingHostname">
          <VaInput
            :model-value="editingHostname.last_updated || '—'"
            label="Last Updated"
            readonly
            class="mb-4"
          />
          <VaInput
            :model-value="editingHostname.last_client_ip || '—'"
            label="Last Client IP"
            readonly
          />
        </template>
      </div>
    </VaModal>

    <!-- Delete Confirmation Modal -->
    <VaModal
      v-model="showDeleteModal"
      title="Delete Hostname"
      ok-text="Delete"
      ok-color="danger"
      @ok="doDelete"
      @cancel="showDeleteModal = false"
    >
      <p v-if="deleteTarget">
        Delete hostname <strong>{{ deleteTarget.hostname }}</strong>?
        <span v-if="deleteBlockedCount > 0" class="text-danger">
          Cannot delete: this hostname has {{ deleteBlockedCount }} permission(s).
          Remove permissions first.
        </span>
      </p>
    </VaModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const currentPage = ref(1)
const perPage = 15
import axios from 'axios'

const loading = ref(false)
const hostnames = ref([])
const search = ref('')
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingHostname = ref(null)
const deleteTarget = ref(null)
const deleteBlockedCount = ref(0)
const guessedDomain = ref('')
const errors = ref({})

const form = ref({ hostname: '', domain: '' })

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'hostname', label: 'Hostname', sortable: true },
  { key: 'domain', label: 'Domain', sortable: true },
  { key: 'last_updated', label: 'Last Updated', sortable: true },
  { key: 'last_client_ip', label: 'Last Client IP' },
  { key: 'actions', label: 'Actions', width: 100 },
]

const filteredHostnames = computed(() => {
  if (!search.value) return hostnames.value
  const q = search.value.toLowerCase()
  return hostnames.value.filter(h =>
    h.hostname.toLowerCase().includes(q) ||
    h.domain.toLowerCase().includes(q)
  )
})

const lastPage = computed(() => Math.ceil(filteredHostnames.value.length / perPage) || 1)

async function loadHostnames() {
  loading.value = true
  try {
    const res = await axios.get('/api/hostnames.php')
    hostnames.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openAddModal() {
  editingHostname.value = null
  form.value = { hostname: '', domain: '' }
  errors.value = {}
  guessedDomain.value = ''
  showModal.value = true
}

function openEditModal(hostname) {
  editingHostname.value = hostname
  form.value = { hostname: hostname.hostname, domain: hostname.domain }
  errors.value = {}
  guessedDomain.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingHostname.value = null
}

async function onHostnameBlur() {
  const val = form.value.hostname.trim()
  if (!val) return
  try {
    const fqdn = val.endsWith('.') ? val.slice(0, -1) : val
    const res = await axios.get(`/api/hostnames.php?guess=${encodeURIComponent(fqdn)}`)
    if (res.data?.domain) {
      guessedDomain.value = res.data.domain
      if (!form.value.domain) {
        form.value.domain = res.data.domain
      }
    }
  } catch (e) {
    // Silent fallback
  }
}

async function saveHostname() {
  errors.value = {}
  if (!form.value.hostname) {
    errors.value.hostname = 'Hostname is required'
    return false
  }
  if (!form.value.hostname.endsWith('.')) {
    errors.value.hostname = 'Hostname must end with a dot'
    return false
  }
  if (!form.value.domain) {
    errors.value.domain = 'Domain is required'
    return false
  }

  try {
    if (editingHostname.value) {
      await axios.put(`/api/hostnames.php?id=${editingHostname.value.id}`, form.value)
    } else {
      await axios.post('/api/hostnames.php', form.value)
    }
    await loadHostnames()
    closeModal()
  } catch (e) {
    const msg = e.response?.data?.error || 'An error occurred'
    errors.value.hostname = msg
    return false
  }
}

async function confirmDelete(hostname) {
  deleteTarget.value = hostname
  deleteBlockedCount.value = 0
  // Check permissions count via delete endpoint — we'll rely on the error response
  // Instead, check by fetching all users' permissions (lightweight approach)
  try {
    const usersRes = await axios.get('/api/users.php')
    let count = 0
    const checks = await Promise.all(
      usersRes.data.map(u => axios.get(`/api/permissions.php?user_id=${u.id}`))
    )
    for (const r of checks) {
      if (r.data?.includes(hostname.id)) count++
    }
    deleteBlockedCount.value = count
  } catch (e) {}
  showDeleteModal.value = true
}

async function doDelete() {
  if (deleteBlockedCount.value > 0) {
    showDeleteModal.value = false
    return
  }
  try {
    await axios.delete(`/api/hostnames.php?id=${deleteTarget.value.id}`)
    await loadHostnames()
  } catch (e) {
    console.error(e)
  }
  showDeleteModal.value = false
}

onMounted(loadHostnames)
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.search-input {
  flex: 1;
  max-width: 300px;
}
.modal-form {
  width: 270px;
}
.domain-row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
}
.domain-input {
  width: 190px;
  flex-shrink: 0;
}
.guess-badge {
  margin-bottom: 0.25rem;
  white-space: nowrap;
}
.text-danger {
  color: var(--va-danger);
  display: block;
  margin-top: 0.5rem;
}
.pagination-row {
  display: flex;
  justify-content: center;
}
</style>
