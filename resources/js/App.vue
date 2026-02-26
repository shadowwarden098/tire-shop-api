<template>
  <div class="app-shell">

    <!-- Sin layout para login -->
    <router-view v-if="$route.meta && $route.meta.isLogin" />

    <!-- Layout principal -->
    <template v-else>
      <aside class="sidebar">
        <div class="sidebar-brand">
          <span class="brand-icon">⬡</span>
          <div>
            <div class="brand-name">Importaciones Adan</div>
            <div class="brand-sub">Sistema de Gestión</div>
          </div>
        </div>

        <nav class="sidebar-nav">
          <router-link to="/dashboard" class="nav-item" active-class="active">
            <span class="nav-icon">◈</span>
            <span>Menu</span>
          </router-link>

          <router-link to="/products" class="nav-item" active-class="active">
            <span class="nav-icon">◉</span>
            <span>Productos</span>
          </router-link>

          <router-link to="/customers" class="nav-item" active-class="active">
            <span class="nav-icon">◎</span>
            <span>Clientes</span>
          </router-link>

          <router-link to="/sales" class="nav-item" active-class="active">
            <span class="nav-icon">◆</span>
            <span>Ventas</span>
          </router-link>

          <router-link to="/services" class="nav-item" active-class="active">
            <span class="nav-icon">◈</span>
            <span>Servicios</span>
          </router-link>

          <template v-if="isAdmin">
            <div class="nav-separator"></div>
            <div class="nav-section-label">Administración</div>
            <router-link to="/reports" class="nav-item" active-class="active">
              <span class="nav-icon">◉</span>
              <span>Reportes</span>
            </router-link>
          </template>
        </nav>

        <div class="sidebar-footer">
          <div class="exchange-rate" v-if="exchangeRate">
            <div class="er-label">Tipo de Cambio</div>
            <div class="er-value">1 USD = S/ {{ exchangeRate.sell_rate }} PEN</div>
            <div class="er-source">Actualizado • {{ formatExchangeDate(exchangeRate.date) }}</div>
          </div>

          <div class="user-bar" v-if="isAdmin">
            <div class="user-info">
              <div class="user-avatar">{{ currentUser.name[0].toUpperCase() }}</div>
              <div>
                <div class="user-name">{{ currentUser.name }}</div>
                <div class="user-role">Administrador</div>
              </div>
            </div>
            <button class="btn-logout" @click="logout" title="Cerrar sesión">⏻</button>
          </div>

          <div class="employee-bar" v-else>
            <span class="employee-icon">👤</span>
            <div class="employee-detail">
              <div class="employee-label">Modo Empleado</div>
              <button class="btn-admin-login" @click="$router.push('/')">
                Ingresar como Admin →
              </button>
            </div>
          </div>
        </div>
      </aside>

      <main class="main-content">
        <router-view />
      </main>

      <!-- Asistente IA - Solo para admins -->
      <AiAssistant v-if="isAdmin" />
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AiAssistant from './components/AiAssistant.vue'

const router = useRouter()
const route  = useRoute()

const exchangeRate = ref(null)
const token    = ref(localStorage.getItem('auth_token'))
const userJson = ref(localStorage.getItem('auth_user'))

// Re-leer localStorage cada vez que cambia la ruta
watch(route, () => {
  token.value    = localStorage.getItem('auth_token')
  userJson.value = localStorage.getItem('auth_user')
})

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
  token.value    = null
  userJson.value = null
  router.push('/')
}

const formatExchangeDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString('es-PE', {
    day: '2-digit', month: 'long', year: 'numeric'
  })
}

onMounted(async () => {
  try {
    const res  = await fetch('/api/exchange-rate/current')
    const json = await res.json()
    if (json.success) {
      exchangeRate.value = {
        sell_rate: new Intl.NumberFormat('es-PE', {
          minimumFractionDigits: 2, maximumFractionDigits: 2
        }).format(json.data.sell_rate),
        date: json.data.date
      }
    }
  } catch (e) {
    console.error('Error cargando tipo de cambio')
  }
})
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #0a0c10;
  --bg2:       #0f1218;
  --bg3:       #161b24;
  --border:    #1e2633;
  --accent:    #f97316;
  --green:     #22c55e;
  --red:       #ef4444;
  --blue:      #3b82f6;
  --text:      #e2e8f0;
  --text2:     #94a3b8;
  --text3:     #475569;
  --sidebar-w: 220px;
}

html, body {
  height: 100%;
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Mono', monospace;
}

.app-shell {
  display: flex;
  min-height: 100vh;
}
</style>

<style scoped>
.sidebar {
  width: var(--sidebar-w);
  background: var(--bg2);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px 20px;
  border-bottom: 1px solid var(--border);
}
.brand-icon { font-size: 28px; color: var(--accent); }
.brand-name { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 16px; }
.brand-sub  { font-size: 10px; color: var(--text3); text-transform: uppercase; }

.sidebar-nav {
  padding: 16px 12px;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  color: var(--text2);
  text-decoration: none;
  font-size: 13px;
  transition: 0.15s;
}
.nav-item:hover  { background: var(--bg3); color: var(--text); }
.nav-item.active { background: rgba(249,115,22,0.12); color: var(--accent); }
.nav-icon { font-size: 16px; width: 20px; text-align: center; }

.nav-separator     { height: 1px; background: var(--border); margin: 8px 0 4px; }
.nav-section-label { font-size: 9px; color: var(--text3); letter-spacing: 0.12em; text-transform: uppercase; padding: 0 12px 4px; }

.sidebar-footer {
  padding: 12px;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.exchange-rate {
  background: var(--bg3);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px;
}
.er-label  { font-size: 10px; color: var(--text3); text-transform: uppercase; }
.er-value  { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; color: var(--accent); margin: 4px 0; }
.er-source { font-size: 10px; color: var(--text3); }

.user-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--bg3);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
}
.user-info { display: flex; align-items: center; gap: 8px; }
.user-avatar {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: var(--accent);
  color: white;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-weight: 700; font-size: 12px;
}
.user-name { font-size: 12px; font-weight: 500; }
.user-role { font-size: 10px; color: var(--accent); }
.btn-logout {
  background: none;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text3);
  cursor: pointer;
  padding: 4px 7px;
  font-size: 13px;
  transition: all 0.15s;
}
.btn-logout:hover { border-color: var(--red); color: var(--red); }

.employee-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--bg3);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
}
.employee-icon   { font-size: 18px; opacity: 0.5; }
.employee-label  { font-size: 11px; color: var(--text2); margin-bottom: 4px; }
.btn-admin-login {
  background: none;
  border: none;
  color: var(--accent);
  font-family: 'DM Mono', monospace;
  font-size: 11px;
  cursor: pointer;
  padding: 0;
  text-align: left;
}
.btn-admin-login:hover { text-decoration: underline; }

.main-content {
  margin-left: var(--sidebar-w);
  flex: 1;
  min-height: 100vh;
  background: var(--bg);
}
</style>
