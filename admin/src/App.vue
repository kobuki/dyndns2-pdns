<template>
  <VaLayout>
    <template #left>
      <VaSidebar v-model="sidebarVisible" class="sidebar">
        <VaSidebarItem to="/dashboard" :active="$route.path === '/dashboard'">
          <VaSidebarItemContent>
            <VaIcon name="dashboard" />
            <VaSidebarItemTitle>Dashboard</VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>
        <VaSidebarItem to="/users" :active="$route.path === '/users'">
          <VaSidebarItemContent>
            <VaIcon name="people" />
            <VaSidebarItemTitle>Users</VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>
        <VaSidebarItem to="/hostnames" :active="$route.path === '/hostnames'">
          <VaSidebarItemContent>
            <VaIcon name="dns" />
            <VaSidebarItemTitle>Hostnames</VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>
        <VaSidebarItem to="/permissions" :active="$route.path === '/permissions'">
          <VaSidebarItemContent>
            <VaIcon name="lock" />
            <VaSidebarItemTitle>Permissions</VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>
        <VaSidebarItem to="/changelog" :active="$route.path === '/changelog'">
          <VaSidebarItemContent>
            <VaIcon name="history" />
            <VaSidebarItemTitle>Changelog</VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>
        <VaSidebarItem to="/url-generator" :active="$route.path === '/url-generator'">
          <VaSidebarItemContent>
            <VaIcon name="link" />
            <VaSidebarItemTitle>URL Generator</VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>
      </VaSidebar>
    </template>

    <template #top>
      <VaNavbar class="navbar">
        <template #left>
          <VaButton icon="menu" preset="plain" @click="sidebarVisible = !sidebarVisible" />
          <span class="navbar-title">DynDNS Admin</span>
        </template>
        <template #right>
          <span v-if="stats" class="navbar-stats">
            {{ stats.users }} users &middot; {{ stats.hostnames }} hostnames
            <template v-if="stats.lastUpdate"> &middot; last update: {{ stats.lastUpdate }}</template>
          </span>
          <VaButton
            :icon="isDark ? 'light_mode' : 'dark_mode'"
            preset="plain"
            @click="toggleDark"
          />
        </template>
      </VaNavbar>
    </template>

    <template #content>
      <div class="content-area">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </div>
    </template>
  </VaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useColors } from 'vuestic-ui'
import axios from 'axios'

const { applyPreset, currentPresetName } = useColors()

const sidebarVisible = ref(true)
const stats = ref(null)
const isDark = ref(localStorage.getItem('colorScheme') === 'dark')

function toggleDark() {
  isDark.value = !isDark.value
  const preset = isDark.value ? 'dark' : 'light'
  applyPreset(preset)
  localStorage.setItem('colorScheme', preset)
}

if (isDark.value) applyPreset('dark')

async function loadStats() {
  try {
    const [usersRes, hostnamesRes, clRes] = await Promise.all([
      axios.get('/api/users.php'),
      axios.get('/api/hostnames.php'),
      axios.get('/api/changelog.php?per_page=1'),
    ])
    const lastEntry = clRes.data.data?.[0]
    stats.value = {
      users: usersRes.data.length,
      hostnames: hostnamesRes.data.length,
      lastUpdate: lastEntry?.timestamp ? lastEntry.timestamp.replace('T', ' ').substring(0, 16) : null,
    }
  } catch (e) {
    // Non-fatal
  }
}

onMounted(loadStats)
</script>

<style scoped>
.sidebar {
  min-height: 100vh;
}
.navbar-title {
  font-size: 1.2rem;
  font-weight: 600;
  margin-left: 0.5rem;
}
.navbar-stats {
  font-size: 0.85rem;
  opacity: 0.8;
  padding-right: 1rem;
}
.content-area {
  padding: 1.5rem;
  min-height: calc(100vh - 56px);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
