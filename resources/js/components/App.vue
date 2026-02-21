<template>
  <div id="app">
    <!-- Sin layout para login -->
    <router-view v-if="$route.meta.public" />

    <!-- Layout principal -->
    <div v-else class="layout">
      <!-- Sidebar -->
      <aside class="sidebar">
        <div class="sidebar-brand">
          <div class="brand-icon">◈</div>
          <div>
            <div class="brand-name">Tienda de<br>neumáticos</div>
            <div class="brand-sub">SISTEMA DE GESTIÓN</div>
          </div>
        </div>

        <nav class="sidebar-nav">
          <router-link to="/"          class="nav-item" active-class="active" exact>
            <span class="nav-icon">◎</span><span>Panel</span>
          </router-link>
          <router-link to="/products"  class="nav-item" active-class="active">
            <span class="nav-icon">◉</span><span>Productos</span>
          </router-link>
          <router-link to="/customers" class="nav-item" active-class="active">
            <span class="nav-icon">◎</span><span>Clientes</span>
          </router-link>
          <router-link to="/sales"     class="nav-item" active-class="active">
            <span class="nav-icon">◆</span><span>Ventas</span>
          </router-link>
          <router-link to="/services"  class="nav-item" active-class="active">
            <span class="nav-icon">◈</span><span>Servicios</span>
          </router-link>

          <!-- Solo visible para admin -->
          <template v-if="isAdmin">
            <div class="nav-separator"></div>
            <div class="nav-section-label">ADMINISTRACIÓN</div>
            <router-link to="/reports" class="nav-item" active-class="active">
              <span class="nav-icon">◉</span><span>Reportes</span>
            </router-link>
          </template>
        </nav>

        <div class="sidebar-footer">
          <!-- Tipo de cambio -->
          <div class="exchange-rate" v-if="exchangeRate">
            <div class="er-label">TIPO DE CAMBIO</div>
            <div class="er-value">S/ {{ exchangeRate.sell_rate }}</div>
            <div class="er-date">{{ exchangeRate.date }}</div>
          </div>

          <!-- Usuario admin logueado -->
          <div class="user-bar" v-if="isAdmin">
            <div class="user-info">
              <div class="user-avatar">{{ currentUser.name[0] }}</div>
              <div class="user-detail">
                <div class="user-name">{{ currentUser.name }}</div>
                <div class="user-role">Administrador</div>
              </div>
            </div>
            <button class="btn-logout" @click="logout" title="Cerrar sesión">⏻</button>
          </div>

          <!-- Empleado sin login -->
          <div class="employee-bar" v-else>
            <div class="employee-icon">👤</div>
            <div class="employee-detail">
              <div class="employee-label">Modo Empleado</div>
              <button class="btn-admin-login" @click="$router.push('/login')">Admin →</button>
            </div>
          </div>
        </div>
      </aside>

      <main class="main">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router      = useRouter()
const exchangeRate = ref(null)

const token     = ref(localStorage.getItem('auth_token'))
const userJson  = ref(localStorage.getItem('auth_user'))

const currentUser = computed(() => {
  if (userJson.value) {
    try { return JSON.parse(userJson.value) } catch { return null }
  }
  return null
})

const isAdmin = computed(() => currentUser.value && currentUser.value.role === 'admin')

const logout = async () => {
  if (token.value) {
    try {
      await fetch('/api/auth/logout', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token.value}` },
      })
    } catch {}
  }
  localStorage.removeItem('auth_token')
  localStorage.removeItem('auth_user')
  localStorage.setItem('auth_role', 'employee')
  token.value   = null
  userJson.value = null
  router.push('/')
}

const loadExchangeRate = async () => {
  try {
    const res  = await fetch('/api/exchange-rate/current')
    const json = await res.json()
    if (json.success) exchangeRate.value = json.data
  } catch {}
}

onMounted(loadExchangeRate)
</script>

<style>
:root {
  --bg: #0a0c10;
  --bg2: #111318;
  --bg3: #1a1d24;
  --border: rgba(255,255,255,0.07);
  --text: #f0f2f5;
  --text2: #8b9099;
  --text3: #555b66;
  --accent: #f97316;
  --green: #22c55e;
  --red: #ef4444;
  --blue: #3b82f6;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: 'DM Mono', monospace; }
</style>

<style scoped>
#app { min-height: 100vh; }

.layout { display: flex; min-height: 100vh; }

/* ─── SIDEBAR ─── */
.sidebar {
  width: 220px; min-width: 220px;
  background: var(--bg2);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  position: sticky; top: 0; height: 100vh; overflow-y: auto;
}

.sidebar-brand {
  display: flex; align-items: center; gap: 12px;
  padding: 24px 20px 20px;
  border-bottom: 1px solid var(--border);
}
.brand-icon { font-size: 24px; color: var(--accent); }
.brand-name { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 800; line-height: 1.2; }
.brand-sub  { font-size: 8px; color: var(--text3); letter-spacing: 0.15em; margin-top: 4px; }

.sidebar-nav { padding: 16px 12px; display: flex; flex-direction: column; gap: 2px; flex: 1; }

.nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 8px;
  color: var(--text2); text-decoration: none;
  font-size: 13px; transition: all 0.15s;
}
.nav-item:hover { background: var(--bg3); color: var(--text); }
.nav-item.active { background: rgba(249,115,22,0.12); color: var(--accent); }
.nav-icon { font-size: 14px; width: 18px; text-align: center; }

.nav-separator { height: 1px; background: var(--border); margin: 10px 0 6px; }
.nav-section-label { font-size: 9px; color: var(--text3); letter-spacing: 0.15em; padding: 0 12px 6px; text-transform: uppercase; }

/* ─── SIDEBAR FOOTER ─── */
.sidebar-footer { padding: 12px; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 10px; }

.exchange-rate {
  background: var(--bg3); border-radius: 10px; padding: 12px 14px;
}
.er-label { font-size: 9px; color: var(--text3); letter-spacing: 0.12em; margin-bottom: 4px; }
.er-value { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 800; color: var(--accent); }
.er-date  { font-size: 10px; color: var(--text3); margin-top: 2px; }

.user-bar {
  display: flex; align-items: center; justify-content: space-between;
  background: var(--bg3); border-radius: 10px; padding: 10px 12px;
}
.user-info { display: flex; align-items: center; gap: 10px; }
.user-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--accent); color: white;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-weight: 700; font-size: 13px;
}
.user-name { font-size: 12px; font-weight: 600; }
.user-role { font-size: 10px; color: var(--accent); }
.btn-logout {
  background: none; border: 1px solid var(--border); border-radius: 6px;
  color: var(--text3); cursor: pointer; padding: 4px 7px; font-size: 14px;
  transition: all 0.15s;
}
.btn-logout:hover { border-color: var(--red); color: var(--red); }

.employee-bar {
  display: flex; align-items: center; gap: 10px;
  background: var(--bg3); border-radius: 10px; padding: 10px 12px;
}
.employee-icon { font-size: 20px; opacity: 0.5; }
.employee-label { font-size: 11px; color: var(--text2); margin-bottom: 4px; }
.btn-admin-login {
  background: none; border: none; color: var(--accent);
  font-family: 'DM Mono', monospace; font-size: 11px; cursor: pointer; padding: 0;
}
.btn-admin-login:hover { text-decoration: underline; }

/* ─── MAIN ─── */
.main { flex: 1; overflow-y: auto; }
</style>
