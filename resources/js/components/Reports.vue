<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h1 class="page-title">Reportes</h1>
        <p class="page-sub">Análisis financiero e inventario</p>
      </div>
    </header>

    <div class="tabs">
      <button :class="['tab', { active: activeTab === 'financial' }]" @click="switchTab('financial')">Financiero</button>
      <button :class="['tab', { active: activeTab === 'inventory' }]" @click="switchTab('inventory')">Inventario</button>
    </div>

    <!-- FINANCIERO -->
    <div v-if="activeTab === 'financial'">
      <div class="filter-bar">
        <div class="form-group-inline">
          <label>Desde</label>
          <input v-model="dateFrom" type="date" class="date-input" />
        </div>
        <div class="form-group-inline">
          <label>Hasta</label>
          <input v-model="dateTo" type="date" class="date-input" />
        </div>
        <div class="quick-btns">
          <button @click="setQuick('this_week')" class="quick-btn">Esta semana</button>
          <button @click="setQuick('this_month')" class="quick-btn">Este mes</button>
          <button @click="setQuick('last_month')" class="quick-btn">Mes anterior</button>
          <button @click="setQuick('this_year')" class="quick-btn">Este año</button>
        </div>
        <button class="btn-primary" @click="loadFinancial">Generar</button>
      </div>

      <div v-if="finLoading" class="loading-state"><div class="spinner"></div> Calculando...</div>
      <div v-else-if="finError" class="loading-state"><span>⚠ {{ finError }}</span></div>
      <template v-else-if="fin">
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Ingresos Totales</div>
            <div class="kpi-val accent">S/ {{ fmt(fin.summary && fin.summary.total_revenue) }}</div>
            <div class="kpi-sub">Ventas + Servicios</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Costo de Ventas</div>
            <div class="kpi-val red">- S/ {{ fmt(fin.summary && fin.summary.total_cogs) }}</div>
            <div class="kpi-sub">Costo de productos vendidos</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Gastos Operativos</div>
            <div class="kpi-val red">- S/ {{ fmt(fin.summary && fin.summary.total_expenses) }}</div>
            <div class="kpi-sub">{{ fin.summary && fin.summary.expense_count }} registros</div>
          </div>
          <div class="kpi-card kpi-highlight">
            <div class="kpi-label">Ganancia Neta</div>
            <div :class="['kpi-val', netProfitPositive ? 'green' : 'red']">
              S/ {{ fmt(fin.summary && fin.summary.net_profit) }}
            </div>
            <div class="kpi-sub">Margen: {{ fin.summary && fin.summary.profit_margin }}%</div>
          </div>
        </div>

        <div class="two-col">
          <div class="report-card">
            <div class="report-card-title">Top Productos Vendidos</div>
            <table class="inner-table">
              <thead><tr><th>Producto</th><th>Cant.</th><th>Ingresos</th><th>Ganancia</th></tr></thead>
              <tbody>
                <tr v-for="p in fin.top_products" :key="p.product_id">
                  <td><div class="prod-name">{{ p.product_name }}</div><div class="prod-sub">{{ p.brand }}</div></td>
                  <td class="mono">{{ p.total_quantity }}</td>
                  <td class="mono accent">S/ {{ fmt(p.total_revenue) }}</td>
                  <td class="mono green">S/ {{ fmt(p.total_profit) }}</td>
                </tr>
                <tr v-if="!fin.top_products || !fin.top_products.length"><td colspan="4" class="empty-cell">Sin datos</td></tr>
              </tbody>
            </table>
          </div>

          <div class="report-card">
            <div class="report-card-title">Gastos por Categoría</div>
            <div v-for="e in fin.expenses_by_category" :key="e.category" class="expense-row">
              <div class="expense-info">
                <span class="expense-cat">{{ e.category }}</span>
                <span class="expense-count">{{ e.count }} registros</span>
              </div>
              <div class="expense-bar-wrap">
                <div class="expense-bar" :style="{ width: expPct(e.total_pen) + '%' }"></div>
              </div>
              <span class="expense-val">S/ {{ fmt(e.total_pen) }}</span>
            </div>
            <div v-if="!fin.expenses_by_category || !fin.expenses_by_category.length" class="empty-cell">Sin gastos en el período</div>
          </div>
        </div>

        <div class="report-card mt-16" v-if="fin.daily_sales && fin.daily_sales.length">
          <div class="report-card-title">Ventas por Día</div>
          <div class="chart-bars">
            <div v-for="d in fin.daily_sales" :key="d.date" class="chart-col">
              <div class="chart-bar-wrap">
                <div class="chart-bar" :style="{ height: barHeight(d.revenue) + '%' }" :title="'S/ ' + fmt(d.revenue)"></div>
              </div>
              <div class="chart-label">{{ shortDate(d.date) }}</div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- INVENTARIO -->
    <div v-if="activeTab === 'inventory'">
      <div v-if="invLoading" class="loading-state"><div class="spinner"></div> Calculando...</div>
    <div v-else-if="invError" class="loading-state"><span>⚠ {{ invError }}</span></div>
      <template v-else-if="inv">
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Productos</div>
            <div class="kpi-val">{{ inv.summary && inv.summary.total_products }}</div>
            <div class="kpi-sub">{{ inv.summary && inv.summary.total_stock }} unidades</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Valor USD</div>
            <div class="kpi-val">$ {{ fmt(inv.summary && inv.summary.total_value_usd) }}</div>
            <div class="kpi-sub">Al costo</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Valor PEN</div>
            <div class="kpi-val accent">S/ {{ fmt(inv.summary && inv.summary.total_value_pen) }}</div>
            <div class="kpi-sub">T.C. {{ inv.summary && inv.summary.exchange_rate }}</div>
          </div>
          <div :class="['kpi-card', inv.summary && inv.summary.low_stock_count > 0 ? 'kpi-warn' : 'kpi-ok']">
            <div class="kpi-label">Bajo Stock</div>
            <div :class="['kpi-val', inv.summary && inv.summary.low_stock_count > 0 ? 'red' : 'green']">
              {{ inv.summary && inv.summary.low_stock_count }}
            </div>
            <div class="kpi-sub">Productos críticos</div>
          </div>
        </div>

        <div class="report-card">
          <div class="report-card-title">Detalle de Inventario</div>
          <table class="inner-table">
            <thead>
              <tr><th>Producto</th><th>Talla</th><th>Stock</th><th>Costo USD</th><th>Valor USD</th><th>Valor PEN</th><th>Margen</th></tr>
            </thead>
            <tbody>
              <tr v-for="p in inv.products" :key="p.id" :class="{ 'row-warn': parseInt(p.stock) <= parseInt(p.min_stock) }">
                <td><div class="prod-name">{{ p.name }}</div><div class="prod-sub">{{ p.brand }}</div></td>
                <td><span class="badge-size">{{ p.size }}</span></td>
                <td>
                  <span :class="['stock-badge', parseInt(p.stock) <= parseInt(p.min_stock) ? 'stock-low' : 'stock-ok']">{{ p.stock }}</span>
                </td>
                <td class="mono">$ {{ fmt(p.cost_usd) }}</td>
                <td class="mono">$ {{ fmt(p.stock_value_usd) }}</td>
                <td class="mono accent">S/ {{ fmt(p.stock_value_pen) }}</td>
                <td>
                  <span :class="['badge-margin', p.profit_margin >= 30 ? 'margin-high' : p.profit_margin >= 10 ? 'margin-mid' : 'margin-low']">
                    {{ p.profit_margin }}%
                  </span>
                </td>
              </tr>
              <tr v-if="!inv.products || !inv.products.length"><td colspan="7" class="empty-cell">Sin productos</td></tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const activeTab = ref('financial')
const fin = ref(null)
const finLoading = ref(false)
const finError = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const inv = ref(null)
const invLoading = ref(false)
const invError = ref('')

const fmt = n => parseFloat(n || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const shortDate = d => { const dt = new Date(d); return `${dt.getDate()}/${dt.getMonth()+1}` }

// headers helper to include token when available (endpoints son admin only)
const authHeaders = () => {
  const h = { 'Accept': 'application/json' }
  const tok = localStorage.getItem('auth_token')
  if (tok) h.Authorization = `Bearer ${tok}`
  return h
}

const maxRevenue = computed(() => {
  if (!fin.value || !fin.value.daily_sales) return 1
  return Math.max(...fin.value.daily_sales.map(d => parseFloat(d.revenue)), 1)
})
const totalExpenses = computed(() => {
  if (!fin.value || !fin.value.summary) return 1
  return parseFloat(fin.value.summary.total_expenses) || 1
})
const netProfitPositive = computed(() => {
  if (!fin.value || !fin.value.summary) return true
  return parseFloat(fin.value.summary.net_profit) >= 0
})

const barHeight = val => Math.round((parseFloat(val) / maxRevenue.value) * 100)
const expPct = val => Math.round((parseFloat(val) / totalExpenses.value) * 100)

const switchTab = tab => {
  activeTab.value = tab
  if (tab === 'financial') loadFinancial()
  else loadInventory()
}

const setQuick = period => {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  const f = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`
  let from, to = f(now)
  if (period === 'this_week') { const d = new Date(now); d.setDate(d.getDate() - d.getDay()); from = f(d) }
  else if (period === 'this_month') { from = `${now.getFullYear()}-${pad(now.getMonth()+1)}-01` }
  else if (period === 'last_month') {
    const d = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    from = f(d)
    to = f(new Date(now.getFullYear(), now.getMonth(), 0))
  }
  else if (period === 'this_year') { from = `${now.getFullYear()}-01-01` }
  dateFrom.value = from; dateTo.value = to
  loadFinancial()
}

const loadFinancial = async () => {
  finLoading.value = true
  const params = new URLSearchParams({
    ...(dateFrom.value && { start_date: dateFrom.value }),
    ...(dateTo.value && { end_date: dateTo.value }),
  })
  try {
    const res = await fetch(`/api/reports/financial?${params}`, { headers: authHeaders() })
    const json = await res.json()
    if (!res.ok || !json.success) {
      finError.value = json.message || 'No se pudo cargar el reporte'
      fin.value = null
    } else {
      fin.value = json.data
      finError.value = ''
    }
  } catch(e) {
    finError.value = 'Error de red al obtener reporte'
    fin.value = null
  } finally { finLoading.value = false }
}

const loadInventory = async () => {
  invLoading.value = true
  try {
    const res = await fetch('/api/reports/inventory', { headers: authHeaders() })
    const json = await res.json()
    if (!res.ok || !json.success) {
      invError.value = json.message || 'No se pudo cargar inventario'
      inv.value = null
    } else {
      inv.value = json.data
      invError.value = ''
    }
  } catch(e) {
    invError.value = 'Error de red al obtener inventario'
    inv.value = null
  } finally { invLoading.value = false }
}

onMounted(() => {
  const route = useRoute()
  // si hay query, usarlas en lugar de la configuración rápida mensual
  if (route.query.date_from) dateFrom.value = route.query.date_from
  if (route.query.date_to) dateTo.value = route.query.date_to
  if (route.query.tab) activeTab.value = route.query.tab

  if (dateFrom.value && dateTo.value) {
    // montón de fechas especificadas previamente
  } else {
    setQuick('this_month')
  }

  // carga según pestaña
  if (activeTab.value === 'financial') loadFinancial()
  else loadInventory()
})
</script>

<style scoped>
.page { padding: 32px; max-width: 1200px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; letter-spacing: -0.02em; }
.page-sub { font-size: 12px; color: var(--text3); margin-top: 4px; }
.tabs { display: flex; gap: 4px; background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 4px; width: fit-content; margin-bottom: 20px; }
.tab { padding: 7px 24px; border: none; border-radius: 7px; background: transparent; color: var(--text2); font-family: 'DM Mono', monospace; font-size: 13px; cursor: pointer; transition: all 0.15s; }
.tab.active { background: var(--accent); color: white; }
.filter-bar { display: flex; gap: 12px; align-items: center; background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; flex-wrap: wrap; }
.form-group-inline { display: flex; align-items: center; gap: 8px; }
.form-group-inline label { font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; white-space: nowrap; }
.date-input { padding: 7px 10px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'DM Mono', monospace; font-size: 12px; }
.date-input:focus { outline: none; border-color: var(--accent); }
.quick-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.quick-btn { padding: 6px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; color: var(--text2); font-family: 'DM Mono', monospace; font-size: 11px; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.quick-btn:hover { border-color: var(--accent); color: var(--accent); }
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.kpi-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; }
.kpi-highlight { border-color: rgba(249,115,22,0.3); background: rgba(249,115,22,0.04); }
.kpi-warn { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.04); }
.kpi-ok { border-color: rgba(34,197,94,0.3); background: rgba(34,197,94,0.04); }
.kpi-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text3); margin-bottom: 6px; }
.kpi-val { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.kpi-sub { font-size: 11px; color: var(--text3); }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.report-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.mt-16 { margin-top: 16px; }
.report-card-title { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; margin-bottom: 14px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em; }
.inner-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.inner-table th { padding: 8px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text3); border-bottom: 1px solid var(--border); font-weight: 500; }
.inner-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.inner-table tr:last-child td { border-bottom: none; }
.inner-table tr:hover td { background: rgba(255,255,255,0.02); }
.row-warn td { background: rgba(239,68,68,0.03); }
.prod-name { font-weight: 500; }
.prod-sub { font-size: 10px; color: var(--text3); margin-top: 1px; }
.mono { font-family: 'DM Mono', monospace; }
.badge-size { padding: 2px 6px; background: var(--bg3); border: 1px solid var(--border); border-radius: 4px; font-size: 10px; color: var(--text3); }
.badge-margin { padding: 2px 7px; border-radius: 4px; font-size: 10px; font-family: 'DM Mono', monospace; }
.margin-high { background: rgba(34,197,94,0.1); color: var(--green); }
.margin-mid  { background: rgba(249,115,22,0.1); color: var(--accent); }
.margin-low  { background: rgba(239,68,68,0.1); color: var(--red); }
.stock-badge { font-family: 'DM Mono', monospace; font-weight: 600; padding: 2px 7px; border-radius: 4px; }
.stock-ok { background: rgba(34,197,94,0.1); color: var(--green); }
.stock-low { background: rgba(239,68,68,0.1); color: var(--red); }
.expense-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); }
.expense-row:last-child { border-bottom: none; }
.expense-info { min-width: 120px; }
.expense-cat { font-size: 12px; font-weight: 500; display: block; }
.expense-count { font-size: 10px; color: var(--text3); }
.expense-bar-wrap { flex: 1; height: 6px; background: var(--bg3); border-radius: 3px; overflow: hidden; }
.expense-bar { height: 100%; background: var(--accent); border-radius: 3px; transition: width 0.5s ease; }
.expense-val { font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text2); min-width: 80px; text-align: right; }
.chart-bars { display: flex; align-items: flex-end; gap: 6px; height: 120px; padding-top: 10px; }
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; }
.chart-bar-wrap { flex: 1; width: 100%; display: flex; align-items: flex-end; }
.chart-bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; opacity: 0.8; transition: height 0.5s ease; min-height: 4px; }
.chart-bar:hover { opacity: 1; }
.chart-label { font-size: 9px; color: var(--text3); font-family: 'DM Mono', monospace; }
.empty-cell { text-align: center; color: var(--text3); padding: 20px !important; font-size: 12px; }
.loading-state { display: flex; align-items: center; gap: 10px; padding: 40px; color: var(--text3); }
.spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-primary { padding: 9px 20px; background: var(--accent); border: none; border-radius: 8px; color: white; font-family: 'DM Mono', monospace; font-size: 13px; cursor: pointer; }
.accent { color: var(--accent) !important; }
.green  { color: var(--green) !important; }
.red    { color: var(--red) !important; }
</style>