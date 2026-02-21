<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h1 class="page-title">Ventas</h1>
        <p class="page-sub">{{ total }} ventas registradas</p>
      </div>
    </header>
    <div class="toolbar">
      <div class="search-wrap">
        <input v-model="search" @input="debouncedLoad" placeholder="Buscar por número, cliente..." class="search-input" />
      </div>
      <select v-model="filterPeriod" @change="loadSales" class="select">
        <option value="">Todas las fechas</option>
        <option value="today">Hoy</option>
        <option value="this_month">Este mes</option>
        <option value="this_year">Este año</option>
      </select>
      <select v-model="filterStatus" @change="loadSales" class="select">
        <option value="">Todos los estados</option>
        <option value="completed">Completadas</option>
        <option value="cancelled">Canceladas</option>
      </select>
    </div>
    <!-- Stats bar -->
    <div class="stats-bar" v-if="stats">
      <div class="stat-item"><div class="stat-label">Total</div><div class="stat-val">{{ stats.total_sales }}</div></div>
      <div class="stat-item"><div class="stat-label">Ingresos</div><div class="stat-val accent">S/ {{ fmt(stats.total_revenue) }}</div></div>
      <div class="stat-item"><div class="stat-label">Ganancia</div><div class="stat-val green">S/ {{ fmt(stats.total_profit) }}</div></div>
      <div class="stat-item"><div class="stat-label">Ticket prom.</div><div class="stat-val">S/ {{ fmt(stats.average_sale) }}</div></div>
    </div>
    <div class="table-wrap">
      <div v-if="loading" class="loading-state"><div class="spinner"></div> Cargando...</div>
      <table v-else class="table">
        <thead>
          <tr><th>N° Venta</th><th>Cliente</th><th>Fecha</th><th>Subtotal</th><th>IGV</th><th>Total</th><th>Pago</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-for="s in sales" :key="s.id">
            <td><span class="sale-number">{{ s.sale_number }}</span></td>
            <td>
              <div class="prod-name">{{ s.customer?.name }}</div>
              <div class="prod-sub">{{ s.customer?.document_number }}</div>
            </td>
            <td class="mono">{{ formatDate(s.sale_date) }}</td>
            <td class="mono">S/ {{ fmt(s.subtotal_pen) }}</td>
            <td class="mono text3">S/ {{ fmt(s.tax_pen) }}</td>
            <td class="mono accent font-bold">S/ {{ fmt(s.total_pen) }}</td>
            <td><span class="pay-badge">{{ s.payment_method }}</span></td>
           <td>
  <span
    :class="['status-badge', s.status === 'completed' ? 'status-ok' : 'status-cancelled']"
  >
    {{ s.status === 'completed' ? 'Completada' : 'Cancelada' }}
  </span>
</td>

<td>
  <div class="action-btns">
    <button class="btn-icon" @click="viewSale(s)">👁</button>

    <button
      v-if="s.status === 'completed'"
      class="btn-icon btn-icon-del"
      @click="cancelSale(s)"
    >
      ✕
    </button>
  </div>
</td>
          </tr>
          <tr v-if="sales.length === 0"><td colspan="9" class="empty-row">No se encontraron ventas</td></tr>
        </tbody>
      </table>
    </div>
    <div class="pagination" v-if="lastPage > 1">
      <button :disabled="currentPage === 1" @click="goPage(currentPage - 1)" class="page-btn">‹</button>
      <span class="page-info">Página {{ currentPage }} de {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="goPage(currentPage + 1)" class="page-btn">›</button>
    </div>
    <!-- Modal detalle -->
    <div v-if="showDetail && selectedSale" class="modal-overlay" @click.self="showDetail = false">
      <div class="modal modal-lg">
        <div class="modal-header">
          <div>
            <h2 class="modal-title">{{ selectedSale.sale_number }}</h2>
            <p class="modal-sub">{{ formatDate(selectedSale.sale_date) }}</p>
          </div>
          <button class="modal-close" @click="showDetail = false">✕</button>
        </div>
        <div class="modal-form">
          <div class="detail-grid">
            <div class="detail-section">
              <div class="detail-label">Cliente</div>
              <div class="detail-val">{{ selectedSale.customer?.name }}</div>
              <div class="detail-sub">{{ selectedSale.customer?.document_type }}: {{ selectedSale.customer?.document_number }}</div>
            </div>
            <div class="detail-section">
              <div class="detail-label">Pago</div>
              <div class="detail-val">{{ selectedSale.payment_method }}</div>
              <div class="detail-sub">{{ selectedSale.payment_status }}</div>
            </div>
            <div class="detail-section">
              <div class="detail-label">Tipo de cambio</div>
              <div class="detail-val">S/ {{ selectedSale.exchange_rate }}</div>
            </div>
          </div>
          <table class="table" style="margin-top:16px">
            <thead><tr><th>Producto</th><th>Cant.</th><th>P. Unit.</th><th>Total</th></tr></thead>
            <tbody>
              <tr v-for="item in selectedSale.items" :key="item.id">
                <td><div class="prod-name">{{ item.product?.name }}</div><div class="prod-sub">{{ item.product?.brand }}</div></td>
                <td class="mono">{{ item.quantity }}</td>
                <td class="mono">S/ {{ fmt(item.unit_price_pen) }}</td>
                <td class="mono accent">S/ {{ fmt(item.total_pen) }}</td>
              </tr>
            </tbody>
          </table>
          <div class="totals-box">
            <div class="total-row"><span>Subtotal</span><span>S/ {{ fmt(selectedSale.subtotal_pen) }}</span></div>
            <div class="total-row"><span>Descuento</span><span class="red">- S/ {{ fmt(selectedSale.discount_pen) }}</span></div>
            <div class="total-row"><span>IGV (18%)</span><span>S/ {{ fmt(selectedSale.tax_pen) }}</span></div>
            <div class="total-row total-final"><span>TOTAL</span><span class="accent">S/ {{ fmt(selectedSale.total_pen) }}</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from "vue"
const sales = ref([])
const loading = ref(true)
const search = ref("")
const filterPeriod = ref("this_month")
const filterStatus = ref("")
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const stats = ref(null)
const showDetail = ref(false)
const selectedSale = ref(null)
const fmt = n => parseFloat(n || 0).toLocaleString("es-PE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const formatDate = d => d ? new Date(d).toLocaleDateString("es-PE", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" }) : ""
let debounceTimer = null
const debouncedLoad = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadSales(), 350) }
const loadSales = async (page = 1) => {
  loading.value = true; currentPage.value = page
  const params = new URLSearchParams({ page, per_page: 10,
    ...(search.value && { search: search.value }),
    ...(filterPeriod.value && { period: filterPeriod.value }),
    ...(filterStatus.value && { status: filterStatus.value }),
  })
  try {
    const [salesRes, statsRes] = await Promise.all([
      fetch(`/api/sales?${params}`),
      fetch(`/api/sales/stats?period=${filterPeriod.value || "this_month"}`)
    ])
    const salesJson = await salesRes.json()
    const statsJson = await statsRes.json()
    sales.value = salesJson.data?.data || []
    lastPage.value = salesJson.data?.last_page || 1
    total.value = salesJson.data?.total || 0
    if (statsJson.success) stats.value = statsJson.data
  } finally { loading.value = false }
}
const goPage = p => loadSales(p)
const viewSale = async s => {
  const res = await fetch(`/api/sales/${s.id}`)
  const json = await res.json()
  if (json.success) { selectedSale.value = json.data; showDetail.value = true }
}
const cancelSale = async s => {
  if (!confirm(`¿Cancelar la venta ${s.sale_number}? Se devolverá el stock.`)) return
  const res = await fetch(`/api/sales/${s.id}/cancel`, { method: "POST" })
  const json = await res.json()
  if (json.success) loadSales(currentPage.value)
}
onMounted(loadSales)
</script>
<style scoped>
.page { padding: 32px; max-width: 1200px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title { font-family: "Syne", sans-serif; font-size: 28px; font-weight: 800; letter-spacing: -0.02em; }
.page-sub { font-size: 12px; color: var(--text3); margin-top: 4px; }
.toolbar { display: flex; gap: 12px; margin-bottom: 16px; align-items: center; }
.search-wrap { flex: 1; }
.search-input { width: 100%; padding: 9px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: "DM Mono", monospace; font-size: 13px; }
.search-input:focus { outline: none; border-color: var(--accent); }
.select { padding: 8px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: "DM Mono", monospace; font-size: 12px; cursor: pointer; }
.stats-bar { display: flex; gap: 0; background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 16px; }
.stat-item { flex: 1; padding: 16px 20px; border-right: 1px solid var(--border); }
.stat-item:last-child { border-right: none; }
.stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text3); margin-bottom: 4px; }
.stat-val { font-family: "Syne", sans-serif; font-size: 18px; font-weight: 700; }
.table-wrap { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th { padding: 12px 16px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text3); border-bottom: 1px solid var(--border); font-weight: 500; }
.table td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: rgba(255,255,255,0.02); }
.sale-number { font-family: "DM Mono", monospace; font-size: 12px; color: var(--accent); }
.prod-name { font-weight: 500; }
.prod-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.mono { font-family: "DM Mono", monospace; }
.text3 { color: var(--text3); }
.font-bold { font-weight: 600; }
.pay-badge { padding: 3px 8px; background: var(--bg3); border: 1px solid var(--border); border-radius: 4px; font-size: 11px; color: var(--text2); }
.status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; }
.status-ok { background: rgba(34,197,94,0.1); color: var(--green); }
.status-cancelled { background: rgba(239,68,68,0.1); color: var(--red); }
.action-btns { display: flex; gap: 4px; }
.btn-icon { width: 28px; height: 28px; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: var(--text2); cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.btn-icon:hover { background: var(--bg3); color: var(--text); }
.btn-icon-del:hover { border-color: var(--red); color: var(--red); }
.empty-row { text-align: center; color: var(--text3); padding: 40px !important; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 16px; }
.page-btn { padding: 6px 14px; background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); cursor: pointer; font-size: 16px; }
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.page-info { font-size: 12px; color: var(--text3); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 200; backdrop-filter: blur(4px); }
.modal { background: var(--bg2); border: 1px solid var(--border); border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }
.modal-lg { max-width: 680px; }
.modal-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 24px; border-bottom: 1px solid var(--border); }
.modal-title { font-family: "Syne", sans-serif; font-size: 18px; font-weight: 700; }
.modal-sub { font-size: 12px; color: var(--text3); margin-top: 2px; }
.modal-close { width: 28px; height: 28px; background: var(--bg3); border: none; border-radius: 6px; color: var(--text2); cursor: pointer; font-size: 14px; }
.modal-form { padding: 24px; }
.detail-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px; }
.detail-section { background: var(--bg3); border-radius: 8px; padding: 12px; }
.detail-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text3); margin-bottom: 4px; }
.detail-val { font-family: "Syne", sans-serif; font-size: 15px; font-weight: 700; }
.detail-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.totals-box { background: var(--bg3); border-radius: 8px; padding: 16px; margin-top: 16px; }
.total-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px solid var(--border); }
.total-row:last-child { border-bottom: none; }
.total-final { font-family: "Syne", sans-serif; font-size: 16px; font-weight: 700; margin-top: 4px; }
.loading-state { display: flex; align-items: center; gap: 10px; padding: 40px; color: var(--text3); }
.spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.accent { color: var(--accent) !important; }
.green { color: var(--green) !important; }
.red { color: var(--red) !important; }
</style>