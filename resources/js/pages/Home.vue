<template>
  <div class="home-page">
    <!-- Hero Section -->
    <section class="hero">
      <div class="hero-content">
        <h1 class="hero-title">Welcome to Sugalan</h1>
        <p class="hero-subtitle">The Most Trusted Online Casino in the Philippines</p>
        
        <div class="hero-actions">
          <button v-if="!isAuthenticated" @click="handleGetStarted" class="btn btn-primary btn-lg">
            Get Started
          </button>
          <router-link v-else to="/games" class="btn btn-primary btn-lg">
            Play Now
          </router-link>
          <router-link to="/games" class="btn btn-secondary btn-lg">
            View Games
          </router-link>
        </div>

        <div class="hero-stats">
          <div class="stat-item">
            <div class="stat-value">₱50M+</div>
            <div class="stat-label">Paid Out</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">10K+</div>
            <div class="stat-label">Players</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">7</div>
            <div class="stat-label">Games</div>
          </div>
        </div>
      </div>

      <div class="hero-visual">
        <div class="floating-emoji">🎰</div>
        <div class="floating-emoji">💰</div>
        <div class="floating-emoji">🎲</div>
      </div>
    </section>

    <!-- Featured Games -->
    <section class="featured-games">
      <div class="container">
        <h2 class="section-title">Featured Games</h2>
        <p class="section-subtitle">Try your luck with our provably fair games</p>

        <div class="games-grid">
          <router-link 
            v-for="game in featuredGames" 
            :key="game.id"
            :to="`/play/${game.id}`"
            class="game-card"
          >
            <div class="game-icon">{{ game.icon }}</div>
            <h3>{{ game.name }}</h3>
            <p>{{ game.description }}</p>
            <div class="game-footer">
              <span class="game-type">{{ game.type }}</span>
              <span class="game-rtp">{{ game.rtp }}% RTP</span>
            </div>
          </router-link>
        </div>

        <div class="view-all">
          <router-link to="/games" class="btn btn-outline">
            View All Games →
          </router-link>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="features">
      <div class="container">
        <h2 class="section-title">Why Choose Sugalan?</h2>
        
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">🔒</div>
            <h3>Secure & Safe</h3>
            <p>Industry-leading security with encrypted transactions and secure wallet storage</p>
          </div>
          
          <div class="feature-card">
            <div class="feature-icon">⚡</div>
            <h3>Instant Payouts</h3>
            <p>Quick withdrawal processing with GCash integration for seamless transfers</p>
          </div>
          
          <div class="feature-card">
            <div class="feature-icon">🎲</div>
            <h3>Provably Fair</h3>
            <p>All games use cryptographic verification ensuring transparent and fair gameplay</p>
          </div>
          
          <div class="feature-card">
            <div class="feature-icon">🎁</div>
            <h3>Generous Bonuses</h3>
            <p>Welcome bonuses, daily rewards, and VIP benefits for loyal players</p>
          </div>
          
          <div class="feature-card">
            <div class="feature-icon">📱</div>
            <h3>Multi-Platform</h3>
            <p>Login with phone, MetaMask, or Telegram - play anywhere, anytime</p>
          </div>
          
          <div class="feature-card">
            <div class="feature-icon">💎</div>
            <h3>VIP Rewards</h3>
            <p>Climb the VIP tiers for exclusive perks, higher limits, and special bonuses</p>
          </div>
        </div>
      </div>
    </section>

    <!-- VIP Preview -->
    <section class="vip-preview">
      <div class="container">
        <div class="vip-content">
          <div class="vip-left">
            <h2>Join the VIP Club</h2>
            <p>Unlock exclusive benefits and rewards as you play</p>
            
            <ul class="vip-benefits">
              <li>Higher betting limits</li>
              <li>Exclusive bonuses and promotions</li>
              <li>Priority customer support</li>
              <li>Birthday gifts and special rewards</li>
              <li>Dedicated VIP manager</li>
            </ul>
            
            <router-link to="/vip" class="btn btn-vip">
              Learn More About VIP →
            </router-link>
          </div>
          
          <div class="vip-right">
            <div class="vip-tiers">
              <div class="tier-badge bronze">Bronze</div>
              <div class="tier-badge silver">Silver</div>
              <div class="tier-badge gold">Gold</div>
              <div class="tier-badge platinum">Platinum</div>
              <div class="tier-badge diamond">Diamond</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container">
        <h2>Ready to Start Winning?</h2>
        <p>Join thousands of players and claim your welcome bonus today</p>
        
        <button v-if="!isAuthenticated" @click="handleGetStarted" class="btn btn-primary btn-lg">
          Create Account Now
        </button>
        <router-link v-else to="/deposit" class="btn btn-primary btn-lg">
          Make Your First Deposit
        </router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const isAuthenticated = computed(() => authStore.isAuthenticated);

const featuredGames = [
  {
    id: 'dice',
    name: 'Dice',
    icon: '🎲',
    description: 'Predict if the roll will be higher or lower',
    type: 'Classic',
    rtp: '99',
  },
  {
    id: 'crash',
    name: 'Crash',
    icon: '🚀',
    description: 'Cash out before the rocket crashes',
    type: 'Multiplayer',
    rtp: '99',
  },
  {
    id: 'mines',
    name: 'Mines',
    icon: '💣',
    description: 'Reveal tiles and avoid the mines',
    type: 'Strategy',
    rtp: '99',
  },
  {
    id: 'plinko',
    name: 'Plinko',
    icon: '🏀',
    description: 'Drop the ball and win big',
    type: 'Luck',
    rtp: '99',
  },
  {
    id: 'hilo',
    name: 'Hi-Lo',
    icon: '🃏',
    description: 'Guess if the next card is higher or lower',
    type: 'Classic',
    rtp: '99',
  },
  {
    id: 'wheel',
    name: 'Wheel',
    icon: '🎡',
    description: 'Spin the wheel of fortune',
    type: 'Luck',
    rtp: '98',
  },
];

function handleGetStarted() {
  authStore.showRegisterModal = true;
}
</script>

<style scoped>
.home-page {
  background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
  min-height: 100vh;
}

.hero {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  padding: 60px 20px;
}

.hero-content {
  text-align: center;
  z-index: 2;
  max-width: 800px;
}

.hero-title {
  font-size: 56px;
  font-weight: 800;
  margin-bottom: 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-subtitle {
  font-size: 24px;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 40px;
}

.hero-actions {
  display: flex;
  gap: 15px;
  justify-content: center;
  margin-bottom: 60px;
}

.hero-stats {
  display: flex;
  gap: 60px;
  justify-content: center;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 32px;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 8px;
}

.stat-label {
  color: rgba(255, 255, 255, 0.6);
  font-size: 14px;
}

.hero-visual {
  position: absolute;
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  pointer-events: none;
}

.floating-emoji {
  position: absolute;
  font-size: 120px;
  opacity: 0.1;
  animation: float 6s ease-in-out infinite;
}

.floating-emoji:nth-child(1) {
  top: 10%;
  left: 10%;
  animation-delay: 0s;
}

.floating-emoji:nth-child(2) {
  top: 60%;
  right: 10%;
  animation-delay: 2s;
}

.floating-emoji:nth-child(3) {
  bottom: 10%;
  left: 50%;
  animation-delay: 4s;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-30px);
  }
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

.section-title {
  font-size: 40px;
  font-weight: 700;
  text-align: center;
  margin-bottom: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.section-subtitle {
  text-align: center;
  color: rgba(255, 255, 255, 0.6);
  font-size: 18px;
  margin-bottom: 50px;
}

.featured-games {
  padding: 80px 0;
}

.games-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-bottom: 40px;
}

.game-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 30px;
  text-decoration: none;
  color: white;
  transition: all 0.3s;
  cursor: pointer;
}

.game-card:hover {
  transform: translateY(-8px);
  background: rgba(255, 255, 255, 0.08);
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
}

.game-icon {
  font-size: 64px;
  margin-bottom: 20px;
}

.game-card h3 {
  font-size: 24px;
  margin-bottom: 10px;
}

.game-card p {
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 20px;
  line-height: 1.5;
}

.game-footer {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.game-type {
  background: rgba(102, 126, 234, 0.2);
  padding: 4px 12px;
  border-radius: 12px;
  color: #667eea;
}

.game-rtp {
  color: #48bb78;
  font-weight: 600;
}

.view-all {
  text-align: center;
}

.features {
  padding: 80px 0;
  background: rgba(255, 255, 255, 0.02);
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 30px;
}

.feature-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 30px;
  text-align: center;
  transition: all 0.3s;
}

.feature-card:hover {
  transform: translateY(-4px);
  background: rgba(255, 255, 255, 0.08);
}

.feature-icon {
  font-size: 48px;
  margin-bottom: 20px;
}

.feature-card h3 {
  font-size: 20px;
  margin-bottom: 12px;
}

.feature-card p {
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.6;
}

.vip-preview {
  padding: 80px 0;
}

.vip-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  align-items: center;
}

.vip-left h2 {
  font-size: 36px;
  margin-bottom: 16px;
  background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.vip-left p {
  color: rgba(255, 255, 255, 0.7);
  font-size: 18px;
  margin-bottom: 30px;
}

.vip-benefits {
  list-style: none;
  padding: 0;
  margin-bottom: 30px;
}

.vip-benefits li {
  padding: 12px 0;
  color: rgba(255, 255, 255, 0.8);
  font-size: 16px;
}

.vip-benefits li:before {
  content: '✓';
  color: #48bb78;
  font-weight: bold;
  margin-right: 12px;
}

.vip-tiers {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tier-badge {
  padding: 20px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 20px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.tier-badge.bronze {
  background: linear-gradient(135deg, #cd7f32 0%, #a0522d 100%);
}

.tier-badge.silver {
  background: linear-gradient(135deg, #c0c0c0 0%, #808080 100%);
}

.tier-badge.gold {
  background: linear-gradient(135deg, #ffd700 0%, #ffb700 100%);
}

.tier-badge.platinum {
  background: linear-gradient(135deg, #e5e4e2 0%, #a8a8a8 100%);
}

.tier-badge.diamond {
  background: linear-gradient(135deg, #b9f2ff 0%, #00d4ff 100%);
}

.cta-section {
  padding: 80px 0;
  text-align: center;
  background: rgba(102, 126, 234, 0.05);
}

.cta-section h2 {
  font-size: 40px;
  margin-bottom: 16px;
}

.cta-section p {
  color: rgba(255, 255, 255, 0.7);
  font-size: 18px;
  margin-bottom: 30px;
}

.btn {
  padding: 14px 28px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-size: 16px;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.15);
}

.btn-outline {
  background: transparent;
  color: #667eea;
  border: 2px solid #667eea;
}

.btn-outline:hover {
  background: #667eea;
  color: white;
}

.btn-vip {
  background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
  color: #1a1a2e;
  font-weight: 700;
}

.btn-vip:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(246, 211, 101, 0.4);
}

.btn-lg {
  padding: 16px 40px;
  font-size: 18px;
}

@media (max-width: 768px) {
  .hero-title {
    font-size: 36px;
  }

  .hero-subtitle {
    font-size: 18px;
  }

  .hero-actions {
    flex-direction: column;
  }

  .hero-stats {
    gap: 30px;
  }

  .vip-content {
    grid-template-columns: 1fr;
  }

  .section-title {
    font-size: 32px;
  }
}
</style>
