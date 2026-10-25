<template>
  <div class="app-layout">
    <header class="app-header">
      <div class="container header-container">
        <router-link to="/gadgets" class="brand">
          <span class="brand-name">WishRadar</span>
        </router-link>

        <nav v-if="isAuthenticated" class="header-nav">
          <span class="user-label">{{ userEmail }}</span>
          <button @click="handleLogout" class="btn btn-secondary btn-sm" :disabled="isLoggingOut">
            {{ isLoggingOut ? 'Keluar...' : 'Keluar' }}
          </button>
        </nav>
      </div>
    </header>

    <main class="app-main">
      <div class="container">
        <router-view />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import http from './api/http'

const router = useRouter()
const route = useRoute()

const token = ref(localStorage.getItem('token'))
const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
const isLoggingOut = ref(false)

const syncAuth = () => {
  token.value = localStorage.getItem('token')
  user.value = JSON.parse(localStorage.getItem('user') || 'null')
}

watch(() => route.path, () => {
  syncAuth()
})

onMounted(() => {
  window.addEventListener('storage', syncAuth)
})

const isAuthenticated = computed(() => Boolean(token.value))
const userEmail = computed(() => user.value?.email || 'Pengguna')

const handleLogout = async () => {
  isLoggingOut.value = true
  try {
    await http.post('/logout')
  } catch {
  } finally {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    syncAuth()
    isLoggingOut.value = false
    router.push('/login')
  }
}
</script>

<style scoped>
.app-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-header {
  background-color: #ffffff;
  border-bottom: 1px solid var(--border-color);
  padding: 14px 0;
}

.header-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.brand {
  display: flex;
  align-items: center;
  gap: 8px;
  color: inherit;
  text-decoration: none;
}

.brand:hover {
  text-decoration: none;
}

.brand-name {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text-main);
}

.header-nav {
  display: flex;
  align-items: center;
  gap: 16px;
}

.user-label {
  font-size: 13px;
  color: var(--text-muted);
}

.btn-sm {
  padding: 5px 12px;
  font-size: 13px;
}

.app-main {
  flex: 1;
  padding: 32px 0 48px;
}
</style>
