<template>
  <div class="welcome-page">
    <div class="bg-glow g1"></div>
    <div class="bg-glow g2"></div>

    <!-- PASO 1: Elegir rol -->
    <div v-if="step === 'choose'" class="card">
      <div class="card-brand">
        <span class="brand-icon">⬡</span>
        <div>
          <div class="brand-name">Importaciones Adan</div>
          <div class="brand-sub">SISTEMA DE GESTIÓN</div>
        </div>
      </div>
      <h1 class="card-title">Bienvenido</h1>
      <p class="card-desc">¿Cómo deseas ingresar al sistema?</p>
      <div class="role-options">
        <button class="role-btn role-employee" @click="enterAsEmployee">
          <div class="role-icon">👤</div>
          <div class="role-info">
            <div class="role-name">Empleado</div>
            <div class="role-hint">Acceso directo sin contraseña</div>
          </div>
          <span class="role-arrow">→</span>
        </button>
        <button class="role-btn role-admin" @click="step = 'login'">
          <div class="role-icon">🔐</div>
          <div class="role-info">
            <div class="role-name">Administrador</div>
            <div class="role-hint">Requiere usuario y contraseña</div>
          </div>
          <span class="role-arrow">→</span>
        </button>
      </div>
    </div>

    <!-- PASO 2: Formulario Admin -->
    <div v-if="step === 'login'" class="card">
      <button class="btn-back" @click="step = 'choose'">← Volver</button>
      <div class="card-brand">
        <span class="brand-icon">⬡</span>
        <div>
          <div class="brand-name">Importaciones Adan</div>
          <div class="brand-sub">SISTEMA DE GESTIÓN</div>
        </div>
      </div>
      <h1 class="card-title">Acceso Admin</h1>
      <p class="card-desc">Ingresa tus credenciales de administrador.</p>
      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label>Correo electrónico</label>
          <input v-model="email" type="email" required placeholder="admin@tireshop.com" autocomplete="email" :disabled="loading" />
        </div>
        <div class="form-group">
          <label>Contraseña</label>
          <div class="pass-wrap">
            <input v-model="password" :type="showPass ? 'text' : 'password'" required placeholder="••••••••" autocomplete="current-password" :disabled="loading" />
            <button type="button" class="pass-eye" @click="showPass = !showPass">{{ showPass ? '🙈' : '👁' }}</button>
          </div>
        </div>
        <div class="login-error" v-if="error"><span>⚠</span> {{ error }}</div>
        <button type="submit" class="btn-login" :disabled="loading">
          <span v-if="loading" class="spinner-sm"></span>
          {{ loading ? 'Verificando...' : 'Ingresar' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router   = useRouter()
const step     = ref('choose')
const email    = ref('admin@tireshop.com')
const password = ref('')
const loading  = ref(false)
const error    = ref('')
const showPass = ref(false)

const enterAsEmployee = () => {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('auth_user')
  localStorage.setItem('auth_role', 'employee')
  router.push('/dashboard')
}

const handleLogin = async () => {
  loading.value = true
  error.value   = ''
  try {
    const res  = await fetch('/api/auth/login', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify({ email: email.value, password: password.value }),
    })
    const json = await res.json()
    if (json.success) {
      localStorage.setItem('auth_token', json.token)
      localStorage.setItem('auth_user',  JSON.stringify(json.user))
      localStorage.setItem('auth_role',  'admin')
      router.push('/dashboard')
    } else {
      error.value = json.message || 'Credenciales incorrectas.'
    }
  } catch (e) {
    error.value = 'No se pudo conectar con el servidor.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.welcome-page {
  min-height: 100vh;
  background: var(--bg);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
.bg-glow { position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.06; pointer-events: none; }
.g1 { width: 600px; height: 600px; background: var(--accent); top: -200px; right: -100px; }
.g2 { width: 500px; height: 500px; background: var(--blue); bottom: -150px; left: -80px; }
.card { background: var(--bg2); border: 1px solid var(--border); border-radius: 20px; padding: 40px; width: 100%; max-width: 420px; position: relative; z-index: 1; }
.btn-back { background: none; border: none; color: var(--text3); font-family: 'DM Mono', monospace; font-size: 12px; cursor: pointer; padding: 0; margin-bottom: 24px; display: block; transition: color 0.15s; }
.btn-back:hover { color: var(--text2); }
.card-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
.brand-icon { font-size: 26px; color: var(--accent); }
.brand-name { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 800; }
.brand-sub  { font-size: 9px; color: var(--text3); letter-spacing: 0.15em; margin-top: 2px; }
.card-title { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; margin-bottom: 6px; }
.card-desc  { font-size: 12px; color: var(--text3); margin-bottom: 28px; }
.role-options { display: flex; flex-direction: column; gap: 12px; }
.role-btn { display: flex; align-items: center; gap: 16px; padding: 18px 20px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg3); cursor: pointer; transition: all 0.2s; text-align: left; width: 100%; }
.role-btn:hover { transform: translateX(4px); }
.role-employee:hover { border-color: var(--blue); }
.role-admin:hover    { border-color: var(--accent); }
.role-icon { font-size: 24px; }
.role-info { flex: 1; }
.role-name { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; color: var(--text); }
.role-hint { font-size: 11px; color: var(--text3); margin-top: 3px; }
.role-arrow { font-size: 18px; color: var(--text3); transition: color 0.15s; }
.role-btn:hover .role-arrow { color: var(--text); }
.login-form { display: flex; flex-direction: column; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; }
.form-group input { padding: 11px 14px; background: var(--bg3); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'DM Mono', monospace; font-size: 13px; width: 100%; transition: border-color 0.15s; }
.form-group input:focus { outline: none; border-color: var(--accent); }
.form-group input:disabled { opacity: 0.5; }
.pass-wrap { position: relative; }
.pass-wrap input { padding-right: 44px; }
.pass-eye { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px; opacity: 0.6; }
.pass-eye:hover { opacity: 1; }
.login-error { display: flex; align-items: center; gap: 8px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 10px 14px; color: var(--red); font-size: 12px; }
.btn-login { padding: 12px; background: var(--accent); border: none; border-radius: 10px; color: white; font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: opacity 0.15s; margin-top: 4px; }
.btn-login:hover:not(:disabled) { opacity: 0.85; }
.btn-login:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner-sm { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
