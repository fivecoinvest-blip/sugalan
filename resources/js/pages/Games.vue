<template>
  <div class="games-page">
    <div class="container">
      <div class="page-header">
        <div>
          <h1 class="page-title">Games</h1>
          <p class="page-subtitle">All games are provably fair and verified</p>
        </div>
        <div class="search-bar">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Search games..."
            class="search-input"
          />
        </div>
      </div>

      <!-- Filters -->
      <div class="filters">
        <button 
          v-for="category in categories" 
          :key="category.value"
          @click="selectedCategory = category.value"
          class="filter-btn"
          :class="{ active: selectedCategory === category.value }"
        >
          {{ category.icon }} {{ category.label }}
        </button>
      </div>

      <!-- Games Grid -->
      <div class="games-grid">
        <router-link 
          v-for="game in filteredGames" 
          :key="game.id"
          :to="`/play/${game.id}`"
          class="game-card"
        >
          <div class="game-card-inner">
            <div class="game-icon">{{ game.icon }}</div>
            <h3 class="game-name">{{ game.name }}</h3>
            <p class="game-description">{{ game.description }}</p>
            
            <div class="game-meta">
              <div class="game-category">
                <span class="category-badge">{{ game.category }}</span>
              </div>
              <div class="game-rtp">
                <span class="rtp-label">RTP:</span>
                <span class="rtp-value">{{ game.rtp }}%</span>
              </div>
            </div>

            <div class="game-stats">
              <div class="stat-item">
                <span class="stat-label">Players</span>
                <span class="stat-value">{{ game.players }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Max Win</span>
                <span class="stat-value">{{ game.maxWin }}x</span>
              </div>
            </div>

            <div class="play-overlay">
              <span class="play-btn">Play Now</span>
            </div>
          </div>
        </router-link>
      </div>

      <!-- Empty State -->
      <div v-if="filteredGames.length === 0" class="empty-state">
        <div class="empty-icon">🎮</div>
        <p>No games found matching your search</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const searchQuery = ref('');
const selectedCategory = ref('all');

const categories = [
  { value: 'all', label: 'All Games', icon: '🎮' },
  { value: 'classic', label: 'Classic', icon: '🎲' },
  { value: 'multiplayer', label: 'Multiplayer', icon: '👥' },
  { value: 'strategy', label: 'Strategy', icon: '🧠' },
  { value: 'luck', label: 'Luck', icon: '🍀' },
];

const games = [
  {
    id: 'dice',
    name: 'Dice',
    icon: '🎲',
    description: 'Predict if the roll will be higher or lower than your chosen number',
    category: 'Classic',
    rtp: 99,
    players: 1247,
    maxWin: 990,
  },
  {
    id: 'crash',
    name: 'Crash',
    icon: '🚀',
    description: 'Cash out before the rocket crashes. Multiplayer excitement!',
    category: 'Multiplayer',
    rtp: 99,
    players: 3521,
    maxWin: 1000,
  },
  {
    id: 'mines',
    name: 'Mines',
    icon: '💣',
    description: 'Reveal tiles and avoid the mines. Cash out anytime!',
    category: 'Strategy',
    rtp: 99,
    players: 892,
    maxWin: 420,
  },
  {
    id: 'plinko',
    name: 'Plinko',
    icon: '🏀',
    description: 'Drop the ball and watch it bounce to a winning multiplier',
    category: 'Luck',
    rtp: 99,
    players: 1653,
    maxWin: 1000,
  },
  {
    id: 'hilo',
    name: 'Hi-Lo',
    icon: '🃏',
    description: 'Guess if the next card is higher or lower. Chain wins!',
    category: 'Classic',
    rtp: 99,
    players: 743,
    maxWin: 500,
  },
  {
    id: 'keno',
    name: 'Keno',
    icon: '🎱',
    description: 'Pick 10 numbers from 40 and match them to win big',
    category: 'Luck',
    rtp: 98,
    players: 521,
    maxWin: 100,
  },
  {
    id: 'wheel',
    name: 'Wheel',
    icon: '🎡',
    description: 'Spin the wheel of fortune for instant wins',
    category: 'Luck',
    rtp: 98,
    players: 1124,
    maxWin: 50,
  },
  {
    id: 'pump',
    name: 'Pump',
    icon: '💨',
    description: 'Cash out before the pump bursts. Multiplayer excitement!',
    category: 'Multiplayer',
    rtp: 99,
    players: 2341,
    maxWin: 50,
  },
];

const filteredGames = computed(() => {
  let filtered = games;

  // Filter by category
  if (selectedCategory.value !== 'all') {
    filtered = filtered.filter(
      game => game.category.toLowerCase() === selectedCategory.value
    );
  }

  // Filter by search
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      game =>
        game.name.toLowerCase().includes(query) ||
        game.description.toLowerCase().includes(query)
    );
  }

  return filtered;
});
</script>

<style scoped>
.games-page {
  min-height: 100vh;
  padding: 40px 20px;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  gap: 20px;
}

.page-title {
  font-size: 36px;
  font-weight: 800;
  margin-bottom: 8px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.page-subtitle {
  color: rgba(255, 255, 255, 0.6);
  font-size: 16px;
}

.search-bar {
  flex: 0 0 300px;
}

.search-input {
  width: 100%;
  padding: 12px 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  font-size: 14px;
}

.search-input:focus {
  outline: none;
  border-color: #667eea;
  background: rgba(255, 255, 255, 0.08);
}

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 30px;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 10px 20px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.7);
  cursor: pointer;
  transition: all 0.2s;
  font-weight: 600;
  font-size: 14px;
}

.filter-btn:hover {
  background: rgba(255, 255, 255, 0.08);
  color: white;
}

.filter-btn.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-color: transparent;
  color: white;
}

.games-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 24px;
}

.game-card {
  text-decoration: none;
  color: white;
  display: block;
}

.game-card-inner {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.game-card:hover .game-card-inner {
  transform: translateY(-4px);
  background: rgba(255, 255, 255, 0.08);
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
}

.game-card:hover .play-overlay {
  opacity: 1;
}

.game-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.game-name {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 8px;
}

.game-description {
  color: rgba(255, 255, 255, 0.6);
  font-size: 14px;
  line-height: 1.5;
  margin-bottom: 16px;
  flex: 1;
}

.game-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.category-badge {
  background: rgba(102, 126, 234, 0.2);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  color: #667eea;
}

.game-rtp {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
}

.rtp-label {
  color: rgba(255, 255, 255, 0.5);
}

.rtp-value {
  color: #48bb78;
  font-weight: 700;
}

.game-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-label {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.5);
  text-transform: uppercase;
}

.stat-value {
  font-size: 16px;
  font-weight: 700;
  color: white;
}

.play-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(102, 126, 234, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s;
}

.play-btn {
  padding: 16px 32px;
  background: white;
  color: #667eea;
  border-radius: 8px;
  font-weight: 700;
  font-size: 18px;
}

.empty-state {
  text-align: center;
  padding: 80px 20px;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.empty-state p {
  color: rgba(255, 255, 255, 0.6);
  font-size: 16px;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .search-bar {
    flex: 1;
    width: 100%;
  }

  .games-grid {
    grid-template-columns: 1fr;
  }
}
</style>
