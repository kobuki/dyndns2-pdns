<template>
  <div>
    <h2 class="va-h2 mb-4">URL Generator</h2>

    <VaCard style="max-width: 640px;">
      <VaCardContent>
        <VaSelect
          v-model="selectedUserId"
          label="User (active only)"
          :options="activeUsers"
          value-by="id"
          text-by="username"
          class="mb-4"
          @update:model-value="onUserChange"
        />

        <VaSelect
          v-model="selectedHostnameId"
          label="Hostname"
          :options="permittedHostnames"
          value-by="id"
          text-by="hostname"
          :disabled="!selectedUserId"
          class="mb-4"
        />

        <div class="password-row mb-4">
          <VaInput
            v-model="password"
            label="Password"
            :type="showPassword ? 'text' : 'password'"
            class="password-input"
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
              <VaButton icon="content_copy" preset="secondary" size="small" @click="copy(password)" />
            </div>
            <div class="pw-spacer"></div>
          </div>
        </div>

        <VaCheckbox
          v-model="forceUpdate"
          label="Force update — save this password as the user's new hash before generating"
          class="mb-4"
        />

        <VaButton
          :disabled="!selectedUserId || !selectedHostnameId || !password"
          @click="generate"
          :loading="saving"
        >
          Generate URL
        </VaButton>

        <!-- Output -->
        <template v-if="token">
          <VaDivider class="my-4" />

          <div class="output-row mb-3">
            <VaInput
              :model-value="token"
              label="Token"
              readonly
              class="output-input"
            />
            <VaButton
              icon="content_copy"
              preset="secondary"
              size="small"
              class="ml-2"
              @click="copy(token)"
            />
          </div>

          <div class="output-row mb-3">
            <VaInput
              :model-value="fullUrl"
              label="Full URL"
              readonly
              class="output-input"
            />
            <VaButton
              icon="content_copy"
              preset="secondary"
              size="small"
              class="ml-2"
              @click="copy(fullUrl)"
            />
          </div>

          <VaAlert color="warning" class="mt-2">
            This URL auto-detects the caller's IP. Guard it like a password.
          </VaAlert>
        </template>

        <VaAlert v-if="errorMsg" color="danger" class="mt-3">{{ errorMsg }}</VaAlert>
      </VaCardContent>
    </VaCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const users = ref([])
const allHostnames = ref([])
const allPermissions = ref(new Map())
const baseUrl = ref('')

const selectedUserId = ref(null)
const selectedHostnameId = ref(null)
const password = ref('')
const showPassword = ref(false)
const forceUpdate = ref(false)
const saving = ref(false)
const errorMsg = ref('')
const token = ref('')

const CHARSET_ALNUM = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'

function generatePassword() {
  const arr = new Uint32Array(20)
  crypto.getRandomValues(arr)
  password.value = Array.from(arr, n => CHARSET_ALNUM[n % CHARSET_ALNUM.length]).join('')
  showPassword.value = true
}

const activeUsers = computed(() => users.value.filter(u => u.active))

const permittedHostnames = computed(() => {
  if (!selectedUserId.value) return []
  const ids = allPermissions.value.get(selectedUserId.value) || new Set()
  return allHostnames.value.filter(h => ids.has(h.id))
})

const fullUrl = computed(() => {
  if (!token.value || !baseUrl.value) return ''
  return `${baseUrl.value}/${token.value}`
})

async function onUserChange() {
  selectedHostnameId.value = null
  token.value = ''
  errorMsg.value = ''
  if (!selectedUserId.value) return
  if (!allPermissions.value.has(selectedUserId.value)) {
    try {
      const res = await axios.get(`/api/permissions.php?user_id=${selectedUserId.value}`)
      allPermissions.value.set(selectedUserId.value, new Set(res.data))
    } catch (e) {}
  }
}

async function generate() {
  errorMsg.value = ''
  token.value = ''

  if (forceUpdate.value) {
    saving.value = true
    try {
      const user = users.value.find(u => u.id === selectedUserId.value)
      if (!user) throw new Error('User not found')
      await axios.put(`/api/users.php?id=${selectedUserId.value}`, {
        username: user.username,
        active: user.active,
        password: password.value,
      })
    } catch (e) {
      errorMsg.value = e.response?.data?.error || 'Failed to update password'
      saving.value = false
      return
    } finally {
      saving.value = false
    }
  }

  // Compute token client-side
  const raw = `${selectedUserId.value}:${selectedHostnameId.value}:${password.value}`
  token.value = btoa(raw)
}

function copy(text) {
  navigator.clipboard.writeText(text).catch(() => {})
}

async function loadData() {
  try {
    const [uRes, hRes, cfgRes] = await Promise.all([
      axios.get('/api/users.php'),
      axios.get('/api/hostnames.php'),
      axios.get('/api/config.php'),
    ])
    users.value = uRes.data
    allHostnames.value = hRes.data
    baseUrl.value = cfgRes.data.base_url || ''
  } catch (e) {
    console.error(e)
  }
}

onMounted(loadData)
</script>

<style scoped>
.password-row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
}
.password-input {
  flex: 1;
}
.pw-actions {
  display: flex;
  flex-direction: column;
}
.pw-buttons {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
}
.pw-spacer {
  height: 0.375rem;
}
.output-row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
}
.output-input {
  flex: 1;
}
</style>
