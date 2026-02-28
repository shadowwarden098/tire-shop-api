<template>
  <div class="dashboard">
    <!-- Header -->
    <header class="dash-header">
      <div>
        <h1 class="page-title">Menu</h1>
        <p class="page-sub">{{ today }}</p>
      </div>
      <div class="period-tabs" v-if="isAdmin">
        <button v-for="p in periods" :key="p.value"
          :class="['period-btn', { active: period === p.value }]"
          @click="changePeriod(p.value)">
          {{ p.label }}
        </button>
      </div>
    </header>

    <!-- Loading (skeleton) -->
    <div v-if="loading" class="loading-state">
      <div class="skeleton-grid">
        <div class="skeleton-card" v-for="n in 6" :key="n"></div>
      </div>
      <span>Cargando métricas...</span>
    </div>

    <template v-else-if="data">

      <!-- ✅ SOLO ADMIN: KPIs financieros -->
      <template v-if="isAdmin">
        <!-- Atajos Rapidos Admin -->
        <div class="admin-shortcuts">
          <button class="shortcut-btn" @click="handleShortcut('reporte')"><span class="shortcut-icon">📊</span><span class="shortcut-label">Reporte Hoy</span></button>
          <button class="shortcut-btn" @click="handleShortcut('stock')"><span class="shortcut-icon">⚠️</span><span class="shortcut-label">Stock Bajo</span></button>
          <button class="shortcut-btn" @click="handleShortcut('equipo')"><span class="shortcut-icon">👥</span><span class="shortcut-label">Equipo</span></button>
          <button class="shortcut-btn" @click="handleShortcut('gastos')"><span class="shortcut-icon">💸</span><span class="shortcut-label">Gastos</span></button>
        </div>

        <div class="kpi-grid">
          <div class="kpi-card accent">
            <div class="kpi-label">Ingresos Totales</div>
            <div class="kpi-value">S/ {{ fmt(data.total_revenue) }}</div>
            <div class="kpi-detail">
              <span :class="growthClass">{{ growthIcon }} {{ Math.abs(data.growth.revenue_growth_percentage) }}%</span>
              vs período anterior
            </div>
          </div>

          <div class="kpi-card">
            <div class="kpi-label">Ganancia Neta</div>
            <div class="kpi-value" :class="data.profitability.net_profit >= 0 ? 'green' : 'red'">
              S/ {{ fmt(data.profitability.net_profit) }}
            </div>
            <div class="kpi-detail">Margen: {{ data.profitability.profit_margin_percentage }}%</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-label">Ventas</div>
            <div class="kpi-value">{{ data.sales.count }}</div>
            <div class="kpi-detail">S/ {{ fmt(data.sales.revenue) }} en ingresos</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-label">Servicios</div>
            <div class="kpi-value">{{ data.services.count }}</div>
            <div class="kpi-detail">S/ {{ fmt(data.services.revenue) }} en ingresos</div>
          </div>

          <div class="kpi-card" v-if="isAdmin">
            <div class="kpi-label">Gastos</div>
            <div class="kpi-value red">S/ {{ fmt(data.expenses.total) }}</div>
            <div class="kpi-detail">{{ expenseCategoryCount }} categorías</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-label">Ticket Promedio</div>
            <div class="kpi-value">S/ {{ fmt(data.sales.average_ticket) }}</div>
            <div class="kpi-detail">Por venta</div>
          </div>
        </div>
    <!-- KPI extra: estrategia (SOLO ADMIN) -->
    <div class="kpi-grid" v-if="isAdmin">
      <div class="kpi-card">
        <div class="kpi-label">CLV (Valor por Cliente)</div>
        <div class="kpi-value">S/ {{ fmt(data.clv || 0) }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Churn Rate (3m)</div>
        <div class="kpi-value">{{ data.churn_rate || 0 }}%</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Proyección de flujo</div>
        <div class="kpi-value">S/ {{ fmt(data.flow_projection || 0) }}</div>
      </div>
    </div>

    <!-- gráfico de composición (SOLO ADMIN) -->
    <div class="card" v-if="isAdmin && data.revenue_composition">
      <div class="card-header">
        <span class="card-title">Composición de ingresos</span>
      </div>
      <div class="chart-bars">
        <div v-for="(val,label) in data.revenue_composition" :key="label" class="chart-col">
          <div class="chart-bar-wrap">
            <div class="chart-bar" :style="{ height: compositionBarHeight(val) }" :title="fmt(val)"></div>
          </div>
          <div class="chart-label">{{ label }}</div>
        </div>
      </div>
    </div>
          <!-- Resumen financiero (SOLO ADMIN) -->
          <div class="card finance-card" v-if="isAdmin">
            <div class="card-header">
              <span class="card-title">Resumen Financiero</span>
            </div>
            <div class="finance-rows">
              <div class="finance-row">
                <span class="finance-label">Ingresos por ventas</span>
                <span class="finance-val green">+ S/ {{ fmt(data.sales.revenue) }}</span>
              </div>
              <div class="finance-row">
                <span class="finance-label">Ingresos por servicios</span>
                <span class="finance-val green">+ S/ {{ fmt(data.services.revenue) }}</span>
              </div>
              <div class="finance-row separator">
                <span class="finance-label">Total ingresos</span>
                <span class="finance-val accent">S/ {{ fmt(data.total_revenue) }}</span>
              </div>
              <div class="finance-row">
                <span class="finance-label">Gastos</span>
                <span class="finance-val red">- S/ {{ fmt(data.expenses.total) }}</span>
              </div>
              <div class="finance-row">
                <span class="finance-label">Costo de ventas</span>
                <span class="finance-val red">- S/ {{ fmt(data.sales.revenue - data.sales.profit) }}</span>
              </div>
              <div class="finance-row separator total-row">
                <span class="finance-label">Ganancia neta</span>
                <span class="finance-val" :class="data.profitability.net_profit >= 0 ? 'green' : 'red'">
                  S/ {{ fmt(data.profitability.net_profit) }}
                </span>
              </div>
            </div>
          </div>

        <!-- Gastos por categoría (SOLO ADMIN) -->
        <div class="card" v-if="hasExpenses && isAdmin">
          <div class="card-header">
            <span class="card-title">Gastos por Categoría</span>
            <span class="card-sub">S/ {{ fmt(data.expenses.total) }} total</span>
          </div>
          <div class="expense-bars">
            <div v-for="(exp, cat) in data.expenses.by_category" :key="cat" class="expense-bar-row">
              <div class="expense-cat">{{ categoryLabel(cat) }}</div>
              <div class="expense-bar-wrap">
                <div class="expense-bar-fill" :style="{ width: expenseBarWidth(exp.total) }"></div>
              </div>
              <div class="expense-amount">S/ {{ fmt(exp.total) }}</div>
            </div>
          </div>
        </div>
      </template>

      <!-- ✅ EMPLEADO: vista de alto rendimiento -->
      <template v-else>
        <!-- Meta Mensual Pro con indicador de tendencia -->
        <div class="kpi-card meta-pro">
          <div class="meta-content">
            <div class="meta-info">
              <span class="kpi-label">Rendimiento Mensual</span>
              <div class="kpi-value meta-value">{{ progresoMeta }}%</div>
              <p class="meta-status">
                <span v-if="metaFaltante > 0">Faltan S/ {{ fmt(metaFaltante) }} para el bono</span>
                <span v-else class="success">🎆 ¡Meta alcanzada!</span>
              </p>
            </div>
            <div class="progress-circle-container">
              <div class="glow-bar-wrap">
                <div class="trend-marker" :style="{ left: diaDelMes + '%' }" title="Deberías estar aquí hoy"></div>
                <div class="glow-bar-fill" :style="{ width: progresoMeta + '%' }"></div>
              </div>
              <div class="bar-hint">{{ diaDelMes }}% de progreso esperado hoy</div>
            </div>
          </div>
        </div>

        <!-- Botones de Acción Rápida -->
        <div class="action-buttons">
          <button class="action-btn primary">
            <span class="btn-icon">+</span>
            <span class="btn-text">Registrar Venta</span>
          </button>
          <button class="action-btn secondary">
            <span class="btn-icon">📦</span>
            <span class="btn-text">Consultar Stock</span>
          </button>
          <button class="action-btn tertiary">
            <span class="btn-icon">📋</span>
            <span class="btn-text">Ver Catálogo</span>
          </button>
        </div>

        <!-- Grid de 3 columnas -->
        <div class="action-grid">
          <div class="card employee-card suggestions-card">
            <div class="card-header">
              <span class="card-title"><span class="icon-bulb">💡</span> Sugerencias</span>
            </div>
            <ul class="suggestions-list">
              <li v-for="(s,i) in employeeSuggestions" :key="i" v-html="s"></li>
            </ul>
          </div>

          <div class="card employee-card news-card">
            <div class="card-header">
              <span class="card-title"><span class="icon-news pulse-ping">📢</span> Noticias</span>
            </div>
            <div class="news-item" v-for="(n,i) in employeeNews" :key="i">
              <p class="news-date pulse-neon">{{ n.date }}</p>
              <p class="news-text">{{ n.text }}</p>
            </div>
          </div>

          <div class="card employee-card top-selling-card">
            <div class="card-header">
              <span class="card-title"><span class="icon-fire">🔥</span> Más Vendidos</span>
            </div>
            <div class="top-selling-list">
              <div class="top-item">
                <span class="item-name">Llanta Aro 15</span>
                <span class="item-badge">+24%</span>
              </div>
              <div class="top-item">
                <span class="item-name">Alineación</span>
                <span class="item-badge">+18%</span>
              </div>
              <div class="top-item">
                <span class="item-name">Filtros Premium</span>
                <span class="item-badge">+12%</span>
              </div>
            </div>
          </div>
        </div>
      </template>

    </template>

    <div v-else class="empty-state">
      No se pudieron cargar los datos. Verifica que el servidor esté activo.
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const loading = ref(true)
const data    = ref(null)
const period  = ref('today')

// Detectar rol
const isAdmin = computed(() => {
  const user = localStorage.getItem('auth_user')
  if (!user) return false
  try { return JSON.parse(user).role === 'admin' } catch { return false }
})

const periods = [
  { value: 'today',      label: 'Hoy' },
  { value: 'this_week',  label: 'Semana' },
  { value: 'this_month', label: 'Mes' },
  { value: 'this_year',  label: 'Año' },
]

const today = new Date().toLocaleDateString('es-PE', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
})

const fmt = (n) => parseFloat(n || 0).toLocaleString('es-PE', {
  minimumFractionDigits: 2, maximumFractionDigits: 2
})

const growthClass = computed(() => {
  if (!data.value || !data.value.growth) return ''
  return data.value.growth.revenue_growth_percentage >= 0 ? 'green' : 'red'
})

const growthIcon = computed(() => {
  if (!data.value || !data.value.growth) return ''
  return data.value.growth.revenue_growth_percentage >= 0 ? '▲' : '▼'
})

const expenseCategoryCount = computed(() => {
  if (!data.value || !data.value.expenses) return 0
  return Object.keys(data.value.expenses.by_category || {}).length
})

const hasExpenses = computed(() => {
  return data.value && data.value.expenses && Object.keys(data.value.expenses.by_category || {}).length > 0
})

const profitMarginWidth = computed(() => {
  if (!data.value || !data.value.profitability) return '0%'
  const pct = Math.min(Math.max(data.value.profitability.profit_margin_percentage, 0), 100)
  return pct + '%'
})

const maxExpense = computed(() => {
  if (!data.value || !data.value.expenses) return 1
  const vals = Object.values(data.value.expenses.by_category || {}).map(e => e.total)
  return Math.max(...vals, 1)
})

const expenseBarWidth = (total) => {
  return Math.round((total / maxExpense.value) * 100) + '%'
}

const categoryLabels = {
  compra_inventario: 'Compra Inventario',
  operativo:         'Gastos Operativos',
  salarios:          'Salarios',
  servicios:         'Servicios',
  impuestos:         'Impuestos',
  alquiler:          'Alquiler',
  marketing:         'Marketing',
  mantenimiento:     'Mantenimiento',
  transporte:        'Transporte',
  otros:             'Otros',
}

const categoryLabel = (cat) => categoryLabels[cat] || cat

const loadDashboard = async () => {
  loading.value = true
  try {
    // separa el endpoint según el rol para que los empleados no reciban
    // datos financieros. aún mantenemos /reports/dashboard por compatibilidad
    const endpoint = isAdmin.value
      ? '/api/reports/admin-dashboard'
      : '/api/reports/staff-dashboard'

    // adjuntar token si existe
    const headers = { 'Accept': 'application/json' }
    const token = localStorage.getItem('auth_token')
    if (token) headers.Authorization = `Bearer ${token}`

    const res  = await fetch(`${endpoint}?period=${period.value}`, { headers })
    const json = await res.json()
    if (json.success) data.value = json.data
  } catch (e) {
    data.value = null
  } finally {
    loading.value = false
  }
}

const changePeriod = (p) => {
  period.value = p
  loadDashboard()
}

// admin shortcuts
const handleShortcut = (type) => {
  switch(type) {
    case 'reporte':
      // abrir pestaña de reportes con el período de hoy seleccionado
      const today = new Date().toISOString().slice(0,10)
      window.location.href = `/reports?tab=financial&date_from=${today}&date_to=${today}`
      break;
    case 'stock':
      window.location.href = '/inventory'
      break;
    case 'equipo':
      window.location.href = '/team'
      break;
    case 'gastos':
      window.location.href = '/expenses'
      break;
    default:
      console.log('Shortcut', type);
  }
}

// datos para la vista de empleados
const metaMensual = ref(50000) // S/ 50,000 es la meta base
const employeeSuggestions = ref([
  'Si el cliente lleva **Mantenimiento**, ofrece **Filtros Premium**.',
  'Promoción de la semana: 2x1 en servicios de alineación.',
])
const employeeNews = ref([
  { date: 'Hoy', text: 'Llegó el nuevo stock de repuestos originales.' }
])

const ventasActuales = computed(() => data.value?.sales.revenue || 0)
const progresoMeta = computed(() => {
  return Math.min(Math.round((ventasActuales.value / metaMensual.value) * 100), 100)
})
const metaFaltante = computed(() => Math.max(metaMensual.value - ventasActuales.value, 0))

// Día del mes para el indicador de tendencia
const diaDelMes = computed(() => {
  const hoy = new Date()
  const diasEnMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).getDate()
  const diaActual = hoy.getDate()
  return Math.round((diaActual / diasEnMes) * 100)
})

// altura de las barras de composición (para admin)
const compositionBarHeight = (val) => {
  if (!data.value || !data.value.revenue_composition) return '0%'
  const vals = Object.values(data.value.revenue_composition)
  const max = Math.max(...vals, 1)
  return Math.round((val / max) * 100) + '%'
}

onMounted(loadDashboard)
</script>

<style scoped>
.dashboard {
  padding: 32px;
  max-width: 1200px;
}

.dash-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 32px;
}

.page-title {
  font-family: 'Syne', sans-serif;
  font-size: 28px;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.02em;
}

.page-sub {
  font-size: 12px;
  color: var(--text3);
  margin-top: 4px;
  text-transform: capitalize;
}

.period-tabs {
  display: flex;
  gap: 4px;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 4px;
}

.period-btn {
  padding: 6px 14px;
  border: none;
  border-radius: 7px;
  background: transparent;
  color: var(--text2);
  font-family: 'DM Mono', monospace;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}

.period-btn:hover  { color: var(--text); }
.period-btn.active { background: var(--accent); color: white; font-weight: 500; }

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.kpi-card {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  transition: border-color 0.2s;
  position: relative;
  overflow: hidden;
}
.kpi-card::after {
  content: "";
  position: absolute;
  top: -50%; left: -50%;
  width: 200%; height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
  pointer-events: none;
}

.kpi-card:hover  { border-color: var(--accent); }
.kpi-card.accent { border-color: rgba(249,115,22,0.4); background: rgba(249,115,22,0.05); }

.kpi-label {
  font-size: 11px;
  color: var(--text3);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 8px;
}

.kpi-value {
  font-family: 'Syne', sans-serif;
  font-size: 26px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 6px;
}

.kpi-detail {
  font-size: 11px;
  color: var(--text3);
  display: flex;
  gap: 6px;
  align-items: center;
}

.row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.card {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.card-title {
  font-family: 'Syne', sans-serif;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.card-sub { font-size: 12px; color: var(--text3); }

.badge { font-size: 11px; padding: 3px 10px; border-radius: 20px; }
.badge-ok  { background: rgba(34,197,94,0.1);  color: var(--green);  border: 1px solid rgba(34,197,94,0.2); }
.badge-warn{ background: rgba(249,115,22,0.1); color: var(--accent); border: 1px solid rgba(249,115,22,0.2); }

.inv-stats { display: flex; align-items: center; margin-bottom: 20px; }
.inv-stat  { flex: 1; text-align: center; }
.inv-divider { width: 1px; height: 40px; background: var(--border); }
.inv-label { font-size: 11px; color: var(--text3); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.08em; }
.inv-value { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; }

.inv-bar-wrap  { margin-top: 8px; }
.inv-bar-label { display: flex; justify-content: space-between; font-size: 11px; color: var(--text3); margin-bottom: 6px; }
.inv-bar       { height: 6px; background: var(--bg3); border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
.inv-bar-fill  { height: 100%; background: var(--accent); border-radius: 3px; transition: width 0.6s ease; }

.finance-rows  { display: flex; flex-direction: column; }
.finance-row   {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 13px;
}
.finance-row:last-child { border-bottom: none; }
.finance-row.separator  { margin-top: 4px; }
.finance-row.total-row  { margin-top: 4px; }
.finance-row.total-row .finance-label { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14px; }
.finance-row.total-row .finance-val   { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 16px; }
.finance-label { color: var(--text2); }
.finance-val   { font-weight: 500; }

.expense-bars    { display: flex; flex-direction: column; gap: 12px; }
.expense-bar-row { display: grid; grid-template-columns: 140px 1fr 100px; align-items: center; gap: 12px; }
.expense-cat     { font-size: 12px; color: var(--text2); }
.expense-bar-wrap{ height: 6px; background: var(--bg3); border-radius: 3px; overflow: hidden; }
.expense-bar-fill{ height: 100%; background: var(--red); border-radius: 3px; transition: width 0.6s ease; opacity: 0.7; }
.expense-amount  { font-size: 12px; color: var(--text2); text-align: right; }

/* Aviso empleado */
.employee-notice {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  margin-top: 8px;
}
.notice-icon  { font-size: 28px; }
.notice-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14px; margin-bottom: 4px; }
.notice-sub   { font-size: 12px; color: var(--text3); }

.green  { color: var(--green)  !important; }
.red    { color: var(--red)    !important; }
.accent { color: var(--accent) !important; }
.blue   { color: var(--blue)   !important; }

.loading-state {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 60px; color: var(--text3); font-size: 13px;
}
.skeleton-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; width: 100%; max-width: 600px; margin-bottom: 12px;
}
.skeleton-card {
  height: 80px; background: var(--bg3); border-radius: 12px; animation: pulse 1.2s infinite;
}
@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.4; }
  100% { opacity: 1; }
}

.spinner {
  width: 20px; height: 20px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.empty-state { padding: 60px; color: var(--text3); font-size: 13px; }

/* mini barra para metas */
.meta-bar-mini {
  height: 4px; background: var(--bg3); border-radius: 2px; margin-top: 10px;
}
.meta-bar-mini .fill {
  height: 100%; background: var(--accent); border-radius: 2px;
}

/* Botones de Acción Rápida */
.action-buttons {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 24px;
  margin-top: 16px;
}

.action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 12px;
  border: 1px solid rgba(249, 115, 22, 0.2);
  background: rgba(20, 20, 20, 0.6);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-family: 'Syne', sans-serif;
  font-weight: 600;
  font-size: 12px;
}

.action-btn:hover {
  border-color: var(--accent);
  background: rgba(249, 115, 22, 0.1);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(249, 115, 22, 0.2);
}

.btn-icon {
  font-size: 20px;
  display: block;
}

.btn-text {
  color: var(--text2);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.action-btn:hover .btn-text {
  color: var(--text);
}

/* Grid de 3 columnas para empleado */
.action-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

@media (max-width: 1024px) {
  .action-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .action-grid {
    grid-template-columns: 1fr;
  }
  .action-buttons {
    grid-template-columns: repeat(3, 1fr);
  }
}

.meta-pro {
  background: linear-gradient(135deg, var(--bg2) 0%, #1a1a2e 100%);
  border: 1px solid rgba(249, 115, 22, 0.3);
  box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.8);
  grid-column: span 3;
  margin-bottom: 12px;
}

.meta-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 30px;
}

.meta-info {
  flex: 0 0 40%;
}

.meta-value {
  font-size: 48px !important;
  background: linear-gradient(135deg, var(--accent), #fb923c);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.meta-status {
  margin-top: 8px;
  font-size: 13px;
  color: var(--text2);
}

.meta-status .success {
  color: var(--green);
  font-weight: 600;
}

.progress-circle-container {
  flex: 1;
}

.glow-bar-wrap {
  width: 100%;
  height: 12px;
  background: #0f172a;
  border-radius: 20px;
  border: 1px solid var(--border);
  overflow: hidden;
  box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.5);
  position: relative;
}

.trend-marker {
  position: absolute;
  width: 2px;
  height: 14px;
  background: rgba(255, 255, 255, 0.5);
  top: -1px;
  z-index: 2;
  transition: left 0.3s ease;
}

.bar-hint {
  font-size: 10px;
  color: var(--text3);
  margin-top: 6px;
  text-align: right;
  font-style: italic;
}

.glow-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--accent) 0%, #fb923c 100%);
  box-shadow: 0 0 15px var(--accent), 0 0 30px rgba(249, 115, 22, 0.4);
  transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

/* tarjetas específicas de empleado mejoradas */
.employee-card { 
  background: rgba(20, 20, 20, 0.6);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 12px; 
  padding: 20px;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
}

.employee-card:hover {
  border-color: rgba(249, 115, 22, 0.3);
  transform: translateY(-2px);
  background: rgba(20, 20, 20, 0.8);
}

.suggestions-card, .news-card {
  position: relative;
}

.suggestions-card::before, .news-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, var(--accent), transparent);
  border-radius: 12px 12px 0 0;
}

.icon-bulb, .icon-news {
  display: inline-block;
  margin-right: 6px;
  animation: float 3s ease-in-out infinite;
}

.icon-news {
  animation: pulse-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

.icon-fire {
  display: inline-block;
  margin-right: 6px;
  animation: float-bounce 3s ease-in-out infinite;
}

.news-date.pulse-neon {
  animation: neon-pulse 1.5s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}

@keyframes float-bounce {
  0%, 100% { transform: scale(1) translateY(0); }
  50% { transform: scale(1.05) translateY(-3px); }
}

@keyframes pulse-ping {
  75%, 100% { 
    transform: scale(1.2);
    opacity: 0;
  }
}

@keyframes neon-pulse {
  0%, 100% {
    color: var(--accent);
    text-shadow: 0 0 8px var(--accent);
  }
  50% {
    color: #fb923c;
    text-shadow: 0 0 12px #fb923c;
  }
}

.suggestions-list { 
  list-style: none; 
  margin: 0; 
  padding: 0; 
  font-size: 13px; 
}

.suggestions-list li { 
  list-style: none;
  padding: 10px;
  border-left: 2px solid var(--accent);
  background: rgba(249, 115, 22, 0.05);
  margin-bottom: 8px;
  border-radius: 0 8px 8px 0;
  transition: all 0.2s ease;
}

.suggestions-list li:hover {
  background: rgba(249, 115, 22, 0.1);
  padding-left: 12px;
}

.news-item { 
  margin-bottom: 12px;
  padding: 10px;
  background: rgba(59, 130, 246, 0.05);
  border-radius: 8px;
}

.news-date { 
  font-weight: 700; 
  margin-bottom: 4px;
  font-size: 11px;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.news-text { 
  font-size: 13px;
  color: var(--text2);
  line-height: 1.4;
}

/* Tarjeta de Más Vendidos */
.top-selling-card {
  display: flex;
  flex-direction: column;
}

.top-selling-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.top-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  border-left: 3px solid var(--green);
  background: rgba(34, 197, 94, 0.05);
  border-radius: 0 8px 8px 0;
  transition: all 0.2s ease;
}

.top-item:hover {
  background: rgba(34, 197, 94, 0.1);
  padding-left: 12px;
}

.item-name {
  font-size: 12px;
  color: var(--text2);
  font-weight: 500;
}

.item-badge {
  font-size: 11px;
  font-weight: 700;
  color: var(--green);
  background: rgba(34, 197, 94, 0.2);
  padding: 2px 6px;
  border-radius: 12px;
}

/* Admin Shortcuts */
.admin-shortcuts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.shortcut-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px;
  background: linear-gradient(135deg, rgba(249, 115, 22, 0.08), rgba(249, 115, 22, 0.04));
  border: 1px solid rgba(249, 115, 22, 0.2);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
}

.shortcut-btn:hover {
  background: linear-gradient(135deg, rgba(249, 115, 22, 0.12), rgba(249, 115, 22, 0.08));
  border-color: rgba(249, 115, 22, 0.4);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
}

.shortcut-icon {
  font-size: 20px;
  display: block;
}

.shortcut-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text2);
  text-align: center;
}

/* barras usadas en varios dashboards */
.chart-bars { display: flex; align-items: flex-end; gap: 6px; height: 120px; padding-top: 10px; }
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; }
.chart-bar-wrap { flex: 1; width: 100%; display: flex; align-items: flex-end; }
.chart-bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; opacity: 0.8; transition: height 0.5s ease; min-height: 4px; }
.chart-bar:hover { opacity: 1; }
.chart-label { font-size: 9px; color: var(--text3); font-family: 'DM Mono', monospace; }
</style>