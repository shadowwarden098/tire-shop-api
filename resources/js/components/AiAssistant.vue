<template>
  <div class="ai-assistant">
    <!-- Botón flotante con badge de alertas -->
    <button class="ai-toggle-btn" @click="toggleChat" :class="{ active: isOpen }">
      <span v-if="!isOpen">
        🤖 Asistente IA
        <span v-if="alertCount > 0" class="alert-badge">{{ alertCount }}</span>
      </span>
      <span v-else>✕ Cerrar</span>
    </button>

    <transition name="slide-up">
      <div v-if="isOpen" class="ai-chat-panel" :class="{ 'admin-mode': isAdmin }">

        <!-- Header -->
        <div class="ai-header" :class="{ 'admin-header': isAdmin }">
          <div class="ai-header-info">
            <span class="ai-avatar">{{ aiAvatar }}</span>
            <div>
              <h3>{{ aiName }}</h3>
              <small>Importaciones Adan</small>
            </div>
          </div>
          <div class="header-actions">
            <button class="icon-btn" @click="loadHistory" title="Cargar historial">📋</button>
            <button class="icon-btn" @click="clearChat"   title="Limpiar chat">🗑️</button>
          </div>
        </div>

        <!-- Alertas automáticas -->
        <div v-if="alerts.length > 0" class="alerts-bar">
          <div
            v-for="(alert, i) in alerts" :key="i"
            class="alert-item"
            :class="'alert-' + alert.type"
          >
            {{ alert.icon }} {{ alert.message }}
          </div>
        </div>

        <!-- Mensajes -->
        <div class="ai-messages" ref="messagesContainer">

          <!-- Bienvenida -->
          <div v-if="messages.length === 0" class="welcome-message">
            <p class="welcome-text">{{ welcomeMessage }}</p>
            <p class="slash-tip">💡 Escribe <code>/</code> para ver comandos rápidos</p>
            <div class="suggestions">
              <button
                v-for="s in suggestions" :key="s"
                @click="sendSuggestion(s)"
                class="suggestion-chip"
              >{{ s }}</button>
            </div>
          </div>

          <!-- Historial -->
          <div v-for="(msg, i) in messages" :key="i" class="message" :class="msg.role">
            <div class="message-bubble">
              <span class="message-avatar">{{ msg.role === 'user' ? '👤' : aiAvatar }}</span>
              <div class="message-body">
                <div class="message-meta" v-if="msg.time">{{ msg.time }}</div>
                <div class="message-content" v-html="renderMarkdown(msg.content)"></div>
                <!-- Botón descarga PDF -->
                <a
                  v-if="msg.downloadUrl"
                  :href="msg.downloadUrl"
                  target="_blank"
                  class="pdf-download-btn"
                >{{ msg.downloadLabel || '📄 Descargar PDF' }}</a>
              </div>
            </div>
          </div>

          <!-- Typing -->
          <div v-if="isLoading" class="message assistant">
            <div class="message-bubble">
              <span class="message-avatar">{{ aiAvatar }}</span>
              <div class="typing-indicator">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Slash commands dropdown -->
        <div v-if="showCommands" class="commands-dropdown">
          <div
            v-for="cmd in filteredCommands" :key="cmd.text"
            class="command-item"
            @click="useCommand(cmd)"
          >
            <span class="cmd-icon">{{ cmd.icon }}</span>
            <div>
              <div class="cmd-text">{{ cmd.text }}</div>
              <div class="cmd-desc">{{ cmd.desc }}</div>
            </div>
          </div>
        </div>

        <!-- Input -->
        <div class="ai-input-area">
          <textarea
            v-model="userInput"
            @keydown.enter.prevent="sendMessage"
            @keydown.escape="showCommands = false"
            @input="onInput"
            placeholder="Escribe tu pregunta... (/ para comandos)"
            :disabled="isLoading"
            rows="1"
            ref="inputArea"
          ></textarea>
          <button
            @click="sendMessage"
            :disabled="isLoading || !userInput.trim()"
            class="send-btn"
          >{{ isLoading ? '...' : '➤' }}</button>
        </div>

      </div>
    </transition>
  </div>
</template>

<script>
import axios from 'axios'

const SLASH_COMMANDS = [
  { icon: '📊', text: '/pdf financiero',    desc: 'Descargar reporte financiero PDF',    fill: 'Generar PDF de ganancias' },
  { icon: '📦', text: '/pdf inventario',    desc: 'Descargar reporte de inventario PDF', fill: 'Generar PDF de inventario' },
  { icon: '📈', text: '/ventas hoy',        desc: 'Ver ventas del día de hoy',           fill: '📈 Resumen de ventas de hoy' },
  { icon: '⚠️', text: '/stock bajo',        desc: 'Productos con stock crítico',         fill: '⚠️ Productos con stock bajo' },
  { icon: '👥', text: '/clientes',          desc: 'Clientes inactivos',                  fill: '👥 Clientes inactivos' },
  { icon: '💱', text: '/cambio',            desc: 'Tipo de cambio actual',               fill: '💱 Tipo de cambio actual' },
  { icon: '💸', text: '/gastos',            desc: 'Comparar gastos vs ingresos',         fill: '💸 Gastos vs ingresos' },
]

export default {
  name: 'AiAssistant',
  data() {
    return {
      isOpen:       false,
      isLoading:    false,
      userInput:    '',
      messages:     [],
      alerts:       [],
      sessionId:    null,
      showCommands: false,
      userName:     '',
    }
  },
  mounted() {
    const user = localStorage.getItem('auth_user')
    if (user) {
      try { this.userName = JSON.parse(user).name || 'Usuario' } catch {}
    }
    if (this.isAdmin) this.fetchAlerts()
  },
  computed: {
    isAdmin() {
      const user = localStorage.getItem('auth_user')
      if (!user) return false
      try { return JSON.parse(user).role === 'admin' } catch { return false }
    },
    aiAvatar()  { return this.isAdmin ? '📊' : '🔧' },
    aiName()    { return this.isAdmin ? 'Asesor de Negocios' : 'Asesor de Ventas' },
    alertCount(){ return this.alerts.length },
    welcomeMessage() {
      return this.isAdmin
        ? `Bienvenido, ${this.userName}. Analicemos el rendimiento de hoy.`
        : `¡Hola ${this.userName}! ¿Qué necesitas para tu vehículo?`
    },
    suggestions() {
      return this.isAdmin
        ? ['📈 Resumen de ventas de hoy', '⚠️ Productos con stock bajo', '👥 Clientes inactivos',
           '💱 Tipo de cambio actual', '📊 Generar PDF de ganancias', '📄 Generar PDF de inventario',
           '💸 Gastos vs ingresos']
        : ['🔍 Llantas según mi vehículo', '📦 Ver precios', '💳 Opciones de pago', '🛠️ Agendar instalación']
    },
    filteredCommands() {
      const q = this.userInput.slice(1).toLowerCase()
      return SLASH_COMMANDS.filter(c =>
        c.text.toLowerCase().includes(q) || c.desc.toLowerCase().includes(q)
      )
    },
  },
  methods: {
    toggleChat() {
      this.isOpen = !this.isOpen
      if (this.isOpen) {
        this.$nextTick(() => this.$refs.inputArea?.focus())
        if (this.isAdmin && this.alerts.length === 0) this.fetchAlerts()
      }
    },

    async fetchAlerts() {
      try {
        const token = localStorage.getItem('auth_token')
        const { data } = await axios.get('/api/ai/alerts', {
          headers: token ? { Authorization: `Bearer ${token}` } : {},
        })
        this.alerts = data.alerts || []
      } catch {}
    },

    async loadHistory() {
      try {
        const token = localStorage.getItem('auth_token')
        const { data } = await axios.get('/api/ai/history', {
          headers: token ? { Authorization: `Bearer ${token}` } : {},
        })
        if (data.history?.length) {
          this.messages = data.history.map(m => ({
            role:          m.role,
            content:       m.content,
            time:          m.created_at,
            downloadUrl:   m.download_url,
            downloadLabel: m.download_label,
          }))
          this.$nextTick(() => this.scrollToBottom())
        }
      } catch {}
    },

    async clearChat() {
      this.messages = []
      try {
        const token = localStorage.getItem('auth_token')
        await axios.delete('/api/ai/history', {
          headers: token ? { Authorization: `Bearer ${token}` } : {},
        })
      } catch {}
    },

    sendSuggestion(text) {
      this.userInput = text
      this.sendMessage()
    },

    onInput() {
      this.showCommands = this.isAdmin && this.userInput.startsWith('/')
    },

    useCommand(cmd) {
      this.userInput    = cmd.fill
      this.showCommands = false
      this.$refs.inputArea?.focus()
    },

    // Markdown básico → HTML
    renderMarkdown(text) {
      if (!text) return ''
      return text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        // Bloques de código
        .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        // Negrita
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        // Tablas Markdown simples
        .replace(/^\|(.+)\|$/gm, (_, row) => {
          const cells = row.split('|').map(c => c.trim())
          return '<tr>' + cells.map(c => `<td>${c}</td>`).join('') + '</tr>'
        })
        .replace(/(<tr>.*<\/tr>\n?)+/gs, match => `<table class="md-table">${match}</table>`)
        // Encabezados
        .replace(/^### (.+)$/gm, '<h4>$1</h4>')
        .replace(/^## (.+)$/gm,  '<h3>$1</h3>')
        // Listas
        .replace(/^[-*] (.+)$/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>\n?)+/gs, match => `<ul>${match}</ul>`)
        // Saltos de línea
        .replace(/\n/g, '<br>')
    },

    async sendMessage() {
      const text = this.userInput.trim()
      if (!text || this.isLoading) return
      this.showCommands = false

      // Bloqueo frontend empleados
      if (!this.isAdmin && /(gananc(?:ia|ias)|utilidad(?:es)?|beneficio|margenes?|reportes?|estad[ií]sticas?|ventas? totales?|financieras?)/i.test(text)) {
        this.messages.push({ role: 'assistant', content: '❌ Lo siento, esa información solo la maneja el administrador. 😊' })
        this.userInput = ''
        this.$nextTick(() => this.scrollToBottom())
        return
      }

      this.messages.push({ role: 'user', content: text, time: new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) })
      this.userInput = ''
      this.isLoading = true
      this.scrollToBottom()

      try {
        const token = localStorage.getItem('auth_token')
        const { data } = await axios.post('/api/ai/chat', {
          message:    text,
          session_id: this.sessionId,
          history:    this.messages.slice(0, -1).map(m => ({ role: m.role, content: m.content })),
        }, { headers: token ? { Authorization: `Bearer ${token}` } : {} })

        if (data.session_id) this.sessionId = data.session_id

        const msg = {
          role:    'assistant',
          content: data.reply || '',
          time:    new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }),
        }
        if (data.action === 'download_pdf' && data.download_url) {
          msg.downloadUrl   = data.download_url
          msg.downloadLabel = data.label || '📄 Descargar PDF'
        }
        this.messages.push(msg)

      } catch (error) {
        const status = error.response?.status
        let errorMsg = '❌ Error al conectar con la IA. Intenta de nuevo.'
        if (status === 429) errorMsg = '⏳ Demasiadas consultas. Espera unos segundos.'
        else if (status === 503) errorMsg = '⚠️ Servicio de IA temporalmente no disponible.'
        this.messages.push({ role: 'assistant', content: errorMsg })
      } finally {
        this.isLoading = false
        this.$nextTick(() => this.scrollToBottom())
      }
    },

    scrollToBottom() {
      const c = this.$refs.messagesContainer
      if (c) c.scrollTop = c.scrollHeight
    },
  },
}
</script>

<style scoped>
.ai-assistant { position: fixed; bottom: 24px; right: 24px; z-index: 9999; }

/* ── Botón flotante ─────────────────────────────────────────── */
.ai-toggle-btn {
  position: relative;
  background: #2563eb; color: white; border: none;
  padding: 12px 20px; border-radius: 50px; cursor: pointer;
  font-size: 14px; font-weight: 600;
  box-shadow: 0 4px 15px rgba(37,99,235,0.4); transition: all 0.2s;
}
.ai-toggle-btn:hover  { background: #1d4ed8; transform: translateY(-2px); }
.ai-toggle-btn.active { background: #dc2626; }

.alert-badge {
  position: absolute; top: -6px; right: -6px;
  background: #ef4444; color: white;
  width: 20px; height: 20px; border-radius: 50%;
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid white;
}

/* ── Panel ──────────────────────────────────────────────────── */
.ai-chat-panel {
  position: absolute; bottom: 56px; right: 0;
  width: 390px; height: 560px; background: white;
  border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
  display: flex; flex-direction: column; overflow: hidden;
  border: 1px solid #e5e7eb;
}
.ai-chat-panel.admin-mode { border: 1px solid rgba(249,115,22,0.3); }

/* ── Header ─────────────────────────────────────────────────── */
.ai-header {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white; padding: 14px 16px;
  display: flex; justify-content: space-between; align-items: center;
  flex-shrink: 0;
}
.ai-header.admin-header { background: linear-gradient(135deg, #f97316, #ea580c); }
.ai-header-info { display: flex; align-items: center; gap: 10px; }
.ai-avatar { font-size: 24px; }
.ai-header h3 { margin: 0; font-size: 15px; }
.ai-header small { opacity: 0.8; font-size: 11px; }
.header-actions { display: flex; gap: 6px; }
.icon-btn { background: rgba(255,255,255,0.2); border: none; color: white; cursor: pointer; font-size: 14px; padding: 5px 8px; border-radius: 6px; transition: background .2s; }
.icon-btn:hover { background: rgba(255,255,255,0.35); }

/* ── Alertas ────────────────────────────────────────────────── */
.alerts-bar {
  background: #fffbeb; border-bottom: 1px solid #fde68a;
  padding: 6px 12px; display: flex; flex-direction: column; gap: 4px;
  max-height: 100px; overflow-y: auto; flex-shrink: 0;
}
.alert-item { font-size: 11px; padding: 3px 0; }
.alert-warning { color: #92400e; }
.alert-success { color: #065f46; }
.alert-danger  { color: #991b1b; }
.alert-info    { color: #1e40af; }

/* ── Mensajes ───────────────────────────────────────────────── */
.ai-messages {
  flex: 1; overflow-y: auto; padding: 14px;
  display: flex; flex-direction: column; gap: 12px; background: #f9fafb;
}

.welcome-message { text-align: center; color: #6b7280; font-size: 13px; }
.slash-tip { font-size: 11px; color: #9ca3af; margin-top: 6px; }
.slash-tip code { background: #e5e7eb; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
.suggestions { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; justify-content: center; }
.suggestion-chip {
  background: white; border: 1px solid #d1d5db; border-radius: 20px;
  padding: 5px 11px; font-size: 11px; cursor: pointer; color: #374151; transition: all 0.2s;
}
.suggestion-chip:hover { background: #eff6ff; border-color: #2563eb; color: #2563eb; }

.message { display: flex; }
.message.user      { justify-content: flex-end; }
.message.assistant { justify-content: flex-start; }

.message-bubble { display: flex; align-items: flex-start; gap: 8px; max-width: 90%; }
.message.user .message-bubble { flex-direction: row-reverse; }
.message-avatar { font-size: 18px; margin-top: 2px; flex-shrink: 0; }
.message-body   { display: flex; flex-direction: column; gap: 6px; }
.message-meta   { font-size: 9px; color: #9ca3af; }
.message.user .message-meta { text-align: right; }

.message-content {
  background: white; padding: 10px 14px; border-radius: 12px;
  font-size: 13px; line-height: 1.6; color: #374151;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08); word-break: break-word;
}
.message.user .message-content { background: #2563eb; color: white; }
.message-content :deep(pre) {
  background: #1e1e1e; color: #d4d4d4; padding: 10px 12px;
  border-radius: 8px; overflow-x: auto; font-size: 11px; margin: 6px 0;
}
.message-content :deep(code) { font-family: monospace; font-size: 12px; }
.message-content :deep(strong) { font-weight: 700; }
.message-content :deep(h3), .message-content :deep(h4) { margin: 8px 0 4px; font-size: 13px; }
.message-content :deep(ul) { padding-left: 16px; margin: 4px 0; }
.message-content :deep(li) { margin-bottom: 2px; }
.message-content :deep(.md-table) {
  border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 12px;
}
.message-content :deep(.md-table td) {
  border: 1px solid #d1d5db; padding: 4px 8px;
}
.message-content :deep(.md-table tr:first-child td) {
  background: #f3f4f6; font-weight: 600;
}

/* ── Botón PDF ──────────────────────────────────────────────── */
.pdf-download-btn {
  display: inline-block; background: #dc2626; color: white;
  text-decoration: none; padding: 9px 16px; border-radius: 8px;
  font-size: 13px; font-weight: 600; text-align: center;
  transition: background 0.2s, transform 0.1s;
  box-shadow: 0 2px 8px rgba(220,38,38,0.35);
}
.pdf-download-btn:hover  { background: #b91c1c; transform: translateY(-1px); }
.pdf-download-btn:active { transform: translateY(0); }

/* ── Typing ─────────────────────────────────────────────────── */
.typing-indicator { display: flex; gap: 4px; padding: 8px 14px; }
.typing-indicator span {
  width: 8px; height: 8px; background: #9ca3af; border-radius: 50%;
  animation: bounce 1.2s infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30%           { transform: translateY(-8px); }
}

/* ── Slash commands ─────────────────────────────────────────── */
.commands-dropdown {
  position: absolute; bottom: 70px; left: 12px; right: 12px;
  background: white; border: 1px solid #e5e7eb; border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 10; max-height: 220px; overflow-y: auto;
}
.command-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; cursor: pointer; transition: background 0.15s;
}
.command-item:hover { background: #f0f9ff; }
.cmd-icon { font-size: 18px; flex-shrink: 0; }
.cmd-text { font-size: 13px; font-weight: 600; color: #111827; }
.cmd-desc { font-size: 11px; color: #6b7280; }

/* ── Input ──────────────────────────────────────────────────── */
.ai-input-area {
  padding: 12px; border-top: 1px solid #e5e7eb;
  display: flex; gap: 8px; background: white; flex-shrink: 0;
}
.ai-input-area textarea {
  flex: 1; border: 1px solid #d1d5db; border-radius: 20px;
  padding: 8px 14px; font-size: 13px; resize: none; outline: none; font-family: inherit;
  transition: border-color 0.2s;
}
.ai-input-area textarea:focus { border-color: #2563eb; }
.send-btn {
  background: #2563eb; color: white; border: none;
  width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
  font-size: 16px; flex-shrink: 0; transition: background 0.2s;
}
.send-btn:hover:not(:disabled) { background: #1d4ed8; }
.send-btn:disabled { background: #d1d5db; cursor: not-allowed; }

/* ── Animación ──────────────────────────────────────────────── */
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(20px); }
</style>