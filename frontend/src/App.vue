<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from './services/api';
import { ArrowRight, Banknote, BarChart3, CarFront, CheckCircle2, ChevronRight, CircleDollarSign, Clock3, CreditCard, Heart, House, Menu, Plus, ShieldCheck, ShoppingCart, Sparkles, Target, Ticket, Trash2, Trophy, UserRound, WalletCards, X } from 'lucide-vue-next';

type View = 'home' | 'games' | 'pools' | 'login' | 'admin' | 'profile';
type Game = { id: number; slug: string; name: string; short_name: string; price_cents: number; color: string; range_max: number; number_min?: number; numbers_required: number; selection_mode?: string; special_options?: { columns?: number }; next_draw?: { id?: number; contest_number: number; draw_at: string } };
type User = { id: number; name: string; email: string; portal: 'admin' | 'cliente' };
type CartTicket = { id: string; game: Game; draw_id?: number; numbers: number[]; amount_cents: number; kind?: 'game' | 'pool'; pool_id?: number; shares?: number };
type PoolCard = { id: number; slug: string; game: string; title: string; shares: string; price: number; draw_id?: number; color: string };

const view = ref<View>('home');
const mobileOpen = ref(false);
const isLogin = ref(false);
const isRegister = ref(false);
const loginPortal = ref<'cliente' | 'admin'>('cliente');
const customerName = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const user = ref<User | null>(null);
const catalog = ref<Game[]>([]);
const selectedGame = ref<Game | null>(null);
const selectedNumbers = ref<number[]>([]);
const selectedFilter = ref('Todos');
const toast = ref('');
const loginError = ref('');
const loading = ref(false);
const adminData = ref<any>(null);
const cartOpen = ref(false);
const cart = ref<CartTicket[]>([]);
const accountLoading = ref(false);
const paymentMethod = ref<'card' | 'pix'>('card');
const checkoutFeedback = ref('');

const demoCatalog: Game[] = [
  { id: 1, slug: 'mega-sena', name: 'Mega-Sena', short_name: 'MEGA', price_cents: 500, color: '#31b8b2', range_max: 60, numbers_required: 6, next_draw: { contest_number: 2910, draw_at: '2026-08-08T20:00:00-03:00' } },
  { id: 2, slug: 'lotofacil', name: 'Lotofácil', short_name: 'FÁCIL', price_cents: 350, color: '#8c5be5', range_max: 25, numbers_required: 15, next_draw: { contest_number: 3480, draw_at: '2026-08-07T20:00:00-03:00' } },
  { id: 3, slug: 'quina', name: 'Quina', short_name: 'QUINA', price_cents: 300, color: '#ef9151', range_max: 80, numbers_required: 5, next_draw: { contest_number: 6820, draw_at: '2026-08-07T20:00:00-03:00' } },
  { id: 4, slug: 'timemania', name: 'Timemania', short_name: 'TIME', price_cents: 350, color: '#f05295', range_max: 80, numbers_required: 10, next_draw: { contest_number: 2260, draw_at: '2026-08-09T20:00:00-03:00' } },
  { id: 5, slug: 'dia-de-sorte', name: 'Dia de Sorte', short_name: 'DIA', price_cents: 250, color: '#f1b833', range_max: 31, numbers_required: 7, next_draw: { contest_number: 1080, draw_at: '2026-08-09T20:00:00-03:00' } },
  { id: 6, slug: 'dupla-sena', name: 'Dupla Sena', short_name: 'DUPLA', price_cents: 300, color: '#3d8de5', range_max: 50, numbers_required: 6, next_draw: { contest_number: 2860, draw_at: '2026-08-08T20:00:00-03:00' } },
  { id: 7, slug: 'lotomania', name: 'Lotomania', short_name: 'LOTO', price_cents: 300, color: '#e061b7', number_min: 0, range_max: 99, numbers_required: 20, next_draw: { contest_number: 2800, draw_at: '2026-08-08T20:00:00-03:00' } },
  { id: 8, slug: 'super-sete', name: 'Super Sete', short_name: '7', price_cents: 300, color: '#41a86d', number_min: 0, range_max: 9, numbers_required: 7, selection_mode: 'columns', special_options: { columns: 7 }, next_draw: { contest_number: 730, draw_at: '2026-08-08T20:00:00-03:00' } },
];

const gameGroups = ['Todos', 'Mais jogados', 'Menor preço', 'Bolões'];
const gamesToShow = computed(() => {
  if (selectedFilter.value === 'Mais jogados') return catalog.value.slice(0, 4);
  if (selectedFilter.value === 'Menor preço') return [...catalog.value].sort((a, b) => a.price_cents - b.price_cents);
  return catalog.value;
});
const numberMin = computed(() => selectedGame.value?.number_min ?? 1);
const numbers = computed(() => Array.from({ length: (selectedGame.value?.range_max ?? 60) - numberMin.value + 1 }, (_, i) => i + numberMin.value));
const columns = computed(() => Array.from({ length: selectedGame.value?.special_options?.columns ?? 7 }, (_, i) => i));
const amount = computed(() => selectedGame.value ? selectedGame.value.price_cents / 100 : 0);
const formattedAmount = computed(() => amount.value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
const canBet = computed(() => selectedGame.value && selectedNumbers.value.length >= selectedGame.value.numbers_required);
const cartCount = computed(() => cart.value.length);
const cartTotal = computed(() => cart.value.reduce((sum, ticket) => sum + ticket.amount_cents, 0));
const cartTotalLabel = computed(() => money(cartTotal.value));

const chartOptions = {
  chart: { toolbar: { show: false }, fontFamily: 'DM Sans, sans-serif', zoom: { enabled: false } },
  stroke: { curve: 'smooth', width: 3 },
  colors: ['#5c2db8', '#f64c9d'],
  dataLabels: { enabled: false },
  xaxis: { categories: ['01/08', '02/08', '03/08', '04/08', '05/08', '06/08', '07/08'], labels: { style: { colors: '#8a809d' } } },
  yaxis: { labels: { formatter: (value: number) => `R$ ${(value / 1000).toFixed(0)}k` } },
  grid: { borderColor: '#eee8f6' },
  legend: { position: 'top' as const, horizontalAlign: 'right' as const },
  tooltip: { y: { formatter: (value: number) => `R$ ${value.toLocaleString('pt-BR')}` } },
};
const fallbackChart = [{ name: 'Apostado', data: [12400, 15800, 14200, 19400, 22700, 24600, 30200] }, { name: 'Prêmios', data: [6200, 7800, 6900, 8200, 9700, 10300, 12400] }];

function money(cents: number) { return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
function shortDate(value?: string) { return value ? new Date(value).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : 'próximo sorteio'; }
function ticketSubtitle(ticket: CartTicket) { return `${ticket.kind === 'pool' ? '1 cota' : `Concurso ${ticket.game.next_draw?.contest_number ?? '—'}`} · ${ticket.numbers.map(number => String(number).padStart(2, '0')).join(' · ')}`; }
function notify(message: string) { toast.value = message; window.setTimeout(() => { toast.value = ''; }, 3600); }
function persistCart() { localStorage.setItem('lottery_cart', JSON.stringify(cart.value)); }
function navigate(next: View) { view.value = next; mobileOpen.value = false; if (next === 'admin') loadAdmin(); }
function gameIcon(game: Game) { return game.slug === 'mega-sena' ? Sparkles : game.slug === 'lotofacil' ? Ticket : game.slug === 'quina' ? CircleDollarSign : game.slug === 'timemania' ? Trophy : game.slug === 'dia-de-sorte' ? Banknote : game.slug === 'dupla-sena' ? WalletCards : game.slug === 'lotomania' ? Target : ShieldCheck; }

async function loadCatalog() {
  try {
    const response = await api<{ data?: Game[] }>('/api/v1/catalog');
    catalog.value = Array.isArray(response.data) && response.data.length ? response.data : demoCatalog;
  }
  catch { catalog.value = demoCatalog; }
}
function openLogin(portal: 'cliente' | 'admin' = 'cliente') { loginPortal.value = portal; isRegister.value = false; loginError.value = ''; isLogin.value = true; view.value = 'login'; mobileOpen.value = false; }
function openRegister() { loginPortal.value = 'cliente'; isRegister.value = true; loginError.value = ''; isLogin.value = true; view.value = 'login'; mobileOpen.value = false; }
function logout() { localStorage.removeItem('lottery_token'); user.value = null; navigate('home'); notify('Você saiu da sua conta.'); }
async function submitLogin() {
  loading.value = true; loginError.value = '';
  try {
    const response = await api<{ data: { access_token: string; profile: User } }>('/api/auth/login', { method: 'POST', body: JSON.stringify({ email: email.value, password: password.value, portal: loginPortal.value }) });
    localStorage.setItem('lottery_token', response.data.access_token); user.value = response.data.profile; isLogin.value = false; navigate(loginPortal.value === 'admin' ? 'admin' : 'games'); notify(cartCount.value ? 'Acesso autorizado. Seu carrinho foi preservado.' : 'Acesso autorizado.');
  } catch {
    const demoEmail = loginPortal.value === 'admin' ? 'admin@loterias.online' : 'cliente@loterias.online';
    if (email.value === demoEmail && password.value === 'Loterias@2026!') {
      user.value = { id: loginPortal.value === 'admin' ? 1 : 2, name: loginPortal.value === 'admin' ? 'Admin Loterias Online' : 'Cliente Demo', email: email.value, portal: loginPortal.value };
      isLogin.value = false; navigate(loginPortal.value === 'admin' ? 'admin' : 'games'); notify(cartCount.value ? 'Modo demonstração ativado. Seu carrinho foi preservado.' : 'Modo demonstração ativado.');
    } else loginError.value = 'Confira seu e-mail, senha e perfil de acesso.';
  } finally { loading.value = false; }
}
async function submitRegister() {
  loading.value = true; loginError.value = '';
  try {
    const response = await api<{ data: { access_token: string; profile: User } }>('/api/auth/register', { method: 'POST', body: JSON.stringify({ name: customerName.value, email: email.value, password: password.value, password_confirmation: passwordConfirmation.value }) });
    localStorage.setItem('lottery_token', response.data.access_token); user.value = response.data.profile; isLogin.value = false; navigate('games'); notify('Cadastro criado. Seu carrinho foi preservado.');
  } catch (error) { loginError.value = error instanceof Error ? error.message : 'Não foi possível criar seu cadastro.'; }
  finally { loading.value = false; }
}
function chooseGame(game: Game) { selectedGame.value = game; selectedNumbers.value = game.selection_mode === 'columns' ? Array.from({ length: game.special_options?.columns ?? 7 }, () => 0) : []; navigate('games'); }
function secureRandom(max: number) {
  const values = new Uint32Array(1);
  crypto.getRandomValues(values);
  return values[0] % (max + 1);
}
function localCoupon(game: Game) {
  if (game.selection_mode === 'columns') return Array.from({ length: game.special_options?.columns ?? 7 }, () => secureRandom(game.range_max));
  const pool = Array.from({ length: game.range_max - (game.number_min ?? 1) + 1 }, (_, i) => i + (game.number_min ?? 1));
  for (let i = pool.length - 1; i > 0; i--) { const j = secureRandom(i); [pool[i], pool[j]] = [pool[j], pool[i]]; }
  return pool.slice(0, game.numbers_required).sort((a, b) => a - b);
}
async function generateCoupon() {
  if (!selectedGame.value) return;
  try {
    if (user.value) {
      const response = await api<{ data: Array<{ numbers: number[] }> }>('/api/v1/coupons/generate', { method: 'POST', body: JSON.stringify({ game_id: selectedGame.value.id, quantity: 1 }) });
      selectedNumbers.value = response.data[0].numbers;
    } else selectedNumbers.value = localCoupon(selectedGame.value);
    notify('Cupom gerado com seleção aleatória segura.');
  } catch { selectedNumbers.value = localCoupon(selectedGame.value); notify('Cupom demonstrativo gerado.'); }
}
function setColumn(index: number, event: Event) {
  selectedNumbers.value[index] = Number((event.target as HTMLSelectElement).value);
}
function toggleNumber(number: number) {
  if (!selectedGame.value) return;
  if (selectedGame.value.selection_mode === 'columns') return;
  const index = selectedNumbers.value.indexOf(number);
  if (index >= 0) selectedNumbers.value.splice(index, 1);
  else if (selectedNumbers.value.length < selectedGame.value.numbers_required) selectedNumbers.value.push(number);
}
function addCurrentTicket() {
  if (!selectedGame.value || !canBet.value) return notify(`Escolha ${selectedGame.value?.numbers_required ?? 6} números para continuar.`);
  if (!user.value) { notify('Entre ou crie sua conta para guardar o cupom no carrinho.'); return openLogin(); }
  if (!selectedGame.value.next_draw?.id) return notify('Concurso indisponível no momento. Atualize o catálogo para continuar.');
  cart.value.push({ id: `${selectedGame.value.id}-${Date.now()}-${Math.random().toString(36).slice(2)}`, game: selectedGame.value, draw_id: selectedGame.value.next_draw.id, numbers: [...selectedNumbers.value], amount_cents: selectedGame.value.price_cents, kind: 'game' });
  persistCart(); cartOpen.value = true; notify(`${selectedGame.value.name} adicionado ao carrinho.`);
}
function removeCartItem(id: string) { cart.value = cart.value.filter(ticket => ticket.id !== id); persistCart(); }
function clearCart() { cart.value = []; persistCart(); }
function addPoolToCart(pool: PoolCard) {
  const game = catalog.value.find(item => item.slug === pool.slug);
  if (!user.value) { notify('Entre ou crie sua conta para guardar a cota no carrinho.'); return openLogin(); }
  if (!game || !game.next_draw?.id) return notify('Concurso do bolão indisponível no momento.');
  cart.value.push({ id: `pool-${pool.id}-${Date.now()}`, game, draw_id: pool.draw_id ?? game.next_draw.id, numbers: localCoupon(game), amount_cents: Math.round(pool.price * 100), kind: 'pool', pool_id: pool.id, shares: 1 });
  persistCart(); cartOpen.value = true; notify(`${pool.title} adicionado ao carrinho.`);
}
async function checkoutCart() {
  if (!cartCount.value) return notify('Seu carrinho está vazio.');
  if (!user.value) { notify('Entre ou crie sua conta para continuar o pagamento.'); return openLogin(); }
  if (cart.value.some(ticket => !ticket.draw_id)) return notify('Atualize o catálogo para renovar os concursos do carrinho.');
  checkoutFeedback.value = ''; loading.value = true;
  try {
    const stableCartKey = `cart-${user.value.id}-${cart.value.map(ticket => ticket.id).join('-')}-${paymentMethod.value}`;
    const checkout = await api<{ data: { checkout_url?: string; mode?: string } }>('/api/v1/orders/checkout', { method: 'POST', headers: { 'Idempotency-Key': stableCartKey }, body: JSON.stringify({ tickets: cart.value.map(ticket => ({ game_id: ticket.game.id, draw_id: ticket.draw_id, numbers: ticket.numbers, pool_id: ticket.pool_id, shares: ticket.shares })), method: paymentMethod.value }) });
    if (checkout.data.checkout_url) window.location.href = checkout.data.checkout_url;
    else notify(checkout.data.mode === 'stripe_not_configured' ? 'Pedido criado, mas o Stripe ainda não está configurado neste ambiente.' : 'Pedido criado e aguardando pagamento.');
    if (checkout.data.checkout_url) { clearCart(); cartOpen.value = false; }
  } catch (error) {
    const status = error instanceof Error ? (error as Error & { status?: number }).status : undefined;
    if (status === 401) {
      localStorage.removeItem('lottery_token'); user.value = null;
      checkoutFeedback.value = 'Sua sessão expirou. Entre novamente para continuar com o carrinho preservado.';
      notify('Sua sessão expirou. Entre novamente para pagar.');
      openLogin();
    } else {
      const message = error instanceof Error ? error.message : 'Não foi possível iniciar o pagamento.';
      checkoutFeedback.value = message; notify(message);
    }
  }
  finally { loading.value = false; }
}
function submitBet() { addCurrentTicket(); }
async function openBillingPortal() {
  if (!user.value) return openLogin();
  accountLoading.value = true;
  try { const response = await api<{ data: { url: string } }>('/api/v1/profile/billing-portal', { method: 'POST' }); if (response.data.url) window.location.href = response.data.url; }
  catch (error) { notify(error instanceof Error ? error.message : 'Não foi possível abrir o portal de pagamentos.'); }
  finally { accountLoading.value = false; }
}
async function loadAdmin() {
  try { const response = await api<{ data: any }>('/api/v1/admin/dashboard'); adminData.value = response.data; }
  catch { adminData.value = { kpis: { revenue_cents: 3020000, payout_cents: 1240000, margin_cents: 1780000, active_bets: 1842 }, chart: fallbackChart, bets: [{ id: '#LO-10294', player: 'Mariana Costa', game: 'Mega-Sena', amount_cents: 500, status: 'paid' }, { id: '#LO-10293', player: 'Rafael Lima', game: 'Lotofácil', amount_cents: 350, status: 'won' }, { id: '#LO-10292', player: 'João Pedro', game: 'Quina', amount_cents: 300, status: 'pending' }] }; }
}
function showAdmin() { if (user.value?.portal === 'admin') navigate('admin'); else openLogin('admin'); }
onMounted(async () => { await loadCatalog(); try { const saved = JSON.parse(localStorage.getItem('lottery_cart') || '[]'); if (Array.isArray(saved)) cart.value = saved; } catch { cart.value = []; } if (localStorage.getItem('lottery_token')) { try { const response = await api<{ data: User }>('/api/v1/me'); user.value = response.data; } catch { localStorage.removeItem('lottery_token'); } } });
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <div class="topbar-inner">
        <button class="brand" @click="navigate('home')"><span class="brand-mark">✦</span> Loterias Online</button>
        <nav class="nav">
          <button :class="{ active: view === 'home' }" @click="navigate('home')">Início</button>
          <button :class="{ active: view === 'games' }" @click="navigate('games')">Jogos</button>
          <button :class="{ active: view === 'pools' }" @click="navigate('pools')">Bolões</button>
          <button @click="notify('Resultados sincronizados com a Caixa após publicação oficial.')">Resultados</button>
        </nav>
        <div class="top-actions">
          <button v-if="user?.portal === 'admin'" class="btn btn-ghost btn-small" @click="showAdmin()"><BarChart3 :size="15" /> Admin</button>
          <button v-if="user" class="btn btn-ghost btn-small account-button" @click="navigate('profile')"><UserRound :size="15" /> {{ user.name.split(' ')[0] }}</button>
          <button v-if="user" class="btn btn-yellow btn-small" @click="logout()">Sair</button>
          <button v-else class="btn btn-ghost btn-small" @click="openLogin()">Entrar</button>
          <button class="cart-button" @click="cartOpen = true"><ShoppingCart :size="17" /><span>Carrinho</span><b v-if="cartCount">{{ cartCount }}</b></button>
          <button class="mobile-menu btn btn-ghost btn-small" @click="mobileOpen = !mobileOpen"><Menu v-if="!mobileOpen" :size="18" /><X v-else :size="18" /></button>
        </div>
      </div>
      <div v-if="mobileOpen" class="nav mobile-nav"><button @click="navigate('home')">Início</button><button @click="navigate('games')">Jogos</button><button @click="navigate('pools')">Bolões</button><button v-if="user" @click="navigate('profile')">Minha conta</button></div>
    </header>

    <main v-if="view === 'home'" class="main">
      <section class="hero">
        <div class="hero-copy"><div class="eyebrow">Prêmio acumulado em destaque</div><h1>R$ 100 milhões podem mudar o seu próximo capítulo.</h1><p>Escolha seus números, participe de bolões inteligentes e acompanhe tudo em um só lugar — com transparência em cada etapa.</p><button class="btn btn-yellow" @click="navigate('games')">Escolher meu jogo <ArrowRight :size="16" /></button><small class="hero-note">*Campanha visual demonstrativa. Confira o valor oficial do concurso antes de apostar.</small></div>
        <div class="hero-badge"><div><strong>R$ 100 mi</strong>em destaque*</div></div>
      </section>
      <div class="section-head"><div><h2>Escolha sua sorte</h2><p>Jogos oficiais, simples de apostar e fáceis de acompanhar.</p></div><button class="link" @click="navigate('games')">Ver todos <ChevronRight :size="14" /></button></div>
      <section class="games"><article v-for="game in catalog.slice(0, 4)" :key="game.id" class="game-card" :style="{ '--game-color': game.color }" @click="chooseGame(game)"><div class="game-top"><div class="game-logo"><component :is="gameIcon(game)" :size="21" /></div><button class="favorite" @click.stop="notify('Jogo salvo nos favoritos.')"><Heart :size="15" /></button></div><h3>{{ game.name }}</h3><div class="sub">Concurso {{ game.next_draw?.contest_number ?? '—' }} · {{ shortDate(game.next_draw?.draw_at) }}</div><div class="game-bottom"><div class="game-price">{{ money(game.price_cents) }}</div><div class="game-draw">aposta mínima<br /><strong>prêmio estimado</strong></div></div></article></section>
      <div class="section-head dream-heading"><div><div class="eyebrow" style="color:var(--purple)">Imagine o seu próximo capítulo</div><h2>O que você faria com um prêmio?</h2><p>Inspiração para jogar com responsabilidade — sem promessa de ganho.</p></div></div>
      <section class="dream-grid"><article class="dream-card dream-house"><House :size="27" /><strong>Uma casa nova</strong><span>mais espaço para viver seus planos</span></article><article class="dream-card dream-car"><CarFront :size="27" /><strong>O carro dos sonhos</strong><span>liberdade para ir mais longe</span></article><article class="dream-card dream-money"><Banknote :size="27" /><strong>Dinheiro organizado</strong><span>tranquilidade para o futuro</span></article><article class="dream-card dream-project"><WalletCards :size="27" /><strong>Seu grande projeto</strong><span>um começo para novas histórias</span></article></section>
      <section class="feature-row"><article class="feature"><div class="feature-icon"><ShieldCheck :size="20" /></div><strong>Jogue com segurança</strong><p>Pagamentos protegidos e acompanhamento claro do status de cada aposta.</p></article><article class="feature"><div class="feature-icon"><Ticket :size="20" /></div><strong>Bolões que cabem no bolso</strong><p>Mais combinações, mais diversão e participação fácil de acompanhar.</p></article><article class="feature"><div class="feature-icon"><Clock3 :size="20" /></div><strong>Resultado sem ansiedade</strong><p>Assim que a Caixa publica, a conferência acontece automaticamente.</p></article></section>
      <div class="section-head"><div><h2>Histórias que inspiram</h2><p>Conteúdo demonstrativo para a experiência da plataforma.</p></div></div>
      <section class="testimonials"><article v-for="(quote, index) in [{ name: 'Camila R.', month: 'Junho · demonstração', text: 'O fluxo é leve e eu consigo conferir todas as minhas apostas sem perder o horário do sorteio.' }, { name: 'Bruno M.', month: 'Maio · demonstração', text: 'Entrei em um bolão e gostei de ver as cotas, o valor e o status em uma tela só.' }, { name: 'Lívia S.', month: 'Abril · demonstração', text: 'A experiência é simples até para escolher os números e finalizar o pedido.' }]" :key="quote.name" class="quote"><p>“{{ quote.text }}”</p><div class="quote-foot"><div class="avatar">{{ quote.name[0] }}</div><div><strong>{{ quote.name }}</strong><small>{{ quote.month }}</small></div></div></article></section>
      <p class="notice" style="margin-top:18px">*Valores e depoimentos exibidos nesta versão são ilustrativos para demonstração do produto e não representam promessa de prêmio ou ganho real.</p>
    </main>

    <main v-else-if="view === 'games'" class="main">
      <div class="section-head"><div><div class="eyebrow" style="color:var(--purple)">Jogos oficiais</div><h2>Monte sua aposta</h2><p>Escolha a modalidade, gere uma Surpresinha ou marque seus números.</p></div><button class="btn btn-primary btn-small" @click="navigate('pools')"><Trophy :size="15" /> Ver bolões</button></div>
      <div class="filters"><button v-for="group in gameGroups" :key="group" class="chip" :class="{ active: selectedFilter === group }" @click="group === 'Bolões' ? navigate('pools') : selectedFilter = group">{{ group }}</button></div>
      <div class="page-grid">
        <section class="panel"><div class="games game-catalog-grid"><article v-for="game in gamesToShow" :key="game.id" class="game-card game-card-large" :style="{ '--game-color': game.color }" @click="chooseGame(game)"><div class="game-top"><div class="game-logo"><component :is="gameIcon(game)" :size="21" /></div><span class="status success">ativo</span></div><h3>{{ game.name }}</h3><div class="sub">{{ game.numbers_required }} números · faixa {{ game.number_min ?? 1 }}–{{ game.range_max }}</div><div class="game-bottom"><div class="game-price">{{ money(game.price_cents) }}</div><div class="game-draw">Concurso<br /><strong>{{ game.next_draw?.contest_number ?? '—' }}</strong></div></div></article></div></section>
        <aside class="panel summary"><div class="panel-title"><div><span class="summary-kicker">Cupom digital</span><h2>{{ selectedGame ? selectedGame.name : 'Sua aposta' }}</h2></div><Sparkles :size="20" color="#ffc94e" /></div>
          <template v-if="selectedGame">
            <p style="color:#ded2f9;font-size:13px;line-height:1.5">{{ selectedGame.selection_mode === 'columns' ? 'Escolha um número em cada coluna.' : `Marque ${selectedGame.numbers_required} números.` }} Você selecionou <strong style="color:white">{{ selectedNumbers.length }}</strong>.</p>
            <div v-if="selectedGame.selection_mode === 'columns'" class="column-picks"><label v-for="column in columns" :key="column">Coluna {{ column + 1 }}<select v-model.number="selectedNumbers[column]"><option v-for="number in numbers" :key="number" :value="number">{{ String(number).padStart(2, '0') }}</option></select></label></div>
            <div v-else class="number-grid" style="margin-top:18px"><button v-for="number in numbers" :key="number" class="number" :class="{ selected: selectedNumbers.includes(number) }" @click="toggleNumber(number)">{{ String(number).padStart(2, '0') }}</button></div>
            <div class="summary-total"><span>Aposta mínima</span><strong>{{ formattedAmount }}</strong></div>
            <div class="summary-actions"><button class="btn btn-ghost" :disabled="loading" @click="generateCoupon"><Sparkles :size="15" /> Surpresinha</button><button class="btn btn-yellow" :disabled="loading" @click="addCurrentTicket"><Plus :size="15" /> Adicionar</button></div>
            <button class="cart-callout" @click="cartOpen = true"><ShoppingCart :size="17" /><span>{{ cartCount ? `${cartCount} item(ns) no carrinho` : 'Ver carrinho e pagar' }}</span><ChevronRight :size="16" /></button>
            <div class="notice">Você pode gerar e conferir o cupom sem entrar. Para guardar no carrinho, pagar e receber a confirmação por e-mail, entre ou crie sua conta.</div>
          </template><div v-else class="empty" style="color:#ded2f9">Escolha um jogo ao lado para começar.</div>
        </aside>
      </div>
    </main>

    <main v-else-if="view === 'pools'" class="main"><div class="section-head"><div><div class="eyebrow" style="color:var(--purple)">Mais combinações</div><h2>Bolões em destaque</h2><p>Escolha uma cota, adicione ao carrinho e confira tudo em um só pagamento.</p></div><button class="btn btn-primary" @click="navigate('games')">Fazer aposta simples</button></div><section class="pool-grid"><article v-for="pool in [{ id: 1, slug:'mega-sena', game: 'Mega-Sena', title: 'Milionário da Semana', shares: '87/100 cotas', price: 12.5, color:'#31b8b2' }, { id: 2, slug:'lotofacil', game:'Lotofácil', title:'Fácil Premiado', shares:'132/200 cotas', price:7.9, color:'#8c5be5' }, { id: 3, slug:'quina', game:'Quina', title:'Quina Turbo', shares:'42/80 cotas', price:9.5, color:'#ef9151' }]" :key="pool.title" class="pool-card game-card" :style="{ '--game-color': pool.color }" @click="addPoolToCart(pool)"><div class="game-top"><div class="game-logo"><Trophy :size="19" /></div><span class="status success">aberto</span></div><div class="pool-pill">{{ pool.game }}</div><h3>{{ pool.title }}</h3><div class="sub">{{ pool.shares }} disponíveis</div><div class="game-bottom"><div class="game-price">{{ pool.price.toLocaleString('pt-BR',{style:'currency',currency:'BRL'}) }}<small style="font-size:11px;color:var(--muted)"> / cota</small></div><button class="btn btn-primary btn-small"><Plus :size="14" /> Cota</button></div></article></section><div class="panel pool-how"><CircleDollarSign color="#5c2db8" /><div><strong>Como funciona</strong><p>Você compra uma ou mais cotas, nós registramos as combinações do bolão e o resultado é dividido conforme as cotas confirmadas. A cota só fica confirmada após o pagamento aprovado.</p></div></div></main>

    <main v-else-if="view === 'admin'" class="main"><div class="admin-header"><div><div class="eyebrow" style="color:var(--purple)">Visão administrativa</div><h1>Operação da sorte</h1><p>Controle financeiro, exposição e liquidação dos concursos.</p></div><button class="btn btn-primary" @click="notify('Sincronização de resultados adicionada à fila.')"><Clock3 :size="16" /> Sincronizar resultados</button></div><section class="kpis"><article v-for="item in [{ label:'Apostado no período', value:money(adminData?.kpis?.revenue_cents ?? 0), icon:WalletCards, change:'+12,8%' },{label:'Prêmios provisionados',value:money(adminData?.kpis?.payout_cents ?? 0),icon:Trophy,change:'sob controle'},{label:'Margem operacional',value:money(adminData?.kpis?.margin_cents ?? 0),icon:BarChart3,change:'+8,4%'},{label:'Apostas ativas',value:(adminData?.kpis?.active_bets ?? 0).toLocaleString('pt-BR'),icon:Ticket,change:'últimos 30 dias'}]" :key="item.label" class="kpi"><div class="kpi-top"><span>{{ item.label }}</span><component :is="item.icon" :size="18" color="#5c2db8" /></div><strong>{{ item.value }}</strong><small>{{ item.change }}</small></article></section><section class="admin-grid"><article class="panel chart"><div class="panel-title"><div><h2>Volume x prêmios</h2><p style="color:var(--muted);font-size:12px;margin-top:4px">Acompanhamento diário</p></div><select class="chip"><option>Últimos 7 dias</option><option>Últimos 30 dias</option></select></div><apexchart type="line" height="255" :options="chartOptions" :series="adminData?.chart ?? fallbackChart" /></article><article class="panel"><div class="panel-title"><h2>Exposição por jogo</h2><ShieldCheck :size="19" color="#179980" /></div><div v-for="row in [{name:'Mega-Sena',exposure:'R$ 48.200',limit:'R$ 80.000',percent:60,color:'#31b8b2'},{name:'Lotofácil',exposure:'R$ 22.700',limit:'R$ 35.000',percent:65,color:'#8c5be5'},{name:'Quina',exposure:'R$ 12.400',limit:'R$ 25.000',percent:49,color:'#ef9151'},{name:'Demais jogos',exposure:'R$ 7.800',limit:'R$ 18.000',percent:43,color:'#f64c9d'}]" :key="row.name" style="margin-bottom:19px"><div style="display:flex;justify-content:space-between;font-size:13px"><strong>{{ row.name }}</strong><span style="color:var(--muted)">{{ row.exposure }} / {{ row.limit }}</span></div><div style="height:8px;border-radius:8px;background:#eee8f6;margin-top:9px;overflow:hidden"><div :style="{width:row.percent+'%',background:row.color,height:'100%',borderRadius:'8px'}"></div></div></div><div class="notice">Limite global configurável por concurso. Novas apostas podem ser pausadas automaticamente quando a exposição exceder a reserva disponível.</div></article></section><section class="panel" style="margin-top:17px"><div class="panel-title"><h2>Apostas recentes</h2><button class="link" @click="notify('Filtros avançados em breve.')">Ver todas <ChevronRight :size="14" /></button></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Cliente</th><th>Jogo</th><th>Valor</th><th>Status</th></tr></thead><tbody><tr v-for="bet in adminData?.bets ?? []" :key="bet.id"><td><strong>{{ bet.id }}</strong></td><td>{{ bet.player }}</td><td>{{ bet.game }}</td><td>{{ money(bet.amount_cents) }}</td><td><span class="status" :class="bet.status === 'won' ? 'success' : bet.status === 'pending' ? 'pending' : 'success'">{{ bet.status === 'won' ? 'ganhou' : bet.status === 'pending' ? 'aguardando' : 'pago' }}</span></td></tr></tbody></table></div></section></main>

    <main v-else-if="view === 'profile'" class="main"><div class="profile-cover"><div><div class="eyebrow">Área do cliente</div><h1>Olá, {{ user?.name?.split(' ')[0] }}.</h1><p>Cuide dos seus dados, acompanhe pedidos e deixe o pagamento pronto para o próximo jogo.</p></div><div class="profile-avatar"><UserRound :size="30" /></div></div><div class="profile-grid"><section class="panel"><div class="panel-title"><div><span class="summary-kicker">Conta pessoal</span><h2>Seus dados</h2></div><ShieldCheck :size="20" color="#179980" /></div><div class="profile-data"><div><small>Nome</small><strong>{{ user?.name }}</strong></div><div><small>E-mail</small><strong>{{ user?.email }}</strong></div><div><small>Perfil</small><strong>Cliente verificado para demonstração</strong></div></div><div class="notice">Para operar em produção, complete KYC, aceite os termos e mantenha seus dados de contato atualizados.</div></section><section class="panel payment-panel"><div class="panel-title"><div><span class="summary-kicker">Pagamentos</span><h2>Carteira segura</h2></div><CreditCard :size="20" color="#5c2db8" /></div><p>Gerencie cartões diretamente no portal seguro do Stripe. PIX é escolhido no checkout de cada pedido.</p><button class="btn btn-primary" :disabled="accountLoading" @click="openBillingPortal"><CreditCard :size="16" /> {{ accountLoading ? 'Abrindo Stripe...' : 'Configurar cartão' }}</button><div class="payment-methods"><span><CreditCard :size="15" /> Cartão</span><span><CircleDollarSign :size="15" /> PIX</span></div><div class="notice">Boleto temporariamente desativado para o lançamento.</div></section></div><section class="profile-cart panel"><div><span class="summary-kicker">Seu carrinho</span><h2>{{ cartCount ? `${cartCount} item(ns) aguardando` : 'Nenhum item salvo' }}</h2><p>{{ cartCount ? 'Seus cupons continuam salvos neste navegador.' : 'Escolha um jogo ou bolão para começar.' }}</p></div><button class="btn btn-primary" @click="cartOpen = true">Abrir carrinho <ShoppingCart :size="16" /></button></section></main>

    <main v-else class="auth-wrap"><section class="auth-card"><button class="brand" @click="navigate('home')"><span class="brand-mark">✦</span> Loterias Online</button><h1>{{ isRegister ? 'Crie sua conta' : loginPortal === 'admin' ? 'Acesso administrativo' : 'Bem-vindo de volta' }}</h1><p>{{ isRegister ? 'Salve seu carrinho e acompanhe seus cupons em um só lugar.' : loginPortal === 'admin' ? 'Controle sua operação com clareza.' : 'Entre para acompanhar suas apostas e bolões.' }}</p><div v-if="!isRegister" class="filters" style="justify-content:center;margin-top:24px;margin-bottom:0"><button class="chip" :class="{active:loginPortal==='cliente'}" @click="loginPortal='cliente'">Cliente</button><button class="chip" :class="{active:loginPortal==='admin'}" @click="loginPortal='admin'">Admin</button></div><div v-if="isRegister" class="field"><label>Seu nome</label><input v-model="customerName" type="text" placeholder="Como podemos chamar você?" /></div><div class="field"><label>E-mail</label><input v-model="email" type="email" placeholder="voce@email.com" /></div><div class="field"><label>Senha</label><input v-model="password" type="password" placeholder="Mínimo de 8 caracteres" @keyup.enter="isRegister ? submitRegister() : submitLogin()" /></div><div v-if="isRegister" class="field"><label>Confirme a senha</label><input v-model="passwordConfirmation" type="password" placeholder="Repita sua senha" @keyup.enter="submitRegister" /></div><p v-if="loginError" style="color:#bd2856;font-size:12px;margin-top:12px">{{ loginError }}</p><button class="btn btn-primary" :disabled="loading" @click="isRegister ? submitRegister() : submitLogin()">{{ loading ? 'Aguarde...' : isRegister ? 'Criar conta e continuar' : 'Entrar na conta' }}</button><div class="auth-switch" v-if="!isRegister">Ainda não tem conta? <button @click="openRegister">Criar cadastro</button></div><div class="auth-switch" v-else>Já tem conta? <button @click="openLogin()">Entrar</button></div><div class="notice" v-if="!isRegister"><strong>Demo:</strong> {{ loginPortal === 'admin' ? 'admin@loterias.online' : 'cliente@loterias.online' }} · senha <strong>Loterias@2026!</strong></div><div class="notice" v-else>Ao criar a conta, seu carrinho atual continua salvo. O pagamento só acontece depois da revisão do pedido.</div></section></main>

    <div v-if="cartOpen" class="cart-overlay" @click.self="cartOpen = false"><aside class="cart-drawer"><div class="cart-head"><div><span class="summary-kicker">Pedido</span><h2>Seu carrinho</h2></div><button class="icon-button" @click="cartOpen = false"><X :size="18" /></button></div><div v-if="cartCount" class="cart-items"><article v-for="ticket in cart" :key="ticket.id" class="cart-item"><div class="cart-item-icon" :style="{ background: ticket.game.color }"><component :is="gameIcon(ticket.game)" :size="17" /></div><div class="cart-item-copy"><strong>{{ ticket.kind === 'pool' ? 'Bolão · ' : '' }}{{ ticket.game.name }}</strong><small>{{ ticketSubtitle(ticket) }}</small></div><strong class="cart-item-price">{{ money(ticket.amount_cents) }}</strong><button class="remove-item" @click="removeCartItem(ticket.id)"><Trash2 :size="15" /></button></article></div><div v-else class="cart-empty"><ShoppingCart :size="35" /><strong>Seu carrinho está vazio</strong><p>Escolha um jogo ou bolão para adicionar seu primeiro cupom.</p><button class="btn btn-primary" @click="cartOpen = false; navigate('games')">Escolher jogo</button></div><div v-if="cartCount" class="cart-footer"><div class="cart-total"><span>Total do pedido</span><strong>{{ cartTotalLabel }}</strong></div><label class="payment-select"><span>Forma de pagamento</span><select v-model="paymentMethod"><option value="card">Cartão</option><option value="pix">PIX</option></select></label><div v-if="checkoutFeedback" class="checkout-feedback">{{ checkoutFeedback }}</div><button class="btn btn-primary checkout-button" :disabled="loading" @click="checkoutCart">{{ loading ? 'Preparando pedido...' : user ? 'Pagar com segurança' : 'Entrar para pagar' }} <ArrowRight :size="16" /></button><button class="clear-cart" @click="clearCart">Limpar carrinho</button><div class="notice">O pedido só é confirmado após aprovação do Stripe e do controle de reserva da operação. Boleto está temporariamente desativado.</div></div></aside></div>

    <button v-if="view === 'games' && selectedGame" class="coupon-quick" @click="generateCoupon"><Sparkles :size="15" /> Gerar cupom Surpresinha</button>
    <div v-if="toast" class="toast"><CheckCircle2 :size="16" style="vertical-align:-3px;margin-right:6px" />{{ toast }}</div>
  </div>
</template>
