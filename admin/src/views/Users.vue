<template>
  <div>
    <h2 class="va-h2 mb-4">Users</h2>

    <VaCard>
      <VaCardContent>
        <div class="toolbar mb-4">
          <VaInput
            v-model="search"
            placeholder="Search users..."
            clearable
            class="search-input"
          >
            <template #prependInner>
              <VaIcon name="search" />
            </template>
          </VaInput>
          <VaButton icon="add" @click="openAddModal">Add User</VaButton>
        </div>

        <VaDataTable
          :items="filteredUsers"
          :columns="columns"
          :loading="loading"
          :current-page="currentPage"
          :per-page="perPage"
          striped
        >
          <template #cell(active)="{ row }">
            <VaSwitch
              :model-value="!!row.rowData.active"
              @update:model-value="toggleActive(row.rowData)"
              size="small"
            />
          </template>
          <template #cell(actions)="{ row }">
            <span style="display:inline-flex;align-items:center;gap:0.4rem;">
              <VaButton
                icon="edit"
                preset="plain"
                @click="openEditModal(row.rowData)"
              />
              <VaButton
                icon="delete"
                preset="plain"
                color="danger"
                @click="confirmDelete(row.rowData)"
              />
              <VaButton
                icon="manage_accounts"
                preset="plain"
                @click="goToPermissions(row.rowData)"
              />
            </span>
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
      :title="editingUser ? 'Edit User' : 'Add User'"
      @ok="saveUser"
      @cancel="closeModal"
      ok-text="Save"
    >
      <div class="modal-form">
        <VaInput
          v-model="form.username"
          label="Username"
          class="username-field"
          :error="!!errors.username"
          :error-messages="errors.username"
        />
        <VaInput
          v-model="form.password"
          :label="editingUser ? 'Password (leave blank to keep)' : 'Password'"
          :type="showPassword ? 'text' : 'password'"
          class="password-field"
          :error="!!errors.password"
          :error-messages="errors.password"
        >
          <template #appendInner>
            <VaButton
              :icon="showPassword ? 'visibility_off' : 'visibility'"
              preset="plain"
              size="small"
              @click="showPassword = !showPassword"
            />
          </template>
        </VaInput>
        <div class="pw-actions">
          <div class="pw-buttons">
            <VaButton preset="secondary" size="small" @click="generatePassword">Generate</VaButton>
            <VaButton icon="content_copy" preset="secondary" size="small" @click="copyPassword" />
          </div>
          <div class="pw-spacer"></div>
        </div>
        <VaSwitch v-model="form.active" label="Active" class="switch-field" />
      </div>
    </VaModal>

    <!-- Delete Confirmation Modal -->
    <VaModal
      v-model="showDeleteModal"
      title="Delete User"
      ok-text="Delete"
      ok-color="danger"
      @ok="doDelete"
      @cancel="showDeleteModal = false"
    >
      <p v-if="deleteTarget">
        Delete user <strong>{{ deleteTarget.username }}</strong>?
        <span v-if="deleteBlockedCount > 0" class="text-danger">
          Cannot delete: this user has {{ deleteBlockedCount }} permission(s).
          Remove permissions first.
        </span>
      </p>
    </VaModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

function goToPermissions(user) {
  router.push({ path: '/permissions', query: { user_id: user.id } })
}

function copyPassword() {
  navigator.clipboard.writeText(form.value.password).catch(() => {})
}

const currentPage = ref(1)
const perPage = 15
import axios from 'axios'
import { generateSecurePassword } from '../utils/password.js'

const loading = ref(false)
const users = ref([])
const search = ref('')
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingUser = ref(null)
const deleteTarget = ref(null)
const deleteBlockedCount = ref(0)
const showPassword = ref(false)
const errors = ref({})

const form = ref({ username: '', password: '', active: true })

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'username', label: 'Username', sortable: true },
  { key: 'active', label: 'Active' },
  { key: 'actions', label: 'Actions', width: 120, align: 'center', alignHead: 'center' },
]

const filteredUsers = computed(() => {
  if (!search.value) return users.value
  const q = search.value.toLowerCase()
  return users.value.filter(u => u.username.toLowerCase().includes(q))
})

const lastPage = computed(() => Math.ceil(filteredUsers.value.length / perPage) || 1)

async function loadUsers() {
  loading.value = true
  try {
    const res = await axios.get('/api/users.php')
    users.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openAddModal() {
  editingUser.value = null
  form.value = { username: '', password: '', active: true }
  errors.value = {}
  showPassword.value = false
  showModal.value = true
}

function openEditModal(user) {
  editingUser.value = user
  form.value = { username: user.username, password: '', active: !!user.active }
  errors.value = {}
  showPassword.value = false
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingUser.value = null
}

function generatePassword() {
  form.value.password = generateSecurePassword(20)
  showPassword.value = true
}

async function saveUser() {
  errors.value = {}
  if (!form.value.username) {
    errors.value.username = 'Username is required'
    return false
  }
  if (!editingUser.value && !form.value.password) {
    errors.value.password = 'Password is required'
    return false
  }

  try {
    const payload = {
      username: form.value.username,
      active: form.value.active ? 1 : 0,
    }
    if (form.value.password) payload.password = form.value.password

    if (editingUser.value) {
      await axios.put(`/api/users.php?id=${editingUser.value.id}`, payload)
    } else {
      await axios.post('/api/users.php', payload)
    }
    await loadUsers()
    closeModal()
  } catch (e) {
    const msg = e.response?.data?.error || 'An error occurred'
    errors.value.username = msg
    return false
  }
}

async function toggleActive(user) {
  try {
    await axios.put(`/api/users.php?id=${user.id}`, {
      username: user.username,
      active: user.active ? 0 : 1,
    })
    await loadUsers()
  } catch (e) {
    console.error(e)
  }
}

async function confirmDelete(user) {
  deleteTarget.value = user
  deleteBlockedCount.value = 0
  try {
    const res = await axios.get(`/api/permissions.php?user_id=${user.id}`)
    deleteBlockedCount.value = res.data?.length || 0
  } catch (e) {}
  showDeleteModal.value = true
}

async function doDelete() {
  if (deleteBlockedCount.value > 0) {
    showDeleteModal.value = false
    return
  }
  try {
    await axios.delete(`/api/users.php?id=${deleteTarget.value.id}`)
    await loadUsers()
  } catch (e) {
    console.error(e)
  }
  showDeleteModal.value = false
}

onMounted(loadUsers)
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
  width: 415px;
  display: grid;
  grid-template-columns: 1fr auto;
  row-gap: 1rem;
  column-gap: 0.5rem;
}
.username-field {
  grid-column: 1;
  grid-row: 1;
}
.password-field {
  grid-column: 1;
  grid-row: 2;
}
.pw-actions {
  grid-column: 2;
  grid-row: 2;
  display: flex;
  flex-direction: column;
  align-self: end;
}
.pw-buttons {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.pw-spacer {
  height: 0.375rem;
}
.switch-field {
  grid-column: 1 / -1;
  grid-row: 3;
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
