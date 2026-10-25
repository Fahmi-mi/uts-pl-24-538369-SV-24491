<template>
  <div class="detail-page">
    <div class="detail-header">
      <div>
        <router-link to="/gadgets" class="back-link">
          ← Kembali ke Daftar Gadget
        </router-link>
        <h1 class="page-title">{{ gadget?.name || 'Detail Gadget' }}</h1>
        <p class="page-desc">{{ gadget?.brand }} • {{ formatCategory(gadget?.category) }}</p>
      </div>

      <div class="header-actions">
        <router-link v-if="gadget" :to="`/gadgets/${gadget.id}/edit`" class="btn btn-secondary btn-sm">
          Ubah Data
        </router-link>
      </div>
    </div>

    <div v-if="isLoading" class="card empty-state">
      Memuat informasi gadget...
    </div>

    <div v-else-if="errorMessage" class="alert-danger">
      {{ errorMessage }}
    </div>

    <div v-else-if="gadget" class="detail-content">
      <div class="price-cards-grid">
        <div class="card price-summary-card">
          <span class="price-card-label">Target Harga</span>
          <span class="price-card-value">{{ formatRupiah(gadget.target_price) }}</span>
        </div>

        <div class="card price-summary-card">
          <span class="price-card-label">Harga Terkini</span>
          <span class="price-card-value">{{ formatRupiah(gadget.current_price) }}</span>
        </div>

        <div class="card price-summary-card">
          <span class="price-card-label">Status & Prioritas</span>
          <div class="meta-status-row">
            <span class="status-indicator">
              <span :class="['status-dot', `dot-${gadget.status}`]"></span>
              <span>{{ formatStatus(gadget.status) }}</span>
            </span>
            <span class="meta-divider">•</span>
            <span :class="['priority-label', `priority-${gadget.priority}`]">
              Prioritas {{ formatPriority(gadget.priority) }}
            </span>
          </div>
        </div>
      </div>

      <div class="card info-card">
        <h2 class="section-title">Informasi Produk</h2>
        <div class="info-grid">
          <div class="info-row">
            <span class="info-label">Nama Gadget</span>
            <span class="info-value">{{ gadget.name }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Merek</span>
            <span class="info-value">{{ gadget.brand }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Kategori</span>
            <span class="info-value capitalize">{{ formatCategory(gadget.category) }}</span>
          </div>
          <div v-if="gadget.store" class="info-row">
            <span class="info-label">Toko / Platform</span>
            <span class="info-value">{{ gadget.store }}</span>
          </div>
          <div v-if="gadget.product_url" class="info-row">
            <span class="info-label">Tautan Produk</span>
            <a :href="gadget.product_url" target="_blank" rel="noopener noreferrer" class="info-value link-external">
              Buka Halaman Toko ↗
            </a>
          </div>
          <div v-if="gadget.notes" class="info-row span-full">
            <span class="info-label">Catatan</span>
            <p class="info-value notes-text">{{ gadget.notes }}</p>
          </div>
        </div>
      </div>

      <div class="card history-card">
        <h2 class="section-title">Riwayat Fluktuasi Harga</h2>
        <p class="section-desc">Pencatatan riwayat setiap kali harga terbaru diperbarui</p>

        <div v-if="isHistoriesLoading" class="empty-state">
          Memuat riwayat harga...
        </div>

        <div v-else-if="priceHistories.length === 0" class="empty-state">
          Belum ada riwayat perubahan harga
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-time">Waktu Pencatatan</th>
                <th class="col-price">Harga Tercatat</th>
                <th class="col-status">Status terhadap Target</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in priceHistories" :key="item.id">
                <td class="col-time text-muted">{{ formatDate(item.recorded_at) }}</td>
                <td class="col-price font-medium">{{ formatRupiah(item.price) }}</td>
                <td class="col-status">
                  <span v-if="item.price <= gadget.target_price" class="target-reached-label">
                    Mencapai target
                  </span>
                  <span v-else class="text-muted text-sm">
                    Lebih tinggi +{{ formatRupiah(item.price - gadget.target_price) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import http from '../api/http'
import { formatRupiah, formatDate } from '../utils/format'

const route = useRoute()

const gadget = ref(null)
const priceHistories = ref([])
const isLoading = ref(true)
const isHistoriesLoading = ref(true)
const errorMessage = ref('')

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

const fetchGadget = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const response = await http.get(`/gadgets/${route.params.id}`)
    gadget.value = response.data?.data
  } catch {
    errorMessage.value = 'Data gadget tidak ditemukan'
  } finally {
    isLoading.value = false
  }
}

const fetchHistories = async () => {
  isHistoriesLoading.value = true
  try {
    const response = await http.get(`/gadgets/${route.params.id}/price-histories`)
    priceHistories.value = response.data?.data || []
  } catch {
    priceHistories.value = []
  } finally {
    isHistoriesLoading.value = false
  }
}

onMounted(() => {
  fetchGadget()
  fetchHistories()
})
</script>

<style scoped>
.detail-page {
  max-width: 920px;
  margin: 0 auto;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
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

.page-title {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text-main);
}

.page-desc {
  font-size: 13px;
  color: var(--text-muted);
  margin-top: 2px;
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.price-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
}

.price-summary-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.price-card-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.price-card-value {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text-main);
  font-variant-numeric: tabular-nums;
}

.target-reached-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--success);
}

.meta-status-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 4px 0;
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

.meta-divider {
  color: var(--text-subtle);
  font-size: 12px;
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

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-main);
  letter-spacing: -0.01em;
  margin-bottom: 4px;
}

.section-desc {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 16px;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 16px;
}

.info-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.span-full {
  grid-column: span 2;
}

@media (max-width: 640px) {
  .info-grid {
    grid-template-columns: 1fr;
  }
  .span-full {
    grid-column: span 1;
  }
}

.info-label {
  font-size: 12px;
  color: var(--text-muted);
}

.info-value {
  font-size: 14px;
  color: var(--text-main);
  font-weight: 500;
}

.capitalize {
  text-transform: capitalize;
}

.notes-text {
  background-color: #fafafa;
  border: 1px solid var(--border-color);
  padding: 10px 14px;
  border-radius: var(--radius-md);
  font-size: 13px;
  color: var(--text-main);
  white-space: pre-wrap;
  margin-top: 4px;
}

.link-external {
  color: var(--primary-accent);
}

.history-card {
  padding: 0;
  overflow: hidden;
}

.history-card .section-title,
.history-card .section-desc {
  padding: 16px 20px 0;
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

.col-time {
  width: 35%;
  font-size: 13px;
}

.col-price {
  width: 35%;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.col-status {
  width: 30%;
}

.font-medium {
  font-weight: 600;
}

.text-muted {
  color: var(--text-muted);
}

.text-sm {
  font-size: 12px;
}
</style>
