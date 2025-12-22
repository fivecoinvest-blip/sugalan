<template>
<div class="verify-page">
  <div class="verify-container">
    <!-- Header -->
    <div class="verify-header">
      <h1>🔐 Provably Fair Verification</h1>
      <p class="subtitle">Verify any game result independently using cryptographic proof</p>
    </div>

    <!-- How It Works -->
    <div class="how-it-works">
      <h2>How It Works</h2>
      <div class="steps-grid">
        <div class="step">
          <div class="step-number">1</div>
          <h3>Server Seed</h3>
          <p>Before each bet, we generate a random server seed and show you its hash (SHA-256). The actual seed remains hidden until after the bet.</p>
        </div>
        <div class="step">
          <div class="step-number">2</div>
          <h3>Client Seed</h3>
          <p>You provide your own random string (client seed) or use the default one. You can change this anytime before betting.</p>
        </div>
        <div class="step">
          <div class="step-number">3</div>
          <h3>Result Generation</h3>
          <p>The game result is calculated using: <code>HMAC-SHA256(client_seed:nonce, server_seed)</code></p>
        </div>
        <div class="step">
          <div class="step-number">4</div>
          <h3>Verification</h3>
          <p>After the bet, the server seed is revealed. You can verify that the hash matches and recalculate the result yourself.</p>
        </div>
      </div>
    </div>

    <!-- Verification Form -->
    <div class="verify-form-section">
      <h2>Verify a Bet Result</h2>
      
      <div class="form-group">
        <label for="game-type">Game Type</label>
        <select id="game-type" v-model="formData.gameType" class="form-input">
          <option value="">Select a game...</option>
          <option value="dice">Dice 🎲</option>
          <option value="hilo">Hi-Lo 🔼</option>
          <option value="mines">Mines 💣</option>
          <option value="plinko">Plinko 🔵</option>
          <option value="keno">Keno 🔢</option>
          <option value="wheel">Wheel 🎡</option>
          <option value="crash">Crash 📉</option>
          <option value="pump">Pump 🚀</option>
        </select>
      </div>

      <div class="form-group">
        <label for="server-seed">Server Seed (Revealed)</label>
        <input 
          id="server-seed" 
          v-model="formData.serverSeed" 
          type="text" 
          class="form-input"
          placeholder="Enter the revealed server seed..."
        >
      </div>

      <div class="form-group">
        <label for="server-seed-hash">Server Seed Hash (SHA-256)</label>
        <input 
          id="server-seed-hash" 
          v-model="formData.serverSeedHash" 
          type="text" 
          class="form-input"
          placeholder="Enter the server seed hash shown before the bet..."
        >
        <small v-if="computedHash" class="computed-hash">
          Computed Hash: <code>{{ computedHash }}</code>
          <span v-if="hashMatches === true" class="match-badge success">✓ Match</span>
          <span v-else-if="hashMatches === false" class="match-badge error">✗ Mismatch</span>
        </small>
      </div>

      <div class="form-group">
        <label for="client-seed">Client Seed</label>
        <input 
          id="client-seed" 
          v-model="formData.clientSeed" 
          type="text" 
          class="form-input"
          placeholder="Enter the client seed used for the bet..."
        >
      </div>

      <div class="form-group">
        <label for="nonce">Nonce</label>
        <input 
          id="nonce" 
          v-model.number="formData.nonce" 
          type="number" 
          class="form-input"
          placeholder="Enter the nonce..."
        >
      </div>

      <!-- Game-Specific Fields -->
      <div v-if="formData.gameType === 'dice'" class="game-specific-fields">
        <h3>Dice-Specific Data</h3>
        <div class="form-row">
          <div class="form-group">
            <label for="dice-target">Target Number</label>
            <input 
              id="dice-target" 
              v-model.number="formData.gameData.target" 
              type="number" 
              class="form-input"
              min="0"
              max="100"
            >
          </div>
          <div class="form-group">
            <label for="dice-prediction">Prediction</label>
            <select id="dice-prediction" v-model="formData.gameData.prediction" class="form-input">
              <option value="over">Over</option>
              <option value="under">Under</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="formData.gameType === 'mines'" class="game-specific-fields">
        <h3>Mines-Specific Data</h3>
        <div class="form-group">
          <label for="mines-count">Number of Mines</label>
          <input 
            id="mines-count" 
            v-model.number="formData.gameData.mineCount" 
            type="number" 
            class="form-input"
            min="1"
            max="24"
          >
        </div>
      </div>

      <div v-if="formData.gameType === 'plinko'" class="game-specific-fields">
        <h3>Plinko-Specific Data</h3>
        <div class="form-row">
          <div class="form-group">
            <label for="plinko-rows">Rows</label>
            <select id="plinko-rows" v-model.number="formData.gameData.rows" class="form-input">
              <option :value="8">8</option>
              <option :value="12">12</option>
              <option :value="16">16</option>
            </select>
          </div>
          <div class="form-group">
            <label for="plinko-risk">Risk Level</label>
            <select id="plinko-risk" v-model="formData.gameData.risk" class="form-input">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="formData.gameType === 'keno'" class="game-specific-fields">
        <h3>Keno-Specific Data</h3>
        <div class="form-group">
          <label>Selected Numbers (comma-separated)</label>
          <input 
            v-model="formData.gameData.selectedNumbers" 
            type="text" 
            class="form-input"
            placeholder="e.g., 5,12,23,34"
          >
        </div>
      </div>

      <button @click="verifyBet" class="btn-verify" :disabled="isVerifying || !canVerify">
        {{ isVerifying ? 'Verifying...' : 'Verify Result' }}
      </button>
    </div>

    <!-- Verification Result -->
    <div v-if="verificationResult" class="verification-result" :class="verificationResult.verified ? 'success' : 'error'">
      <div class="result-header">
        <span class="result-icon">{{ verificationResult.verified ? '✓' : '✗' }}</span>
        <h2>{{ verificationResult.verified ? 'Verification Successful' : 'Verification Failed' }}</h2>
      </div>

      <div v-if="verificationResult.verified" class="result-details">
        <div class="detail-row">
          <span class="label">Hash Generated:</span>
          <code class="value">{{ verificationResult.hash }}</code>
        </div>
        
        <div v-if="verificationResult.result" class="game-result">
          <h3>Game Result</h3>
          <pre>{{ JSON.stringify(verificationResult.result, null, 2) }}</pre>
        </div>

        <div class="success-message">
          <p>✓ The server seed hash matches the revealed seed</p>
          <p>✓ The game result was calculated correctly</p>
          <p>✓ This bet was provably fair</p>
        </div>
      </div>

      <div v-else class="error-details">
        <p>{{ verificationResult.error || 'The verification failed. Please check your inputs.' }}</p>
      </div>
    </div>

    <!-- Educational Content -->
    <div class="educational-section">
      <h2>Understanding the Verification Process</h2>
      
      <div class="accordion">
        <div class="accordion-item">
          <button @click="toggleAccordion('hash')" class="accordion-header">
            <span>What is a Hash?</span>
            <span>{{ activeAccordion === 'hash' ? '−' : '+' }}</span>
          </button>
          <div v-if="activeAccordion === 'hash'" class="accordion-content">
            <p>A hash is a one-way cryptographic function that converts any input into a fixed-length string. Key properties:</p>
            <ul>
              <li><strong>Deterministic:</strong> Same input always produces the same hash</li>
              <li><strong>One-way:</strong> Cannot reverse the hash to get the original input</li>
              <li><strong>Collision-resistant:</strong> Nearly impossible to find two different inputs with the same hash</li>
            </ul>
            <p>We use SHA-256, which produces a 64-character hexadecimal string.</p>
          </div>
        </div>

        <div class="accordion-item">
          <button @click="toggleAccordion('hmac')" class="accordion-header">
            <span>What is HMAC-SHA256?</span>
            <span>{{ activeAccordion === 'hmac' ? '−' : '+' }}</span>
          </button>
          <div v-if="activeAccordion === 'hmac'" class="accordion-content">
            <p>HMAC (Hash-based Message Authentication Code) combines a hash function with a secret key. Formula:</p>
            <code class="formula">HMAC-SHA256(message, key)</code>
            <p>In our system:</p>
            <ul>
              <li><strong>Key:</strong> Server Seed (kept secret until revealed)</li>
              <li><strong>Message:</strong> Client Seed + ":" + Nonce</li>
            </ul>
            <p>This ensures the result was determined before the bet and cannot be manipulated.</p>
          </div>
        </div>

        <div class="accordion-item">
          <button @click="toggleAccordion('nonce')" class="accordion-header">
            <span>What is a Nonce?</span>
            <span>{{ activeAccordion === 'nonce' ? '−' : '+' }}</span>
          </button>
          <div v-if="activeAccordion === 'nonce'" class="accordion-content">
            <p>A nonce (number used once) is an incrementing counter that starts at 0 for each seed pair. It ensures:</p>
            <ul>
              <li>Each bet has a unique result even with the same seeds</li>
              <li>Results are deterministic and verifiable</li>
              <li>You can verify any past bet by knowing the nonce</li>
            </ul>
            <p>Every time you place a bet, the nonce increments by 1.</p>
          </div>
        </div>

        <div class="accordion-item">
          <button @click="toggleAccordion('manual')" class="accordion-header">
            <span>Manual Verification Steps</span>
            <span>{{ activeAccordion === 'manual' ? '−' : '+' }}</span>
          </button>
          <div v-if="activeAccordion === 'manual'" class="accordion-content">
            <p>You can verify results manually using any programming language or online tools:</p>
            <ol>
              <li><strong>Verify Server Seed Hash:</strong> Calculate <code>SHA256(server_seed)</code> and compare with the hash shown before the bet</li>
              <li><strong>Generate Result Hash:</strong> Calculate <code>HMAC-SHA256(client_seed:nonce, server_seed)</code></li>
              <li><strong>Convert to Game Result:</strong> Use game-specific conversion (e.g., for Dice: <code>parseInt(hash.substring(0, 8), 16) % 10001 / 100</code>)</li>
              <li><strong>Compare:</strong> The result should match what you see in the game</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <!-- Example Verification -->
    <div class="example-section">
      <h2>Example Verification</h2>
      <div class="example-card">
        <h3>Dice Game Example</h3>
        <div class="example-data">
          <p><strong>Server Seed:</strong> <code>a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456</code></p>
          <p><strong>Server Seed Hash:</strong> <code>{{ exampleHash }}</code></p>
          <p><strong>Client Seed:</strong> <code>my-random-seed</code></p>
          <p><strong>Nonce:</strong> <code>42</code></p>
          <p><strong>Prediction:</strong> Over 50.00</p>
        </div>
        <button @click="loadExample" class="btn-load-example">Load This Example</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import CryptoJS from 'crypto-js'
import axios from 'axios'

const formData = ref({
  gameType: '',
  serverSeed: '',
  serverSeedHash: '',
  clientSeed: '',
  nonce: 0,
  gameData: {
    // Dice
    target: 50,
    prediction: 'over',
    // Mines
    mineCount: 3,
    // Plinko
    rows: 16,
    risk: 'medium',
    // Keno
    selectedNumbers: ''
  }
})

const isVerifying = ref(false)
const verificationResult = ref(null)
const activeAccordion = ref(null)

const computedHash = computed(() => {
  if (!formData.value.serverSeed) return ''
  return CryptoJS.SHA256(formData.value.serverSeed).toString()
})

const hashMatches = computed(() => {
  if (!formData.value.serverSeedHash || !computedHash.value) return null
  return computedHash.value === formData.value.serverSeedHash
})

const canVerify = computed(() => {
  return formData.value.gameType &&
         formData.value.serverSeed &&
         formData.value.serverSeedHash &&
         formData.value.clientSeed &&
         formData.value.nonce >= 0
})

const exampleHash = computed(() => {
  return CryptoJS.SHA256('a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456').toString()
})

async function verifyBet() {
  if (!canVerify.value) return

  isVerifying.value = true
  verificationResult.value = null

  try {
    const response = await axios.post('/api/games/verify', {
      game_type: formData.value.gameType,
      server_seed: formData.value.serverSeed,
      server_seed_hash: formData.value.serverSeedHash,
      client_seed: formData.value.clientSeed,
      nonce: formData.value.nonce,
      game_data: formData.value.gameData
    })

    verificationResult.value = response.data
  } catch (error) {
    verificationResult.value = {
      verified: false,
      error: error.response?.data?.message || 'Verification failed. Please check your inputs.'
    }
  } finally {
    isVerifying.value = false
  }
}

function toggleAccordion(section) {
  activeAccordion.value = activeAccordion.value === section ? null : section
}

function loadExample() {
  formData.value = {
    gameType: 'dice',
    serverSeed: 'a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456',
    serverSeedHash: exampleHash.value,
    clientSeed: 'my-random-seed',
    nonce: 42,
    gameData: {
      target: 50,
      prediction: 'over',
      mineCount: 3,
      rows: 16,
      risk: 'medium',
      selectedNumbers: ''
    }
  }
  verificationResult.value = null
}
</script>

<style scoped>
.verify-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem 1rem;
}

.verify-container {
  max-width: 1200px;
  margin: 0 auto;
}

.verify-header {
  text-align: center;
  color: white;
  margin-bottom: 3rem;
}

.verify-header h1 {
  font-size: 3rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
}

.subtitle {
  font-size: 1.25rem;
  opacity: 0.9;
}

.how-it-works, .verify-form-section, .educational-section, .example-section {
  background: white;
  border-radius: 1rem;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.how-it-works h2, .verify-form-section h2, .educational-section h2, .example-section h2 {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 1.5rem;
  color: #333;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

.step {
  text-align: center;
}

.step-number {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 auto 1rem;
}

.step h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #333;
}

.step p {
  color: #666;
  line-height: 1.6;
}

.step code {
  background: #f3f4f6;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  color: #667eea;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #333;
}

.form-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-size: 1rem;
  transition: border-color 0.3s;
}

.form-input:focus {
  outline: none;
  border-color: #667eea;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.game-specific-fields {
  background: #f9fafb;
  padding: 1.5rem;
  border-radius: 0.5rem;
  margin-top: 1.5rem;
}

.game-specific-fields h3 {
  font-size: 1.125rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: #667eea;
}

.computed-hash {
  display: block;
  margin-top: 0.5rem;
  padding: 0.5rem;
  background: #f3f4f6;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  word-break: break-all;
}

.computed-hash code {
  color: #667eea;
  font-weight: 600;
}

.match-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-weight: 600;
  margin-left: 0.5rem;
}

.match-badge.success {
  background: #10b981;
  color: white;
}

.match-badge.error {
  background: #ef4444;
  color: white;
}

.btn-verify {
  width: 100%;
  padding: 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.btn-verify:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.btn-verify:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.verification-result {
  background: white;
  border-radius: 1rem;
  padding: 2rem;
  margin-bottom: 2rem;
  border: 3px solid;
}

.verification-result.success {
  border-color: #10b981;
}

.verification-result.error {
  border-color: #ef4444;
}

.result-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.result-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.verification-result.success .result-icon {
  background: #10b981;
  color: white;
}

.verification-result.error .result-icon {
  background: #ef4444;
  color: white;
}

.result-header h2 {
  font-size: 1.75rem;
  font-weight: 700;
  margin: 0;
}

.result-details, .error-details {
  margin-top: 1.5rem;
}

.detail-row {
  margin-bottom: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 0.5rem;
}

.detail-row .label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #666;
}

.detail-row .value {
  display: block;
  word-break: break-all;
  color: #667eea;
  font-size: 0.875rem;
}

.game-result {
  margin-top: 1.5rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 0.5rem;
}

.game-result h3 {
  font-size: 1.125rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: #333;
}

.game-result pre {
  background: #1f2937;
  color: #10b981;
  padding: 1rem;
  border-radius: 0.5rem;
  overflow-x: auto;
  font-size: 0.875rem;
}

.success-message {
  margin-top: 1.5rem;
  padding: 1rem;
  background: #d1fae5;
  border-radius: 0.5rem;
}

.success-message p {
  margin: 0.5rem 0;
  color: #065f46;
  font-weight: 500;
}

.error-details p {
  color: #dc2626;
  font-weight: 500;
}

.accordion-item {
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  margin-bottom: 1rem;
  overflow: hidden;
}

.accordion-header {
  width: 100%;
  padding: 1rem 1.5rem;
  background: #f9fafb;
  border: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1.125rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
}

.accordion-header:hover {
  background: #f3f4f6;
}

.accordion-content {
  padding: 1.5rem;
  background: white;
}

.accordion-content p {
  margin-bottom: 1rem;
  line-height: 1.6;
  color: #4b5563;
}

.accordion-content ul, .accordion-content ol {
  margin-left: 1.5rem;
  margin-bottom: 1rem;
}

.accordion-content li {
  margin-bottom: 0.5rem;
  line-height: 1.6;
  color: #4b5563;
}

.accordion-content code {
  background: #f3f4f6;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  color: #667eea;
}

.accordion-content .formula {
  display: block;
  padding: 1rem;
  background: #1f2937;
  color: #10b981;
  border-radius: 0.5rem;
  margin: 1rem 0;
  text-align: center;
  font-size: 1rem;
}

.example-card {
  background: #f9fafb;
  padding: 1.5rem;
  border-radius: 0.5rem;
  border: 2px solid #e5e7eb;
}

.example-card h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: #333;
}

.example-data p {
  margin-bottom: 0.75rem;
  line-height: 1.6;
  color: #4b5563;
}

.example-data code {
  background: white;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  color: #667eea;
  word-break: break-all;
}

.btn-load-example {
  margin-top: 1rem;
  padding: 0.75rem 1.5rem;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s;
}

.btn-load-example:hover {
  transform: translateY(-2px);
}

@media (max-width: 768px) {
  .verify-header h1 {
    font-size: 2rem;
  }
  
  .subtitle {
    font-size: 1rem;
  }
  
  .steps-grid {
    grid-template-columns: 1fr;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
