<template>
  <div class="form-page">
    <div class="form-header">
      <router-link to="/gadgets" class="back-link">
        ← Kembali ke Daftar Gadget
      </router-link>
      <h1 class="form-title">{{ isEdit ? 'Ubah Data Gadget' : 'Tambah Gadget Baru' }}</h1>
      <p class="form-desc">
        {{ isEdit ? 'Perbarui informasi dan harga terkini gadget Anda' : 'Masukkan informasi gadget incaran beserta target harga' }}
      </p>
    </div>

    <div v-if="generalError" class="alert-danger">
      {{ generalError }}
    </div>

    <div class="card">
      <form @submit.prevent="handleSubmit">
        <div class="form-grid">
          <div class="form-group span-2">
            <label for="name" class="form-label">Nama Gadget *</label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              class="form-input"
              :class="{ 'has-error': errors.name }"
              placeholder="Contoh: Sony WH-1000XM5"
              required
            />
            <span v-if="errors.name" class="form-error">{{ errors.name[0] }}</span>
          </div>

          <div class="form-group">
            <label for="brand" class="form-label">Merek *</label>
            <input
              id="brand"
              v-model="form.brand"
              type="text"
              class="form-input"
              :class="{ 'has-error': errors.brand }"
              placeholder="Contoh: Sony"
              required
            />
            <span v-if="errors.brand" class="form-error">{{ errors.brand[0] }}</span>
          </div>

          <div class="form-group">
            <label for="category" class="form-label">Kategori *</label>
            <select
              id="category"
              v-model="form.category"
              class="form-select"
              :class="{ 'has-error': errors.category }"
              required
            >
              <option value="smartphone">Smartphone</option>
              <option value="laptop">Laptop</option>
              <option value="audio">Audio</option>
              <option value="wearable">Wearable</option>
              <option value="camera">Kamera</option>
              <option value="accessory">Aksesoris</option>
              <option value="other">Lainnya</option>
            </select>
            <span v-if="errors.category" class="form-error">{{ errors.category[0] }}</span>
          </div>

          <div class="form-group">
            <label for="target_price" class="form-label">Target Harga (Rp) *</label>
            <input
              id="target_price"
              v-model.number="form.target_price"
              type="number"
              min="1"
              class="form-input"
              :class="{ 'has-error': errors.target_price }"
              placeholder="Contoh: 4500000"
              required
            />
            <span v-if="errors.target_price" class="form-error">{{ errors.target_price[0] }}</span>
          </div>

          <div class="form-group">
            <label for="current_price" class="form-label">Harga Saat Ini (Rp) *</label>
            <input
              id="current_price"
              v-model.number="form.current_price"
              type="number"
              min="1"
              class="form-input"
              :class="{ 'has-error': errors.current_price }"
              placeholder="Contoh: 4800000"
              required
            />
            <span v-if="errors.current_price" class="form-error">{{ errors.current_price[0] }}</span>
          </div>

          <div class="form-group">
            <label for="priority" class="form-label">Prioritas</label>
            <select id="priority" v-model="form.priority" class="form-select">
              <option value="low">Rendah</option>
              <option value="medium">Sedang</option>
              <option value="high">Tinggi</option>
            </select>
            <span v-if="errors.priority" class="form-error">{{ errors.priority[0] }}</span>
          </div>

          <div class="form-group">
            <label for="status" class="form-label">Status</label>
            <select id="status" v-model="form.status" class="form-select">
              <option value="wishlist">Wishlist</option>
              <option value="purchased">Sudah Dibeli</option>
              <option value="dropped">Dibatalkan</option>
            </select>
            <span v-if="errors.status" class="form-error">{{ errors.status[0] }}</span>
          </div>

          <div class="form-group">
            <label for="store" class="form-label">Toko / Platform</label>
            <input
              id="store"
              v-model="form.store"
              type="text"
              class="form-input"
              :class="{ 'has-error': errors.store }"
              placeholder="Contoh: Tokopedia, iBox"
            />
            <span v-if="errors.store" class="form-error">{{ errors.store[0] }}</span>
          </div>

          <div class="form-group">
            <label for="product_url" class="form-label">URL Produk</label>
            <input
              id="product_url"
              v-model="form.product_url"
              type="url"
              class="form-input"
              :class="{ 'has-error': errors.product_url }"
              placeholder="https://..."
            />
            <span v-if="errors.product_url" class="form-error">{{ errors.product_url[0] }}</span>
          </div>

          <div class="form-group span-2">
            <label for="notes" class="form-label">Catatan Tambahan</label>
            <textarea
              id="notes"
              v-model="form.notes"
              rows="3"
              class="form-textarea"
              :class="{ 'has-error': errors.notes }"
              placeholder="Contoh: Incar varian warna silver saat ada promo cashback"
            ></textarea>
            <span v-if="errors.notes" class="form-error">{{ errors.notes[0] }}</span>
          </div>
        </div>

        <div class="form-actions">
          <router-link to="/gadgets" class="btn btn-secondary">
            Batal
          </router-link>
          <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : (isEdit ? 'Perbarui Gadget' : 'Simpan Gadget') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import http from '../api/http'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => Boolean(route.params.id))
const isSubmitting = ref(false)
const generalError = ref('')
const errors = ref({})

const form = ref({
  name: '',
  brand: '',
  category: 'smartphone',
  store: '',
  product_url: '',
  target_price: '',
  current_price: '',
  priority: 'medium',
  status: 'wishlist',
  notes: '',
})

const fetchGadget = async (id) => {
  try {
    const response = await http.get(`/gadgets/${id}`)
    const data = response.data?.data
    if (data) {
      form.value = {
        name: data.name || '',
        brand: data.brand || '',
        category: data.category || 'smartphone',
        store: data.store || '',
        product_url: data.product_url || '',
        target_price: data.target_price || '',
        current_price: data.current_price || '',
        priority: data.priority || 'medium',
        status: data.status || 'wishlist',
        notes: data.notes || '',
      }
    }
  } catch {
    generalError.value = 'Gadget tidak ditemukan'
  }
}

const handleSubmit = async () => {
  isSubmitting.value = true
  generalError.value = ''
  errors.value = {}

  try {
    const payload = {
      ...form.value,
      target_price: Number(form.value.target_price),
      current_price: Number(form.value.current_price),
    }

    if (isEdit.value) {
      await http.put(`/gadgets/${route.params.id}`, payload)
    } else {
      await http.post('/gadgets', payload)
    }

    router.push('/gadgets')
  } catch (error) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else if (error.response?.data?.message) {
      generalError.value = error.response.data.message
    } else {
      generalError.value = 'Terjadi kesalahan saat menyimpan data'
    }
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  if (isEdit.value) {
    fetchGadget(route.params.id)
  }
})
</script>

<style scoped>
.form-page {
  max-width: 720px;
  margin: 0 auto;
}

.form-header {
  margin-bottom: 24px;
}

.back-link {
  display: inline-block;
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 8px;
}

.back-link:hover {
  color: var(--text-main);
  text-decoration: none;
}

.form-title {
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text-main);
}

.form-desc {
  font-size: 13px;
  color: var(--text-muted);
  margin-top: 2px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.span-2 {
  grid-column: span 2;
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .span-2 {
    grid-column: span 1;
  }
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--border-color);
}
</style>
