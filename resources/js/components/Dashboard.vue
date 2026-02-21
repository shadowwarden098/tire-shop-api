<template>
  <div class="dashboard">
    <!-- Header -->
    <header class="dash-header">
      <div>
        <h1 class="page-title">Menu</h1>
        <p class="page-sub">{{ today }}</p>
      </div>
      <div class="period-tabs">
        <button v-for="p in periods" :key="p.value"
          :class="['period-btn', { active: period === p.value }]"
          @click="changePeriod(p.value)">
          {{ p.label }}
        </button>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Cargando métricas...</span>
    </div>

    <template v-else-if="data">
      <!-- KPI Row -->
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

        <div class="kpi-card">
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

      <!-- Row 2: Inventory + Profitability -->
      <div class="row-2">
        <!-- Inventario -->
        <div class="card inventory-card">
          <div class="card-header">
            <span class="card-title">Inventario</span>
            <span class="badge" :class="data.inventory.low_stock_items > 0 ? 'badge-warn' : 'badge-ok'">
              {{ data.inventory.low_stock_items > 0 ? `⚠ ${data.inventory.low_stock_items} bajo stock` : '✓ Stock OK' }}
            </span>
          </div>
          <div class="inv-stats">
            <div class="inv-stat">
              <div class="inv-label">Valor USD</div>
              <div class="inv-value">$ {{ fmt(data.inventory.value_usd) }}</div>
            </div>
            <div class="inv-divider"></div>
            <div class="inv-stat">
              <div class="inv-label">Valor PEN</div>
              <div class="inv-value accent">S/ {{ fmt(data.inventory.value_pen) }}</div>
            </div>
            <div class="inv-divider"></div>
            <div class="inv-stat">
              <div class="inv-label">Productos</div>
              <div class="inv-value">{{ data.inventory.total_products }}</div>
            </div>
          </div>
          <div class="inv-bar-wrap">
            <div class="inv-bar-label">
              <span>Tipo de cambio</span>
              <span class="accent">S/ {{ data.exchange_rate.sell }}</span>
            </div>
            <div class="inv-bar">
              <div class="inv-bar-fill" :style="{ width: profitMarginWidth }"></div>
            </div>
            <div class="inv-bar-label">
              <span>Margen de ganancia bruta</span>
              <span>{{ data.profitability.profit_margin_percentage }}%</span>
            </div>
          </div>
        </div>

        <!-- Resumen financiero -->
        <div class="card finance-card">
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
      </div>

      <!-- Gastos por categoría -->
      <div class="card" v-if="hasExpenses">
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

    <div v-else class="empty-state">
      No se pudieron cargar los datos. Verifica que el servidor esté activo.
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const loading = ref(true)
const data = ref(null)
const period = ref('today')

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
  if (!data.value) return ''
  return data.value.growth.revenue_growth_percentage >= 0 ? 'green' : 'red'
})

const growthIcon = computed(() => {
  if (!data.value) return ''
  return data.value.growth.revenue_growth_percentage >= 0 ? '▲' : '▼'
})

const expenseCategoryCount = computed(() => {
  if (!data.value) return 0
  return Object.keys(data.value.expenses.by_category || {}).length
})

const hasExpenses = computed(() => {
  return data.value && Object.keys(data.value.expenses.by_category || {}).length > 0
})

const profitMarginWidth = computed(() => {
  if (!data.value) return '0%'
  const pct = Math.min(Math.max(data.value.profitability.profit_margin_percentage, 0), 100)
  return pct + '%'
})

const maxExpense = computed(() => {
  if (!data.value) return 1
  const vals = Object.values(data.value.expenses.by_category || {}).map(e => e.total)
  return Math.max(...vals, 1)
})

const expenseBarWidth = (total) => {
  return Math.round((total / maxExpense.value) * 100) + '%'
}

const categoryLabels = {
  compra_inventario: 'Compra Inventario',
  operativo: 'Gastos Operativos',
  salarios: 'Salarios',
  servicios: 'Servicios',
  impuestos: 'Impuestos',
  alquiler: 'Alquiler',
  marketing: 'Marketing',
  mantenimiento: 'Mantenimiento',
  transporte: 'Transporte',
  otros: 'Otros',
}

const categoryLabel = (cat) => categoryLabels[cat] || cat

const loadDashboard = async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/reports/dashboard?period=${period.value}`)
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

onMounted(loadDashboard)
</script>

<style scoped>
.dashboard {
  padding: 32px;
  max-width: 1200px;
}

/* Header */
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

.period-btn:hover { color: var(--text); }
.period-btn.active { background: var(--accent); color: white; font-weight: 500; }

/* KPI Grid */
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
}

.kpi-card:hover { border-color: var(--accent); }
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

/* Row 2 */
.row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

/* Cards */
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

.badge {
  font-size: 11px;
  padding: 3px 10px;
  border-radius: 20px;
}
.badge-ok  { background: rgba(34,197,94,0.1);  color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
.badge-warn{ background: rgba(249,115,22,0.1); color: var(--accent); border: 1px solid rgba(249,115,22,0.2); }

/* Inventory */
.inv-stats {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: 20px;
}

.inv-stat { flex: 1; text-align: center; }
.inv-divider { width: 1px; height: 40px; background: var(--border); }
.inv-label { font-size: 11px; color: var(--text3); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.08em; }
.inv-value { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; }

.inv-bar-wrap { margin-top: 8px; }
.inv-bar-label { display: flex; justify-content: space-between; font-size: 11px; color: var(--text3); margin-bottom: 6px; }
.inv-bar { height: 6px; background: var(--bg3); border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
.inv-bar-fill { height: 100%; background: var(--accent); border-radius: 3px; transition: width 0.6s ease; }

/* Finance */
.finance-rows { display: flex; flex-direction: column; gap: 0; }
.finance-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
  font-size: 13px;
}
.finance-row:last-child { border-bottom: none; }
.finance-row.separator { margin-top: 4px; }
.finance-row.total-row { margin-top: 4px; }
.finance-row.total-row .finance-label { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14px; }
.finance-row.total-row .finance-val { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 16px; }
.finance-label { color: var(--text2); }
.finance-val { font-weight: 500; }

/* Expense Bars */
.expense-bars { display: flex; flex-direction: column; gap: 12px; }
.expense-bar-row { display: grid; grid-template-columns: 140px 1fr 100px; align-items: center; gap: 12px; }
.expense-cat { font-size: 12px; color: var(--text2); }
.expense-bar-wrap { height: 6px; background: var(--bg3); border-radius: 3px; overflow: hidden; }
.expense-bar-fill { height: 100%; background: var(--red); border-radius: 3px; transition: width 0.6s ease; opacity: 0.7; }
.expense-amount { font-size: 12px; color: var(--text2); text-align: right; }

/* Colors */
.green  { color: var(--green) !important; }
.red    { color: var(--red) !important; }
.accent { color: var(--accent) !important; }
.blue   { color: var(--blue) !important; }

/* Loading */
.loading-state {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 60px;
  color: var(--text3);
  font-size: 13px;
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
</style>
