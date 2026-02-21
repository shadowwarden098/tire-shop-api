<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h1 class="page-title">Servicios</h1>
        <p class="page-sub">Catálogo y registros de servicios realizados</p>
      </div>
    </header>

    <div class="tabs">
      <button :class="['tab', { active: activeTab === 'records' }]" @click="activeTab = 'records'">Registros</button>
      <button :class="['tab', { active: activeTab === 'catalog' }]" @click="activeTab = 'catalog'">Catálogo</button>
    </div>

    <!-- TAB: REGISTROS -->
    <div v-if="activeTab === 'records'">
      <div class="toolbar">
        <div class="search-wrap">
          <input v-model="recSearch" @input="debouncedRecLoad" placeholder="Buscar por cliente, placa, número..." class="search-input" />
        </div>
        <select v-model="recPeriod" @change="loadRecords()" class="select">
          <option value="">Todos</option>
          <option value="today">Hoy</option>
          <option value="this_month">Este mes</option>
          <option value="this_year">Este año</option>
        </select>
      </div>
      <div class="table-wrap">
        <div v-if="recLoading" class="loading-state"><div class="spinner"></div> Cargando...</div>
        <table v-else class="table">
          <thead>
            <tr><th>N° Registro</th><th>Cliente</th><th>Vehículo</th><th>Servicio</th><th>Fecha</th><th>Total</th><th>Pago</th><th>Estado</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in records" :key="r.id">
              <td><span class="sale-number">{{ r.record_number }}</span></td>
              <td><div class="prod-name">{{ r.customer?.name }}</div><div class="prod-sub">{{ r.customer?.document_number }}</div></td>
              <td><div class="prod-name">{{ r.vehicle?.brand }} {{ r.vehicle?.model }}</div><div class="prod-sub">{{ r.vehicle?.plate }}</div></td>
              <td><span class="service-name">{{ r.service?.name }}</span><div class="prod-sub">{{ r.service?.category }}</div></td>
              <td class="mono">{{ formatDate(r.service_date) }}</td>
              <td class="mono accent">S/ {{ fmt(r.total_pen) }}</td>
              <td><span class="pay-badge">{{ r.payment_method }}</span></td>
              <td>
                <span :class="['status-badge', r.status === 'completed' ? 'status-ok' : 'status-cancelled']">
                  {{ r.status === 'completed' ? 'Completado' : 'Cancelado' }}
                </span>
              </td>
            </tr>
            <tr v-if="records.length === 0"><td colspan="8" class="empty-row">No se encontraron registros</td></tr>
          </tbody>
        </table>
      </div>
      <div class="pagination" v-if="recLastPage > 1">
        <button :disabled="recPage === 1" @click="loadRecords(recPage - 1)" class="page-btn">‹</button>
        <span class="page-info">Página {{ recPage }} de {{ recLastPage }}</span>
        <button :disabled="recPage === recLastPage" @click="loadRecords(recPage + 1)" class="page-btn">›</button>
      </div>
    </div>

    <!-- TAB: CATÁLOGO -->
    <div v-if="activeTab === 'catalog'">
      <div class="toolbar">
        <div class="search-wrap">
          <input v-model="catSearch" @input="debouncedCatLoad" placeholder="Buscar servicio..." class="search-input" />
        </div>
        <select v-model="catCategory" @change="loadCatalog()" class="select">
          <option value="">Todas las categorías</option>
          <option value="Mantenimiento">Mantenimiento</option>
          <option value="Instalación">Instalación</option>
          <option value="Reparación">Reparación</option>
        </select>
        <button class="btn-primary" @click="openServiceModal()">+ Nuevo Servicio</button>
      </div>
      <div class="services-grid">
        <div v-if="catLoading" class="loading-state"><div class="spinner"></div> Cargando...</div>
        <template v-else>
          <div v-for="s in catalog" :key="s.id" class="service-card">
            <div class="service-card-header">
              <span :class="['cat-badge', catClass(s.category)]">{{ s.category }}</span>
              <div class="action-btns">
                <button class="btn-icon" @click="openServiceModal(s)">✎</button>
                <button class="btn-icon btn-icon-del" @click="deleteService(s)">✕</button>
              </div>
            </div>
            <div class="service-card-name">{{ s.name }}</div>
            <div class="service-card-code">{{ s.code }}</div>
            <div class="service-card-desc" v-if="s.description">{{ s.description }}</div>
            <div class="service-card-footer">
              <div class="service-price">S/ {{ fmt(s.price_pen) }}</div>
              <div class="service-duration" v-if="s.duration_minutes">⏱ {{ s.duration_minutes }} min</div>
            </div>
          </div>
          <div v-if="catalog.length === 0" class="empty-grid">No hay servicios registrados</div>
        </template>
      </div>
    </div>

    <!-- Modal Servicio -->
    <div v-if="showServiceModal" class="modal-overlay" @click.self="showServiceModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2 class="modal-title">{{ editingService ? 'Editar Servicio' : 'Nuevo Servicio' }}</h2>
          <button class="modal-close" @click="showServiceModal = false">✕</button>
        </div>
        <form @submit.prevent="saveService" class="modal-form">
          <div class="form-grid">
            <div class="form-group span-2"><label>Nombre</label><input v-model="sForm.name" required placeholder="Balanceo de Llantas" /></div>
            <div class="form-group"><label>Código</label><input v-model="sForm.code" required placeholder="BALANC" /></div>
            <div class="form-group"><label>Categoría</label>
              <select v-model="sForm.category" required>
                <option value="Mantenimiento">Mantenimiento</option>
                <option value="Instalación">Instalación</option>
                <option value="Reparación">Reparación</option>
              </select>
            </div>
            <div class="form-group"><label>Precio (PEN)</label><input v-model="sForm.price_pen" type="number" step="0.01" required placeholder="40.00" /></div>
            <div class="form-group"><label>Duración (min)</label><input v-model="sForm.duration_minutes" type="number" placeholder="30" /></div>
            <div class="form-group span-2"><label>Descripción</label><input v-model="sForm.description" placeholder="Descripción del servicio" /></div>
          </div>
          <div class="form-error" v-if="sFormError">{{ sFormError }}</div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="showServiceModal = false">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="sSaving">{{ sSaving ? 'Guardando...' : (editingService ? 'Actualizar' : 'Crear') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const activeTab = ref('records')
const records = ref([])
const recLoading = ref(true)
const recSearch = ref('')
const recPeriod = ref('this_month')
const recPage = ref(1)
const recLastPage = ref(1)
const catalog = ref([])
const catLoading = ref(true)
const catSearch = ref('')
const catCategory = ref('')
const showServiceModal = ref(false)
const editingService = ref(false)
const sSaving = ref(false)
const sFormError = ref('')
const sForm = ref({})

const fmt = n => parseFloat(n || 0).toFixed(2)
const formatDate = d => d ? new Date(d).toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : ''
const catClass = c => ({ 'Mantenimiento': 'cat-mant', 'Instalación': 'cat-inst', 'Reparación': 'cat-rep' }[c] || 'cat-mant')

let recTimer = null
const debouncedRecLoad = () => { clearTimeout(recTimer); recTimer = setTimeout(() => loadRecords(), 350) }
let catTimer = null
const debouncedCatLoad = () => { clearTimeout(catTimer); catTimer = setTimeout(() => loadCatalog(), 350) }

const loadRecords = async (page = 1) => {
  recLoading.value = true; recPage.value = page
  const params = new URLSearchParams({ page, per_page: 10,
    ...(recSearch.value && { search: recSearch.value }),
    ...(recPeriod.value && { period: recPeriod.value }),
  })
  try {
    const res = await fetch(`/api/service-records?${params}`)
    const json = await res.json()
    records.value = json.data?.data || []
    recLastPage.value = json.data?.last_page || 1
  } finally { recLoading.value = false }
}

const loadCatalog = async () => {
  catLoading.value = true
  const params = new URLSearchParams({
    ...(catSearch.value && { search: catSearch.value }),
    ...(catCategory.value && { category: catCategory.value }),
  })
  try {
    const res = await fetch(`/api/services?${params}`)
    const json = await res.json()
    catalog.value = json.data || []
  } finally { catLoading.value = false }
}

const openServiceModal = (s = null) => {
  editingService.value = !!s; sFormError.value = ''
  sForm.value = s ? { ...s } : { name: '', code: '', category: 'Mantenimiento', price_pen: '', duration_minutes: '', description: '' }
  showServiceModal.value = true
}

const saveService = async () => {
  sSaving.value = true; sFormError.value = ''
  try {
    const url = editingService.value ? `/api/services/${sForm.value.id}` : '/api/services'
    const method = editingService.value ? 'PUT' : 'POST'
    const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(sForm.value) })
    const json = await res.json()
    if (json.success) { showServiceModal.value = false; loadCatalog() }
    else { sFormError.value = Object.values(json.errors || {}).flat().join(' · ') }
  } finally { sSaving.value = false }
}

const deleteService = async s => {
  if (!confirm(`¿Eliminar "${s.name}"?`)) return
  await fetch(`/api/services/${s.id}`, { method: 'DELETE' })
  loadCatalog()
}

onMounted(() => { loadRecords(); loadCatalog() })
</script>

<style scoped>
.page { padding: 32px; max-width: 1200px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; letter-spacing: -0.02em; }
.page-sub { font-size: 12px; color: var(--text3); margin-top: 4px; }
.tabs { display: flex; gap: 4px; background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 4px; width: fit-content; margin-bottom: 20px; }
.tab { padding: 7px 20px; border: none; border-radius: 7px; background: transparent; color: var(--text2); font-family: 'DM Mono', monospace; font-size: 13px; cursor: pointer; transition: all 0.15s; }
.tab.active { background: var(--accent); color: white; }
.toolbar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
.search-wrap { flex: 1; }
.search-input { width: 100%; padding: 9px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'DM Mono', monospace; font-size: 13px; }
.search-input:focus { outline: none; border-color: var(--accent); }
.select { padding: 8px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'DM Mono', monospace; font-size: 12px; cursor: pointer; }
.table-wrap { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th { padding: 12px 16px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text3); border-bottom: 1px solid var(--border); font-weight: 500; }
.table td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: rgba(255,255,255,0.02); }
.sale-number { font-family: 'DM Mono', monospace; font-size: 12px; color: var(--accent); }
.prod-name { font-weight: 500; }
.prod-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.mono { font-family: 'DM Mono', monospace; }
.service-name { font-weight: 500; }
.pay-badge { padding: 3px 8px; background: var(--bg3); border: 1px solid var(--border); border-radius: 4px; font-size: 11px; color: var(--text2); }
.status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; }
.status-ok { background: rgba(34,197,94,0.1); color: var(--green); }
.status-cancelled { background: rgba(239,68,68,0.1); color: var(--red); }
.empty-row { text-align: center; color: var(--text3); padding: 40px !important; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 16px; }
.page-btn { padding: 6px 14px; background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); cursor: pointer; font-size: 16px; }
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.page-info { font-size: 12px; color: var(--text3); }
.services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.service-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 18px; transition: border-color 0.15s; }
.service-card:hover { border-color: var(--accent); }
.service-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.cat-badge { padding: 3px 10px; border-radius: 20px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; }
.cat-mant { background: rgba(59,130,246,0.1); color: var(--blue); }
.cat-inst { background: rgba(34,197,94,0.1); color: var(--green); }
.cat-rep  { background: rgba(249,115,22,0.1); color: var(--accent); }
.service-card-name { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; margin-bottom: 2px; }
.service-card-code { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text3); margin-bottom: 8px; }
.service-card-desc { font-size: 12px; color: var(--text2); margin-bottom: 12px; line-height: 1.4; }
.service-card-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 12px; }
.service-price { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: var(--accent); }
.service-duration { font-size: 11px; color: var(--text3); }
.empty-grid { grid-column: span 3; text-align: center; color: var(--text3); padding: 40px; }
.action-btns { display: flex; gap: 4px; }
.btn-icon { width: 26px; height: 26px; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: var(--text2); cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.btn-icon:hover { background: var(--bg3); color: var(--text); }
.btn-icon-del:hover { border-color: var(--red); color: var(--red); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 200; backdrop-filter: blur(4px); }
.modal { background: var(--bg2); border: 1px solid var(--border); border-radius: 16px; width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); }
.modal-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; }
.modal-close { width: 28px; height: 28px; background: var(--bg3); border: none; border-radius: 6px; color: var(--text2); cursor: pointer; font-size: 14px; }
.modal-form { padding: 24px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.span-2 { grid-column: span 2; }
.form-group label { font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; }
.form-group input, .form-group select { padding: 9px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'DM Mono', monospace; font-size: 13px; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
.form-error { margin-top: 12px; color: var(--red); font-size: 12px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; }
.btn-primary { padding: 9px 20px; background: var(--accent); border: none; border-radius: 8px; color: white; font-family: 'DM Mono', monospace; font-size: 13px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { padding: 9px 20px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text2); font-family: 'DM Mono', monospace; font-size: 13px; cursor: pointer; }
.loading-state { display: flex; align-items: center; gap: 10px; padding: 40px; color: var(--text3); }
.spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.accent { color: var(--accent) !important; }
.green { color: var(--green) !important; }
.red { color: var(--red) !important; }
</style>