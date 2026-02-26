<template>
  <div class="ai-assistant">
    <!-- Botón flotante -->
    <button class="ai-toggle-btn" @click="toggleChat" :class="{ active: isOpen }">
      <span v-if="!isOpen">🤖 Asistente IA</span>
      <span v-else>✕ Cerrar</span>
    </button>

    <!-- Panel de chat -->
    <transition name="slide-up">
      <div v-if="isOpen" class="ai-chat-panel">
        <div class="ai-header">
          <div class="ai-header-info">
            <span class="ai-avatar">🤖</span>
            <div>
              <h3>Asistente IA</h3>
              <small>Importaciones Adan</small>
            </div>
          </div>
          <button class="clear-btn" @click="clearChat" title="Limpiar">🗑️</button>
        </div>

        <!-- Mensajes -->
        <div class="ai-messages" ref="messagesContainer">
          <!-- Bienvenida -->
          <div v-if="messages.length === 0" class="welcome-message">
            <p>¡Hola! ¿En qué te puedo ayudar?</p>
            <div class="suggestions">
              <button v-for="s in suggestions" :key="s" @click="sendSuggestion(s)" class="suggestion-chip">
                {{ s }}
              </button>
            </div>
          </div>

          <!-- Historial -->
          <div v-for="(msg, i) in messages" :key="i" class="message" :class="msg.role">
            <div class="message-bubble">
              <span class="message-avatar">{{ msg.role === 'user' ? '👤' : '🤖' }}</span>
              <div class="message-content" v-html="msg.content"></div>
            </div>
          </div>

          <!-- Typing -->
          <div v-if="isLoading" class="message assistant">
            <div class="message-bubble">
              <span class="message-avatar">🤖</span>
              <div class="typing-indicator">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Input -->
        <div class="ai-input-area">
          <textarea
            v-model="userInput"
            @keydown.enter.prevent="sendMessage"
            placeholder="Escribe tu pregunta..."
            :disabled="isLoading"
            rows="1"
            ref="inputArea"
          ></textarea>
          <button @click="sendMessage" :disabled="isLoading || !userInput.trim()" class="send-btn">
            {{ isLoading ? '...' : '➤' }}
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AiAssistant',
  data() {
    return {
      isOpen: false,
      isLoading: false,
      userInput: '',
      messages: [],
      suggestions: [
        '📊 ¿Cómo van las ventas este mes?',
        '⚠️ ¿Qué productos tienen stock bajo?',
        '👥 ¿Quiénes son mis mejores clientes?',
        '💡 ¿Qué debería reabastecer?',
      ],
    }
  },
  methods: {
    toggleChat() {
      this.isOpen = !this.isOpen
      if (this.isOpen) this.$nextTick(() => this.$refs.inputArea?.focus())
    },
    clearChat() {
      this.messages = []
    },
    sendSuggestion(text) {
      this.userInput = text
      this.sendMessage()
    },
    async sendMessage() {
      const text = this.userInput.trim()
      if (!text || this.isLoading) return

      this.messages.push({ role: 'user', content: text })
      this.userInput = ''
      this.isLoading = true
      this.scrollToBottom()

      try {
        const token = localStorage.getItem('auth_token')
        const history = this.messages.slice(0, -1)

        const { data } = await axios.post('/api/ai/chat', {
          message: text,
          history: history,
        }, {
          headers: { Authorization: `Bearer ${token}` }
        })

        this.messages.push({ role: 'assistant', content: data.reply })
      } catch (error) {
        this.messages.push({
          role: 'assistant',
          content: '❌ Error al conectar con la IA. Intenta de nuevo.',
        })
      } finally {
        this.isLoading = false
        this.$nextTick(() => this.scrollToBottom())
      }
    },
    scrollToBottom() {
      const container = this.$refs.messagesContainer
      if (container) container.scrollTop = container.scrollHeight
    },
  },
}
</script>

<style scoped>
.ai-assistant { position: fixed; bottom: 24px; right: 24px; z-index: 9999; }

.ai-toggle-btn {
  background: #2563eb; color: white; border: none;
  padding: 12px 20px; border-radius: 50px; cursor: pointer;
  font-size: 14px; font-weight: 600;
  box-shadow: 0 4px 15px rgba(37,99,235,0.4); transition: all 0.2s;
}
.ai-toggle-btn:hover { background: #1d4ed8; transform: translateY(-2px); }
.ai-toggle-btn.active { background: #dc2626; }

.ai-chat-panel {
  position: absolute; bottom: 56px; right: 0;
  width: 370px; height: 500px; background: white;
  border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
  display: flex; flex-direction: column; overflow: hidden;
  border: 1px solid #e5e7eb;
}

.ai-header {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white; padding: 14px 16px;
  display: flex; justify-content: space-between; align-items: center;
}
.ai-header-info { display: flex; align-items: center; gap: 10px; }
.ai-avatar { font-size: 24px; }
.ai-header h3 { margin: 0; font-size: 15px; }
.ai-header small { opacity: 0.8; font-size: 11px; }
.clear-btn { background: none; border: none; color: white; cursor: pointer; font-size: 16px; }

.ai-messages {
  flex: 1; overflow-y: auto; padding: 16px;
  display: flex; flex-direction: column; gap: 12px; background: #f9fafb;
}

.welcome-message { text-align: center; color: #6b7280; font-size: 13px; }
.suggestions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; justify-content: center; }
.suggestion-chip {
  background: white; border: 1px solid #d1d5db; border-radius: 20px;
  padding: 6px 12px; font-size: 12px; cursor: pointer; color: #374151; transition: all 0.2s;
}
.suggestion-chip:hover { background: #eff6ff; border-color: #2563eb; color: #2563eb; }

.message { display: flex; }
.message.user { justify-content: flex-end; }
.message.assistant { justify-content: flex-start; }

.message-bubble { display: flex; align-items: flex-start; gap: 8px; max-width: 85%; }
.message.user .message-bubble { flex-direction: row-reverse; }
.message-avatar { font-size: 18px; margin-top: 2px; flex-shrink: 0; }

.message-content {
  background: white; padding: 10px 14px; border-radius: 12px;
  font-size: 13px; line-height: 1.5; color: #374151;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08); white-space: pre-wrap;
}
.message.user .message-content { background: #2563eb; color: white; }

.typing-indicator { display: flex; gap: 4px; padding: 8px 14px; }
.typing-indicator span {
  width: 8px; height: 8px; background: #9ca3af; border-radius: 50%;
  animation: bounce 1.2s infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-8px); }
}

.ai-input-area {
  padding: 12px; border-top: 1px solid #e5e7eb;
  display: flex; gap: 8px; background: white;
}
.ai-input-area textarea {
  flex: 1; border: 1px solid #d1d5db; border-radius: 20px;
  padding: 8px 14px; font-size: 13px; resize: none; outline: none; font-family: inherit;
}
.ai-input-area textarea:focus { border-color: #2563eb; }
.send-btn {
  background: #2563eb; color: white; border: none;
  width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 16px; flex-shrink: 0;
}
.send-btn:hover:not(:disabled) { background: #1d4ed8; }
.send-btn:disabled { background: #d1d5db; cursor: not-allowed; }

.slide-up-enter-active, .slide-up-leave-active { transition: all 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(20px); }
</style>