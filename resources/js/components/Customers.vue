<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h1 class="page-title">Clientes</h1>
        <p class="page-sub">{{ total }} clientes registrados</p>
      </div>
      <button class="btn-primary" @click="openModal()">+ Nuevo Cliente</button>
    </header>
    <div class="toolbar">
      <div class="search-wrap">
        <input v-model="search" @input="debouncedLoad" placeholder="Buscar por nombre, DNI, teléfono..." class="search-input" />
      </div>
      <select v-model="filterType" @change="loadCustomers" class="select">
        <option value="">Todos los tipos</option>
        <option value="individual">Persona Natural</option>
        <option value="company">Empresa</option>
      </select>
    </div>
    <div class="table-wrap">
      <div v-if="loading" class="loading-state"><div class="spinner"></div> Cargando...</div>
      <table v-else class="table">
        <thead>
          <tr><th>Cliente</th><th>Documento</th><th>Teléfono</th><th>Ciudad</th><th>Tipo</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-for="c in customers" :key="c.id">
            <td>
              <div class="prod-name">{{ c.name }}</div>
              <div class="prod-sub" v-if="c.email">{{ c.email }}</div>
            </td>
            <td><span class="badge-size">{{ c.document_type }}</span> {{ c.document_number }}</td>
            <td class="mono">{{ c.phone }}</td>
            <td>{{ c.city }}<span v-if="c.district"> · {{ c.district }}</span></td>
            <td><span :class="['type-badge', c.customer_type === 'company' ? 'type-company' : 'type-individual']">{{ c.customer_type === 'company' ? 'Empresa' : 'Natural' }}</span></td>
            <td>
              <div class="action-btns">
                <button class="btn-icon" @click="openModal(c)">✎</button>
                <button class="btn-icon btn-icon-del" @click="deleteCustomer(c)">✕</button>
              </div>
            </td>
          </tr>
          <tr v-if="customers.length === 0"><td colspan="6" class="empty-row">No se encontraron clientes</td></tr>
        </tbody>
      </table>
    </div>
    <div class="pagination" v-if="lastPage > 1">
      <button :disabled="currentPage === 1" @click="goPage(currentPage - 1)" class="page-btn">‹</button>
      <span class="page-info">Página {{ currentPage }} de {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="goPage(currentPage + 1)" class="page-btn">›</button>
    </div>
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h2 class="modal-title">{{ editing ? "Editar Cliente" : "Nuevo Cliente" }}</h2>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>
        <form @submit.prevent="saveCustomer" class="modal-form">
          <div class="form-grid">
            <div class="form-group span-2"><label>Nombre completo</label><input v-model="form.name" required placeholder="Juan Pérez García" /></div>
            <div class="form-group"><label>Tipo de documento</label>
              <select v-model="form.document_type" required>
                <option value="DNI">DNI</option><option value="RUC">RUC</option><option value="CE">CE</option>
              </select>
            </div>
            <div class="form-group"><label>Número de documento</label><input v-model="form.document_number" required placeholder="12345678" /></div>
            <div class="form-group"><label>Teléfono</label><input v-model="form.phone" required placeholder="987654321" /></div>
            <div class="form-group"><label>Email</label><input v-model="form.email" type="email" placeholder="juan@email.com" /></div>
            <div class="form-group"><label>Ciudad</label><input v-model="form.city" placeholder="Lima" /></div>
            <div class="form-group"><label>Distrito</label><input v-model="form.district" placeholder="San Isidro" /></div>
            <div class="form-group span-2"><label>Dirección</label><input v-model="form.address" placeholder="Av. Principal 123" /></div>
            <div class="form-group"><label>Tipo de cliente</label>
              <select v-model="form.customer_type" required>
                <option value="individual">Persona Natural</option><option value="company">Empresa</option>
              </select>
            </div>
          </div>
          <div class="form-error" v-if="formError">{{ formError }}</div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="closeModal">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? "Guardando..." : (editing ? "Actualizar" : "Crear") }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from "vue"
const customers = ref([])
const loading = ref(true)
const search = ref("")
const filterType = ref("")
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const showModal = ref(false)
const editing = ref(false)
const saving = ref(false)
const formError = ref("")
const form = ref({})
let debounceTimer = null
const debouncedLoad = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadCustomers(), 350) }
const loadCustomers = async (page = 1) => {
  loading.value = true; currentPage.value = page
  const params = new URLSearchParams({ page, per_page: 10,
    ...(search.value && { search: search.value }),
    ...(filterType.value && { customer_type: filterType.value }),
  })
  try {
    const res = await fetch(`/api/customers?${params}`)
    const json = await res.json()
    customers.value = json.data?.data || []
    lastPage.value = json.data?.last_page || 1
    total.value = json.data?.total || 0
  } finally { loading.value = false }
}
const goPage = p => loadCustomers(p)
const openModal = (c = null) => {
  editing.value = !!c; formError.value = ""
  form.value = c ? { ...c } : { name:"", document_type:"DNI", document_number:"", phone:"", email:"", city:"Lima", district:"", address:"", customer_type:"individual" }
  showModal.value = true
}
const closeModal = () => { showModal.value = false }
const saveCustomer = async () => {
  saving.value = true; formError.value = ""
  try {
    const url = editing.value ? `/api/customers/${form.value.id}` : "/api/customers"
    const method = editing.value ? "PUT" : "POST"
    const res = await fetch(url, { method, headers: { "Content-Type": "application/json" }, body: JSON.stringify(form.value) })
    const json = await res.json()
    if (json.success) { closeModal(); loadCustomers(currentPage.value) }
    else { formError.value = Object.values(json.errors || {}).flat().join(" · ") }
  } finally { saving.value = false }
}
const deleteCustomer = async c => {
  if (!confirm(`¿Eliminar a "${c.name}"?`)) return
  await fetch(`/api/customers/${c.id}`, { method: "DELETE" })
  loadCustomers(currentPage.value)
}
onMounted(loadCustomers)
</script>
<style scoped>
.page { padding: 32px; max-width: 1200px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title { font-family: "Syne", sans-serif; font-size: 28px; font-weight: 800; letter-spacing: -0.02em; }
.page-sub { font-size: 12px; color: var(--text3); margin-top: 4px; }
.toolbar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
.search-wrap { flex: 1; }
.search-input { width: 100%; padding: 9px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: "DM Mono", monospace; font-size: 13px; }
.search-input:focus { outline: none; border-color: var(--accent); }
.select { padding: 8px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: "DM Mono", monospace; font-size: 12px; cursor: pointer; }
.table-wrap { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th { padding: 12px 16px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text3); border-bottom: 1px solid var(--border); font-weight: 500; }
.table td { padding: 14px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: rgba(255,255,255,0.02); }
.prod-name { font-weight: 500; }
.prod-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.mono { font-family: "DM Mono", monospace; }
.badge-size { padding: 2px 6px; background: var(--bg3); border: 1px solid var(--border); border-radius: 4px; font-size: 10px; color: var(--text3); }
.type-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; }
.type-individual { background: rgba(59,130,246,0.1); color: var(--blue); }
.type-company { background: rgba(249,115,22,0.1); color: var(--accent); }
.action-btns { display: flex; gap: 4px; }
.btn-icon { width: 28px; height: 28px; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: var(--text2); cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.btn-icon:hover { background: var(--bg3); color: var(--text); }
.btn-icon-del:hover { border-color: var(--red); color: var(--red); }
.empty-row { text-align: center; color: var(--text3); padding: 40px !important; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 16px; }
.page-btn { padding: 6px 14px; background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); cursor: pointer; font-size: 16px; }
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.page-info { font-size: 12px; color: var(--text3); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 200; backdrop-filter: blur(4px); }
.modal { background: var(--bg2); border: 1px solid var(--border); border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); }
.modal-title { font-family: "Syne", sans-serif; font-size: 18px; font-weight: 700; }
.modal-close { width: 28px; height: 28px; background: var(--bg3); border: none; border-radius: 6px; color: var(--text2); cursor: pointer; font-size: 14px; }
.modal-form { padding: 24px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.span-2 { grid-column: span 2; }
.form-group label { font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; }
.form-group input, .form-group select { padding: 9px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: "DM Mono", monospace; font-size: 13px; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
.form-error { margin-top: 12px; color: var(--red); font-size: 12px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; }
.btn-primary { padding: 9px 20px; background: var(--accent); border: none; border-radius: 8px; color: white; font-family: "DM Mono", monospace; font-size: 13px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { padding: 9px 20px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text2); font-family: "DM Mono", monospace; font-size: 13px; cursor: pointer; }
.loading-state { display: flex; align-items: center; gap: 10px; padding: 40px; color: var(--text3); }
.spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>