<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h1 class="page-title">Productos</h1>
        <p class="page-sub">{{ total }} productos en inventario</p>
      </div>
      <button class="btn-primary" @click="openModal()">+ Nuevo Producto</button>
    </header>
    <div class="toolbar">
      <div class="search-wrap">
        <input v-model="search" @input="debouncedLoad" placeholder="Buscar por nombre, marca, talla..." class="search-input" />
      </div>
      <div class="filters">
        <select v-model="filterCategory" @change="loadProducts" class="select">
          <option value="">Todas las categorías</option>
          <option value="Automóvil">Automóvil</option>
          <option value="Camioneta">Camioneta</option>
          <option value="Camión">Camión</option>
        </select>
        <button :class="['btn-filter', { active: filterLowStock }]" @click="toggleLowStock">⚠ Bajo stock</button>
      </div>
    </div>
    <div class="table-wrap">
      <div v-if="loading" class="loading-state"><div class="spinner"></div> Cargando...</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Producto</th><th>Talla</th><th>Costo USD</th><th>Precio USD</th><th>Precio PEN</th><th>Margen</th><th>Stock</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id" :class="{ 'low-stock-row': isLowStock(p) }">
            <td><div class="prod-name">{{ p.name }}</div><div class="prod-sub">{{ p.brand }} · {{ p.model }}</div></td>
            <td><span class="badge-size">{{ p.size }}</span></td>
            <td class="mono">\$ {{ fmt(p.cost_usd) }}</td>
            <td class="mono">\$ {{ fmt(p.price_usd) }}</td>
            <td class="mono accent">S/ {{ fmtPen(p.price_usd) }}</td>
            <td><span :class="['badge-margin', marginClass(p)]">{{ margin(p) }}%</span></td>
            <td>
              <span :class="['stock-badge', isLowStock(p) ? 'stock-low' : 'stock-ok']">{{ p.stock }}</span>
              <span class="stock-min">/ {{ p.min_stock }}</span>
            </td>
            <td>
              <div class="action-btns">
                <button class="btn-icon" @click="openModal(p)">✎</button>
                <button class="btn-icon btn-icon-stock" @click="openStock(p)">⊕</button>
                <button class="btn-icon btn-icon-del" @click="deleteProduct(p)">✕</button>
              </div>
            </td>
          </tr>
          <tr v-if="products.length === 0"><td colspan="8" class="empty-row">No se encontraron productos</td></tr>
        </tbody>
      </table>
    </div>
    <div class="pagination" v-if="lastPage > 1">
      <button :disabled="currentPage === 1" @click="goPage(currentPage - 1)" class="page-btn">‹</button>
      <span class="page-info">Página {{ currentPage }} de {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="goPage(currentPage + 1)" class="page-btn">›</button>
    </div>
    <!-- Modal Crear/Editar -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h2 class="modal-title">{{ editing ? 'Editar Producto' : 'Nuevo Producto' }}</h2>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>
        <form @submit.prevent="saveProduct" class="modal-form">
          <div class="form-grid">
            <div class="form-group span-2"><label>Nombre</label><input v-model="form.name" required placeholder="Ej: Michelin Primacy 4" /></div>
            <div class="form-group"><label>Marca</label><input v-model="form.brand" required placeholder="Michelin" /></div>
            <div class="form-group"><label>Modelo</label><input v-model="form.model" required placeholder="Primacy 4" /></div>
            <div class="form-group"><label>Talla</label><input v-model="form.size" required placeholder="205/55R16" /></div>
            <div class="form-group"><label>Categoría</label>
              <select v-model="form.category" required>
                <option value="Automóvil">Automóvil</option><option value="Camioneta">Camioneta</option>
                <option value="Camión">Camión</option><option value="Moto">Moto</option>
              </select>
            </div>
            <div class="form-group"><label>Costo USD</label><input v-model="form.cost_usd" type="number" step="0.01" required placeholder="85.00" /></div>
            <div class="form-group"><label>Precio USD</label><input v-model="form.price_usd" type="number" step="0.01" required placeholder="120.00" /></div>
            <div class="form-group"><label>Stock actual</label><input v-model="form.stock" type="number" required placeholder="20" /></div>
            <div class="form-group"><label>Stock mínimo</label><input v-model="form.min_stock" type="number" placeholder="5" /></div>
            <div class="form-group span-2"><label>Proveedor</label><input v-model="form.supplier" placeholder="Distribuidora Lima" /></div>
          </div>
          <div class="form-preview" v-if="form.price_usd && exchangeRate">
            Precio PEN: <strong class="accent">S/ {{ (form.price_usd * exchangeRate).toFixed(2) }}</strong>
            &nbsp;·&nbsp; Margen: <strong>{{ margin(form) }}%</strong>
          </div>
          <div class="form-error" v-if="formError">{{ formError }}</div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="closeModal">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}</button>
          </div>
        </form>
      </div>
    </div>
    <!-- Modal Stock -->
    <div v-if="showStock" class="modal-overlay" @click.self="showStock = false">
      <div class="modal modal-sm">
        <div class="modal-header"><h2 class="modal-title">Ajustar Stock</h2><button class="modal-close" @click="showStock = false">✕</button></div>
        <div class="modal-form">
          <p class="stock-product-name">{{ stockProduct?.name }}</p>
          <p class="stock-current">Stock actual: <strong>{{ stockProduct?.stock }}</strong></p>
          <div class="form-group"><label>Operación</label>
            <select v-model="stockOp" class="select">
              <option value="add">Agregar</option><option value="subtract">Restar</option><option value="set">Establecer</option>
            </select>
          </div>
          <div class="form-group"><label>Cantidad</label><input v-model="stockQty" type="number" min="0" /></div>
          <div class="modal-footer">
            <button class="btn-secondary" @click="showStock = false">Cancelar</button>
            <button class="btn-primary" @click="saveStock">Actualizar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const products = ref([])
const loading = ref(true)
const search = ref('')
const filterCategory = ref('')
const filterLowStock = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const exchangeRate = ref(3.70)
const showModal = ref(false)
const editing = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({})
const showStock = ref(false)
const stockProduct = ref(null)
const stockOp = ref('add')
const stockQty = ref(1)
const fmt = n => parseFloat(n || 0).toFixed(2)
const fmtPen = n => (parseFloat(n || 0) * exchangeRate.value).toFixed(2)
const isLowStock = p => parseInt(p.stock) <= parseInt(p.min_stock)
const margin = p => {
  const cost = parseFloat(p.cost_usd || 0)
  const price = parseFloat(p.price_usd || 0)
  if (!cost) return 0
  return Math.round(((price - cost) / cost) * 100)
}
const marginClass = p => {
  const m = margin(p)
  if (m >= 30) return 'margin-high'
  if (m >= 10) return 'margin-mid'
  return 'margin-low'
}
let debounceTimer = null
const debouncedLoad = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => loadProducts(), 350)
}
const toggleLowStock = () => { filterLowStock.value = !filterLowStock.value; loadProducts() }
const loadProducts = async (page = 1) => {
  loading.value = true
  currentPage.value = page
  const params = new URLSearchParams({ page, per_page: 10,
    ...(search.value && { search: search.value }),
    ...(filterCategory.value && { category: filterCategory.value }),
    ...(filterLowStock.value && { low_stock: 1 }),
  })
  try {
    const res = await fetch(`/api/products?${params}`)
    const json = await res.json()
    products.value = json.data?.data || []
    lastPage.value = json.data?.last_page || 1
    total.value = json.data?.total || 0
    if (json.exchange_rate) exchangeRate.value = parseFloat(json.exchange_rate.sell_rate)
  } finally { loading.value = false }
}
const goPage = p => loadProducts(p)
const openModal = (p = null) => {
  editing.value = !!p
  formError.value = ''
  form.value = p ? { ...p } : { name:'', brand:'', model:'', size:'', category:'Automóvil', cost_usd:'', price_usd:'', stock:0, min_stock:5, supplier:'' }
  showModal.value = true
}
const closeModal = () => { showModal.value = false }
const saveProduct = async () => {
  saving.value = true; formError.value = ''
  try {
    const url = editing.value ? `/api/products/${form.value.id}` : '/api/products'
    const method = editing.value ? 'PUT' : 'POST'
    const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form.value) })
    const json = await res.json()
    if (json.success) { closeModal(); loadProducts(currentPage.value) }
    else { formError.value = Object.values(json.errors || {}).flat().join(' · ') }
  } finally { saving.value = false }
}
const deleteProduct = async p => {
  if (!confirm(`¿Eliminar "${p.name}"?`)) return
  await fetch(`/api/products/${p.id}`, { method: 'DELETE' })
  loadProducts(currentPage.value)
}
const openStock = p => { stockProduct.value = p; stockOp.value = 'add'; stockQty.value = 1; showStock.value = true }
const saveStock = async () => {
  await fetch(`/api/products/${stockProduct.value.id}/update-stock`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ stock: parseInt(stockQty.value), operation: stockOp.value })
  })
  showStock.value = false; loadProducts(currentPage.value)
}
onMounted(loadProducts)
</script>
<style scoped>
.page { padding: 32px; max-width: 1200px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; letter-spacing: -0.02em; }
.page-sub { font-size: 12px; color: var(--text3); margin-top: 4px; }
.toolbar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-input { width: 100%; padding: 9px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'DM Mono', monospace; font-size: 13px; }
.search-input:focus { outline: none; border-color: var(--accent); }
.filters { display: flex; gap: 8px; }
.select { padding: 8px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'DM Mono', monospace; font-size: 12px; cursor: pointer; }
.btn-filter { padding: 8px 14px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text2); font-family: 'DM Mono', monospace; font-size: 12px; cursor: pointer; }
.btn-filter.active { border-color: var(--accent); color: var(--accent); background: rgba(249,115,22,0.08); }
.table-wrap { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th { padding: 12px 16px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text3); border-bottom: 1px solid var(--border); font-weight: 500; }
.table td { padding: 14px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: rgba(255,255,255,0.02); }
.low-stock-row td { background: rgba(239,68,68,0.03); }
.prod-name { font-weight: 500; }
.prod-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.mono { font-family: 'DM Mono', monospace; }
.badge-size { padding: 3px 8px; background: var(--bg3); border: 1px solid var(--border); border-radius: 4px; font-size: 11px; font-family: 'DM Mono', monospace; color: var(--text2); }
.badge-margin { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-family: 'DM Mono', monospace; }
.margin-high { background: rgba(34,197,94,0.1); color: var(--green); }
.margin-mid { background: rgba(249,115,22,0.1); color: var(--accent); }
.margin-low { background: rgba(239,68,68,0.1); color: var(--red); }
.stock-badge { font-family: 'DM Mono', monospace; font-weight: 600; padding: 2px 8px; border-radius: 4px; }
.stock-ok { background: rgba(34,197,94,0.1); color: var(--green); }
.stock-low { background: rgba(239,68,68,0.1); color: var(--red); }
.stock-min { font-size: 11px; color: var(--text3); margin-left: 4px; }
.action-btns { display: flex; gap: 4px; }
.btn-icon { width: 28px; height: 28px; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: var(--text2); cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.btn-icon:hover { background: var(--bg3); color: var(--text); }
.btn-icon-stock:hover { border-color: var(--green); color: var(--green); }
.btn-icon-del:hover { border-color: var(--red); color: var(--red); }
.empty-row { text-align: center; color: var(--text3); padding: 40px !important; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 16px; }
.page-btn { padding: 6px 14px; background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); cursor: pointer; font-size: 16px; }
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.page-info { font-size: 12px; color: var(--text3); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 200; backdrop-filter: blur(4px); }
.modal { background: var(--bg2); border: 1px solid var(--border); border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }
.modal-sm { max-width: 360px; }
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
.form-preview { margin-top: 16px; padding: 12px; background: var(--bg3); border-radius: 8px; font-size: 12px; color: var(--text2); }
.form-error { margin-top: 12px; color: var(--red); font-size: 12px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; }
.stock-product-name { font-family: 'Syne', sans-serif; font-weight: 700; margin-bottom: 4px; }
.stock-current { font-size: 13px; color: var(--text2); margin-bottom: 16px; }
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