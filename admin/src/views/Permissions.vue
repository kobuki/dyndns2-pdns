<template>
  <div>
    <div class="header-row mb-4">
      <h2 class="va-h2">Permissions</h2>
      <VaButton
        :icon="matrixView ? 'view_list' : 'grid_on'"
        preset="secondary"
        @click="matrixView = !matrixView"
      >
        {{ matrixView ? 'Panel View' : 'Matrix View' }}
      </VaButton>
    </div>

    <!-- Two-panel layout -->
    <div v-if="!matrixView" class="panel-layout">
      <!-- Left: user list -->
      <VaCard class="user-panel">
        <VaCardTitle>Users</VaCardTitle>
        <VaCardContent>
          <div class="user-list">
            <div
              v-for="user in sortedUsers"
              :key="user.id"
              class="user-list-item"
              :class="{ 'selected': selectedUser?.id === user.id }"
              @click="selectUser(user)"
            >
              <span class="user-label">
                {{ user.username }}
                <VaBadge
                  :text="user.active ? 'active' : 'inactive'"
                  :color="user.active ? 'success' : 'secondary'"
                  class="status-badge"
                />
              </span>
            </div>
          </div>
        </VaCardContent>
      </VaCard>

      <!-- Right: hostname checklist -->
      <VaCard class="hostname-panel">
        <VaCardTitle>
          <span v-if="selectedUser">Hostnames for {{ selectedUser.username }}</span>
          <span v-else>Select a user</span>
        </VaCardTitle>
        <VaCardContent>
          <div v-if="!selectedUser" class="placeholder-text">
            Click a user on the left to manage their permissions.
          </div>
          <div v-else-if="hostnames.length === 0" class="placeholder-text">
            No hostnames configured.
          </div>
          <div v-else>
            <VaInput
              v-model="hostnameSearch"
              placeholder="Filter hostnames..."
              clearable
              class="mb-3"
            >
              <template #prependInner>
                <VaIcon name="search" />
              </template>
            </VaInput>
            <div class="hostname-list">
              <div
                v-for="hostname in filteredHostnames"
                :key="hostname.id"
                class="hostname-check-row"
              >
                <VaCheckbox
                  :model-value="userPermissions.has(hostname.id)"
                  @update:model-value="togglePermission(hostname, $event)"
                  :label="hostname.hostname"
                />
                <VaBadge
                  v-if="hostname.hostname.startsWith('.')"
                  text="wildcard"
                  color="warning"
                  class="ml-2"
                />
              </div>
              <div v-if="filteredHostnames.length === 0" class="placeholder-text">
                No matches.
              </div>
            </div>
          </div>
        </VaCardContent>
      </VaCard>
    </div>

    <!-- Matrix view -->
    <div v-else>
      <VaCard>
        <VaCardContent class="matrix-scroll">
          <table class="matrix-table">
            <thead>
              <tr>
                <th class="user-col">User</th>
                <th v-for="hostname in hostnames" :key="hostname.id" class="hostname-col">
                  <span :title="hostname.hostname">{{ hostname.hostname }}</span>
                  <VaBadge
                    v-if="hostname.hostname.startsWith('.')"
                    text="wildcard"
                    color="warning"
                    class="ml-1"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td class="user-col">{{ user.username }}</td>
                <td v-for="hostname in hostnames" :key="hostname.id" class="check-col">
                  <VaCheckbox
                    :model-value="matrixPermissions.get(user.id)?.has(hostname.id) ?? false"
                    @update:model-value="toggleMatrixPermission(user, hostname, $event)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </VaCardContent>
      </VaCard>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

const users = ref([])
const sortedUsers = computed(() =>
  [...users.value].sort((a, b) => a.username.localeCompare(b.username))
)
const hostnames = ref([])
const hostnameSearch = ref('')
const filteredHostnames = computed(() => {
  if (!hostnameSearch.value) return hostnames.value
  const q = hostnameSearch.value.toLowerCase()
  return hostnames.value.filter(h => h.hostname.toLowerCase().includes(q))
})
const selectedUser = ref(null)
const userPermissions = ref(new Set())
const matrixPermissions = ref(new Map())
const matrixView = ref(false)

async function loadAll() {
  const [uRes, hRes] = await Promise.all([
    axios.get('/api/users.php'),
    axios.get('/api/hostnames.php'),
  ])
  users.value = uRes.data
  hostnames.value = hRes.data
}

async function selectUser(user) {
  selectedUser.value = user
  const res = await axios.get(`/api/permissions.php?user_id=${user.id}`)
  userPermissions.value = new Set(res.data)
}

async function togglePermission(hostname, checked) {
  const prev = userPermissions.value.has(hostname.id)
  // Optimistic update
  if (checked) {
    userPermissions.value.add(hostname.id)
  } else {
    userPermissions.value.delete(hostname.id)
  }
  // Trigger reactivity
  userPermissions.value = new Set(userPermissions.value)

  try {
    if (checked) {
      await axios.post('/api/permissions.php', {
        user_id: selectedUser.value.id,
        hostname_id: hostname.id,
      })
    } else {
      await axios.delete('/api/permissions.php', {
        data: { user_id: selectedUser.value.id, hostname_id: hostname.id },
      })
    }
  } catch (e) {
    // Rollback
    if (prev) {
      userPermissions.value.add(hostname.id)
    } else {
      userPermissions.value.delete(hostname.id)
    }
    userPermissions.value = new Set(userPermissions.value)
    console.error('Permission toggle failed', e)
  }
}

async function loadMatrixPermissions() {
  const map = new Map()
  const results = await Promise.all(
    users.value.map(u =>
      axios.get(`/api/permissions.php?user_id=${u.id}`).then(r => ({ userId: u.id, perms: r.data }))
    )
  )
  for (const { userId, perms } of results) {
    map.set(userId, new Set(perms))
  }
  matrixPermissions.value = map
}

async function toggleMatrixPermission(user, hostname, checked) {
  const userSet = matrixPermissions.value.get(user.id) || new Set()
  const prev = userSet.has(hostname.id)

  // Optimistic
  if (checked) userSet.add(hostname.id)
  else userSet.delete(hostname.id)
  matrixPermissions.value = new Map(matrixPermissions.value)

  try {
    if (checked) {
      await axios.post('/api/permissions.php', {
        user_id: user.id,
        hostname_id: hostname.id,
      })
    } else {
      await axios.delete('/api/permissions.php', {
        data: { user_id: user.id, hostname_id: hostname.id },
      })
    }
  } catch (e) {
    // Rollback
    if (prev) userSet.add(hostname.id)
    else userSet.delete(hostname.id)
    matrixPermissions.value = new Map(matrixPermissions.value)
    console.error('Permission toggle failed', e)
  }
}

watch(matrixView, async (val) => {
  if (val) {
    await loadMatrixPermissions()
  }
})

onMounted(loadAll)
</script>

<style scoped>
.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.panel-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 1rem;
  align-items: start;
}
.user-panel {
  min-height: 300px;
  max-height: calc(100vh - 180px);
  overflow-y: auto;
}
.hostname-panel {
  min-height: 300px;
}
.hostname-list {
  max-height: calc(100vh - 300px);
  overflow-y: auto;
}
.selected {
  background: rgba(var(--va-primary-rgb), 0.15);
}
.selected:hover {
  background: rgba(var(--va-primary-rgb), 0.15);
}
.hostname-check-row {
  display: flex;
  align-items: center;
  padding: 0.4rem 0;
  border-bottom: 1px solid var(--va-background-border, #eee);
}
.hostname-check-row:last-child {
  border-bottom: none;
}
.placeholder-text {
  color: var(--va-secondary);
  font-style: italic;
  padding: 1rem 0;
}
.user-list {
  display: flex;
  flex-direction: column;
}
.user-list-item {
  display: flex;
  align-items: center;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  border-radius: 4px;
  transition: background 0.1s;
}
.user-list-item:hover {
  background: rgba(var(--va-primary-rgb), 0.07);
}
.user-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
}
.status-badge {
  flex-shrink: 0;
}
.matrix-scroll {
  overflow-x: auto;
}
.matrix-table {
  border-collapse: collapse;
  min-width: 100%;
}
.matrix-table th,
.matrix-table td {
  border: 1px solid var(--va-background-border, #ddd);
  padding: 0.4rem 0.6rem;
  text-align: center;
  white-space: nowrap;
}
.matrix-table .user-col {
  text-align: left;
  font-weight: 600;
  min-width: 120px;
}
.matrix-table .hostname-col {
  font-size: 0.8rem;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.check-col {
  min-width: 48px;
}
</style>
