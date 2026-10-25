<template>
  <div class="login-wrapper">
    <div class="card login-card">
      <div class="login-header">
        <h1 class="login-title">Masuk ke Akun</h1>
        <p class="login-desc">Masukkan kredensial untuk mengelola pelacakan gadget</p>
      </div>

      <div v-if="errorMessage" class="alert-danger">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="form-group">
          <label for="email" class="form-label">Alamat Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="form-input"
            :class="{ 'has-error': Boolean(errorMessage) }"
            placeholder="nama@email.com"
            required
            autocomplete="email"
          />
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Kata Sandi</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="form-input"
            :class="{ 'has-error': Boolean(errorMessage) }"
            placeholder="Kata sandi"
            required
            autocomplete="current-password"
          />
        </div>

        <button type="submit" class="btn btn-primary btn-block" :disabled="isLoading">
          {{ isLoading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import http from '../api/http'

const router = useRouter()

const email = ref('')
const password = ref('')
const isLoading = ref(false)
const errorMessage = ref('')

const handleSubmit = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await http.post('/login', {
      email: email.value,
      password: password.value,
    })

    const token = response.data?.data?.token
    const user = response.data?.data?.user

    if (token) {
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user || {}))
      window.dispatchEvent(new Event('storage'))
      router.push('/gadgets')
    }
  } catch (error) {
    if (error.response?.data?.errors?.email?.[0]) {
      errorMessage.value = error.response.data.errors.email[0]
    } else if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message
    } else {
      errorMessage.value = 'Gagal masuk ke sistem'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.login-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 200px);
}

.login-card {
  width: 100%;
  max-width: 380px;
}

.login-header {
  margin-bottom: 20px;
}

.login-title {
  font-size: 18px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--text-main);
  margin-bottom: 4px;
}

.login-desc {
  font-size: 13px;
  color: var(--text-muted);
}

.btn-block {
  width: 100%;
  margin-top: 8px;
}
</style>
