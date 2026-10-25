<template>
  <div class="gadget-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Daftar Gadget</h1>
        <p class="page-desc">Pantau perkembangan harga dan status wishlist Anda</p>
      </div>
      <router-link to="/gadgets/new" class="btn btn-primary">
        + Tambah Gadget
      </router-link>
    </div>

    <div class="card filter-card">
      <div class="filter-grid">
        <div class="filter-item search-item">
          <input
            v-model="searchQuery"
            type="text"
            class="form-input"
            placeholder="Cari berdasarkan nama atau merek..."
            @input="handleSearchInput"
          />
        </div>

        <div class="filter-item select-item">
          <select v-model="selectedCategory" class="form-select" @change="fetchGadgets(1)">
            <option value="">Semua Kategori</option>
            <option value="smartphone">Smartphone</option>
            <option value="laptop">Laptop</option>
            <option value="audio">Audio</option>
            <option value="wearable">Wearable</option>
            <option value="camera">Kamera</option>
            <option value="accessory">Aksesoris</option>
            <option value="other">Lainnya</option>
          </select>
        </div>

        <div class="filter-item select-item">
          <select v-model="selectedStatus" class="form-select" @change="fetchGadgets(1)">
            <option value="">Semua Status</option>
            <option value="wishlist">Wishlist</option>
            <option value="purchased">Sudah Dibeli</option>
            <option value="dropped">Dibatalkan</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="successMessage" class="alert-success">
      {{ successMessage }}
    </div>

    <div class="card table-card">
      <div v-if="isLoading" class="empty-state">
        Memuat data gadget...
      </div>

      <div v-else-if="gadgets.length === 0" class="empty-state">
        <p>Tidak ada data gadget yang sesuai</p>
      </div>

      <div v-else class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th class="col-gadget">Gadget</th>
              <th class="col-price">Target</th>
              <th class="col-price">Harga Terkini</th>
              <th class="col-status">Status</th>
              <th class="col-priority">Prioritas</th>
              <th class="col-actions text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="gadget in gadgets" :key="gadget.id">
              <td class="col-gadget">
                <div class="gadget-info">
                  <router-link :to="`/gadgets/${gadget.id}`" class="gadget-name">
                    {{ gadget.name }}
                  </router-link>
                  <span class="gadget-sub">
                    {{ gadget.brand }} • {{ formatCategory(gadget.category) }}
                  </span>
                </div>
              </td>
              <td class="col-price">
                <span class="price-target-value">
                  {{ formatRupiah(gadget.target_price) }}
                </span>
              </td>
              <td class="col-price">
                <div class="price-current-group">
                  <span class="price-current-value">
                    {{ formatRupiah(gadget.current_price) }}
                  </span>
                  <span v-if="gadget.target_reached" class="target-reached-label">
                    Mencapai target
                  </span>
                  <span v-else class="target-gap-label">
                    +{{ formatRupiah(gadget.price_gap) }}
                  </span>
                </div>
              </td>
              <td class="col-status">
                <span class="status-indicator">
                  <span :class="['status-dot', `dot-${gadget.status}`]"></span>
                  <span>{{ formatStatus(gadget.status) }}</span>
                </span>
              </td>
              <td class="col-priority">
                <span :class="['priority-label', `priority-${gadget.priority}`]">
                  {{ formatPriority(gadget.priority) }}
                </span>
              </td>
              <td class="col-actions text-right">
                <div class="action-links">
                  <router-link :to="`/gadgets/${gadget.id}`" class="action-link link-detail">
                    Detail
                  </router-link>
                  <router-link :to="`/gadgets/${gadget.id}/edit`" class="action-link link-edit">
                    Ubah
                  </router-link>
                  <button
                    class="action-link link-delete"
                    :disabled="deletingId === gadget.id"
                    @click="handleDelete(gadget)"
                  >
                    {{ deletingId === gadget.id ? 'Menghapus...' : 'Hapus' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.last_page > 1" class="pagination-bar">
        <button
          class="btn btn-secondary btn-sm"
          :disabled="pagination.current_page <= 1"
          @click="fetchGadgets(pagination.current_page - 1)"
        >
          Sebelumnya
        </button>
        <span class="pagination-info">
          Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
        </span>
        <button
          class="btn btn-secondary btn-sm"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="fetchGadgets(pagination.current_page + 1)"
        >
          Berikutnya
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import http from '../api/http'
import { formatRupiah } from '../utils/format'

const gadgets = ref([])
const isLoading = ref(false)
const deletingId = ref(null)
const successMessage = ref('')

const searchQuery = ref('')
const selectedCategory = ref('')
const selectedStatus = ref('')
let searchTimeout = null

const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})

const formatCategory = (category) => {
  const map = {
    smartphone: 'Smartphone',
    laptop: 'Laptop',
    audio: 'Audio',
    wearable: 'Wearable',
    camera: 'Kamera',
    accessory: 'Aksesoris',
    other: 'Lainnya',
  }
  return map[category] || category
}

const formatStatus = (status) => {
  const map = {
    wishlist: 'Wishlist',
    purchased: 'Sudah Dibeli',
    dropped: 'Dibatalkan',
  }
  return map[status] || status
}

const formatPriority = (priority) => {
  const map = {
    low: 'Rendah',
    medium: 'Sedang',
    high: 'Tinggi',
  }
  return map[priority] || priority
}

const fetchGadgets = async (page = 1) => {
  isLoading.value = true
  try {
    const params = {
      page,
    }
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (selectedCategory.value) params.category = selectedCategory.value
    if (selectedStatus.value) params.status = selectedStatus.value

    const response = await http.get('/gadgets', { params })
    gadgets.value = response.data?.data || []

    const meta = response.data?.meta
    if (meta) {
      pagination.value = {
        current_page: meta.current_page,
        last_page: meta.last_page,
        total: meta.total,
      }
    }
  } catch {
    gadgets.value = []
  } finally {
    isLoading.value = false
  }
}

const handleSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchGadgets(1)
  }, 300)
}

const handleDelete = async (gadget) => {
  const isConfirmed = window.confirm(`Hapus ${gadget.name} dari daftar?`)
  if (!isConfirmed) return

  deletingId.value = gadget.id
  try {
    const response = await http.delete(`/gadgets/${gadget.id}`)
    successMessage.value = response.data?.message || 'Gadget berhasil dihapus'
    setTimeout(() => {
      successMessage.value = ''
    }, 3000)
    await fetchGadgets(pagination.value.current_page)
  } catch {
  } finally {
    deletingId.value = null
  }
}

onMounted(() => {
  fetchGadgets(1)
})
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.page-title {
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text-main);
}

.page-desc {
  font-size: 13px;
  color: var(--text-muted);
  margin-top: 2px;
}

.filter-card {
  margin-bottom: 16px;
  padding: 14px 16px;
}

.filter-grid {
  display: flex;
  align-items: center;
  gap: 12px;
}

.search-item {
  flex: 1;
  min-width: 240px;
}

.select-item {
  flex: 0 0 170px;
}

@media (max-width: 768px) {
  .filter-grid {
    flex-direction: column;
    align-items: stretch;
  }
  .select-item {
    flex: auto;
  }
}

.table-card {
  padding: 0;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  padding: 12px 16px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-muted);
  background-color: #f8fafc;
  border-bottom: 1px solid var(--border-color);
  white-space: nowrap;
}

.data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.data-table tbody tr:hover td {
  background-color: #f8fafc;
}

.col-gadget {
  width: 32%;
}

.col-price {
  width: 18%;
  font-variant-numeric: tabular-nums;
}

.col-status {
  width: 15%;
}

.col-priority {
  width: 12%;
}

.col-actions {
  width: 23%;
  white-space: nowrap;
}

.text-right {
  text-align: right;
}

.gadget-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.gadget-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-main);
  text-decoration: none;
}

.gadget-name:hover {
  color: var(--primary-accent);
  text-decoration: none;
}

.gadget-sub {
  font-size: 12px;
  color: var(--text-muted);
}

.price-target-value {
  font-size: 13px;
  color: var(--text-muted);
}

.price-current-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.price-current-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-main);
}

.target-reached-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--success);
}

.target-gap-label {
  font-size: 11px;
  color: var(--text-subtle);
}

.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--text-main);
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.dot-wishlist {
  background-color: #3b82f6;
}

.dot-purchased {
  background-color: #16a34a;
}

.dot-dropped {
  background-color: #94a3b8;
}

.priority-label {
  font-size: 13px;
  font-weight: 500;
}

.priority-high {
  color: #dc2626;
}

.priority-medium {
  color: #d97706;
}

.priority-low {
  color: #64748b;
}

.action-links {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  justify-content: flex-end;
}

.action-link {
  font-size: 13px;
  font-weight: 500;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  text-decoration: none;
}

.link-detail {
  color: var(--text-main);
}

.link-detail:hover {
  color: var(--primary-accent);
}

.link-edit {
  color: var(--primary-accent);
}

.link-edit:hover {
  text-decoration: underline;
}

.link-delete {
  color: var(--danger);
}

.link-delete:hover:not(:disabled) {
  text-decoration: underline;
}

.link-delete:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-top: 1px solid var(--border-color);
  background-color: #f8fafc;
}

.pagination-info {
  font-size: 13px;
  color: var(--text-muted);
}

.alert-success {
  background-color: var(--success-bg);
  border: 1px solid var(--success-border);
  color: var(--success);
  padding: 10px 14px;
  border-radius: var(--radius-md);
  font-size: 13px;
  margin-bottom: 16px;
}
</style>
