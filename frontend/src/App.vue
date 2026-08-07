<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from './services/api';
import { ArrowRight, BarChart3, CheckCircle2, ChevronRight, CircleDollarSign, Clock3, Heart, Menu, ShieldCheck, Sparkles, Ticket, Trophy, WalletCards, X } from 'lucide-vue-next';

type View = 'home' | 'games' | 'pools' | 'login' | 'admin';
type Game = { id: number; slug: string; name: string; short_name: string; price_cents: number; color: string; range_max: number; numbers_required: number; next_draw?: { contest_number: number; draw_at: string } };
type User = { id: number; name: string; email: string; portal: 'admin' | 'cliente' };

const view = ref<View>('home');
const mobileOpen = ref(false);
const isLogin = ref(false);
const loginPortal = ref<'cliente' | 'admin'>('cliente');
const email = ref('');
const password = ref('');
const user = ref<User | null>(null);
const catalog = ref<Game[]>([]);
const selectedGame = ref<Game | null>(null);
const selectedNumbers = ref<number[]>([]);
const selectedFilter = ref('Todos');
const toast = ref('');
const loginError = ref('');
const loading = ref(false);
const adminData = ref<any>(null);

const demoCatalog: Game[] = [
  { id: 1, slug: 'mega-sena', name: 'Mega-Sena', short_name: 'MEGA', price_cents: 500, color: '#31b8b2', range_max: 60, numbers_required: 6, next_draw: { contest_number: 2910, draw_at: '2026-08-08T20:00:00-03:00' } },
  { id: 2, slug: 'lotofacil', name: 'Lotofácil', short_name: 'FÁCIL', price_cents: 350, color: '#8c5be5', range_max: 25, numbers_required: 15, next_draw: { contest_number: 3480, draw_at: '2026-08-07T20:00:00-03:00' } },
  { id: 3, slug: 'quina', name: 'Quina', short_name: 'QUINA', price_cents: 300, color: '#ef9151', range_max: 80, numbers_required: 5, next_draw: { contest_number: 6820, draw_at: '2026-08-07T20:00:00-03:00' } },
  { id: 4, slug: 'timemania', name: 'Timemania', short_name: 'TIME', price_cents: 350, color: '#f05295', range_max: 80, numbers_required: 10, next_draw: { contest_number: 2260, draw_at: '2026-08-09T20:00:00-03:00' } },
  { id: 5, slug: 'dia-de-sorte', name: 'Dia de Sorte', short_name: 'DIA', price_cents: 250, color: '#f1b833', range_max: 31, numbers_required: 7, next_draw: { contest_number: 1080, draw_at: '2026-08-09T20:00:00-03:00' } },
  { id: 6, slug: 'dupla-sena', name: 'Dupla Sena', short_name: 'DUPLA', price_cents: 300, color: '#3d8de5', range_max: 50, numbers_required: 6, next_draw: { contest_number: 2860, draw_at: '2026-08-08T20:00:00-03:00' } },
  { id: 7, slug: 'lotomania', name: 'Lotomania', short_name: 'LOTO', price_cents: 300, color: '#e061b7', range_max: 100, numbers_required: 20, next_draw: { contest_number: 2800, draw_at: '2026-08-08T20:00:00-03:00' } },
  { id: 8, slug: 'super-sete', name: 'Super Sete', short_name: '7', price_cents: 300, color: '#41a86d', range_max: 9, numbers_required: 7, next_draw: { contest_number: 730, draw_at: '2026-08-08T20:00:00-03:00' } },
];

const gameGroups = ['Todos', 'Mais jogados', 'Menor preço', 'Bolões'];
const gamesToShow = computed(() => {
  if (selectedFilter.value === 'Mais jogados') return catalog.value.slice(0, 4);
  if (selectedFilter.value === 'Menor preço') return [...catalog.value].sort((a, b) => a.price_cents - b.price_cents);
  return catalog.value;
});
const numbers = computed(() => Array.from({ length: selectedGame.value?.range_max ?? 60 }, (_, i) => i + 1));
const amount = computed(() => selectedGame.value ? selectedGame.value.price_cents / 100 : 0);
const formattedAmount = computed(() => amount.value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
const canBet = computed(() => selectedGame.value && selectedNumbers.value.length >= selectedGame.value.numbers_required);

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
function notify(message: string) { toast.value = message; window.setTimeout(() => { toast.value = ''; }, 3600); }

async function loadCatalog() {
  try { const response = await api<{ data: Game[] }>('/api/v1/catalog'); catalog.value = response.data; }
  catch { catalog.value = demoCatalog; }
}
function openLogin(portal: 'cliente' | 'admin' = 'cliente') { loginPortal.value = portal; isLogin.value = true; view.value = 'login'; mobileOpen.value = false; }
function logout() { localStorage.removeItem('lottery_token'); user.value = null; view.value = 'home'; notify('Você saiu da sua conta.'); }
async function submitLogin() {
  loading.value = true; loginError.value = '';
  try {
    const response = await api<{ data: { access_token: string; profile: User } }>('/api/auth/login', { method: 'POST', body: JSON.stringify({ email: email.value, password: password.value, portal: loginPortal.value }) });
    localStorage.setItem('lottery_token', response.data.access_token); user.value = response.data.profile; isLogin.value = false; view.value = loginPortal.value === 'admin' ? 'admin' : 'home'; notify('Acesso autorizado.');
  } catch {
    const demoEmail = loginPortal.value === 'admin' ? 'admin@loterias.online' : 'cliente@loterias.online';
    if (email.value === demoEmail && password.value === 'Loterias@2026!') {
      user.value = { id: loginPortal.value === 'admin' ? 1 : 2, name: loginPortal.value === 'admin' ? 'Admin Loterias Online' : 'Cliente Demo', email: email.value, portal: loginPortal.value };
      isLogin.value = false; view.value = loginPortal.value === 'admin' ? 'admin' : 'home'; notify('Modo demonstração ativado.');
    } else loginError.value = 'Confira seu e-mail, senha e perfil de acesso.';
  } finally { loading.value = false; }
}
function chooseGame(game: Game) { selectedGame.value = game; selectedNumbers.value = []; view.value = 'games'; }
function toggleNumber(number: number) {
  if (!selectedGame.value) return;
  const index = selectedNumbers.value.indexOf(number);
  if (index >= 0) selectedNumbers.value.splice(index, 1);
  else if (selectedNumbers.value.length < selectedGame.value.numbers_required) selectedNumbers.value.push(number);
}
async function submitBet() {
  if (!user.value) return openLogin();
  if (!selectedGame.value || !canBet.value) return notify(`Escolha ${selectedGame.value?.numbers_required ?? 6} números para continuar.`);
  loading.value = true;
  try {
    const bet = await api<{ data: { id: number } }>('/api/v1/bets', { method: 'POST', body: JSON.stringify({ game_id: selectedGame.value.id, draw_id: selectedGame.value.next_draw?.contest_number, numbers: selectedNumbers.value }) });
    const checkout = await api<{ data: { checkout_url?: string } }>('/api/v1/payments/checkout', { method: 'POST', body: JSON.stringify({ bet_id: bet.data.id, method: 'card' }) });
    if (checkout.data.checkout_url) window.location.href = checkout.data.checkout_url;
    else notify('Aposta criada. Checkout de teste pronto para configurar.');
  } catch (error) { notify(error instanceof Error ? error.message : 'Aposta criada no modo demonstração.'); }
  finally { loading.value = false; }
}
async function loadAdmin() {
  try { const response = await api<{ data: any }>('/api/v1/admin/dashboard'); adminData.value = response.data; }
  catch { adminData.value = { kpis: { revenue_cents: 3020000, payout_cents: 1240000, margin_cents: 1780000, active_bets: 1842 }, chart: fallbackChart, bets: [{ id: '#LO-10294', player: 'Mariana Costa', game: 'Mega-Sena', amount_cents: 500, status: 'paid' }, { id: '#LO-10293', player: 'Rafael Lima', game: 'Lotofácil', amount_cents: 350, status: 'won' }, { id: '#LO-10292', player: 'João Pedro', game: 'Quina', amount_cents: 300, status: 'pending' }] }; }
}
function showAdmin() { if (user.value?.portal === 'admin') { view.value = 'admin'; loadAdmin(); } else openLogin('admin'); }
onMounted(async () => { await loadCatalog(); if (localStorage.getItem('lottery_token')) { try { const response = await api<{ data: User }>('/api/v1/me'); user.value = response.data; } catch { localStorage.removeItem('lottery_token'); } } });
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <div class="topbar-inner">
        <button class="brand" @click="view = 'home'"><span class="brand-mark">✦</span> Loterias Online</button>
        <nav class="nav">
          <button :class="{ active: view === 'home' }" @click="view = 'home'">Início</button>
          <button :class="{ active: view === 'games' }" @click="view = 'games'">Jogos</button>
          <button :class="{ active: view === 'pools' }" @click="view = 'pools'">Bolões</button>
          <button @click="notify('Resultados sincronizados com a Caixa após publicação oficial.')">Resultados</button>
        </nav>
        <div class="top-actions">
          <button v-if="user?.portal === 'admin'" class="btn btn-ghost btn-small" @click="showAdmin()"><BarChart3 :size="15" /> Admin</button>
          <button v-if="user" class="btn btn-yellow btn-small" @click="logout()">Sair</button>
          <button v-else class="btn btn-ghost btn-small" @click="openLogin()">Entrar</button>
          <button class="mobile-menu btn btn-ghost btn-small" @click="mobileOpen = !mobileOpen"><Menu v-if="!mobileOpen" :size="18" /><X v-else :size="18" /></button>
        </div>
      </div>
      <div v-if="mobileOpen" class="nav" style="display:flex; padding:0 20px 16px; justify-content:space-around;"><button @click="view = 'home'; mobileOpen = false">Início</button><button @click="view = 'games'; mobileOpen = false">Jogos</button><button @click="view = 'pools'; mobileOpen = false">Bolões</button></div>
    </header>

    <main v-if="view === 'home'" class="main">
      <section class="hero">
        <div class="hero-copy"><div class="eyebrow">Seu próximo momento pode começar aqui</div><h1>Jogue com leveza. Sonhe grande.</h1><p>Escolha seus números, participe de bolões inteligentes e acompanhe tudo em um só lugar — com transparência em cada etapa.</p><button class="btn btn-yellow" @click="view = 'games'">Escolher meu jogo <ArrowRight :size="16" /></button></div>
        <div class="hero-badge"><div><strong>R$ 12 mi</strong>em prêmios estimados*</div></div>
      </section>
      <div class="section-head"><div><h2>Escolha sua sorte</h2><p>Jogos oficiais, simples de apostar e fáceis de acompanhar.</p></div><button class="link" @click="view = 'games'">Ver todos <ChevronRight :size="14" /></button></div>
      <section class="games"><article v-for="game in catalog.slice(0, 4)" :key="game.id" class="game-card" :style="{ '--game-color': game.color }" @click="chooseGame(game)"><div class="game-top"><div class="game-logo">{{ game.short_name.slice(0, 2) }}</div><button class="favorite" @click.stop="notify('Jogo salvo nos favoritos.')"><Heart :size="15" /></button></div><h3>{{ game.name }}</h3><div class="sub">Concurso {{ game.next_draw?.contest_number ?? '—' }} · {{ shortDate(game.next_draw?.draw_at) }}</div><div class="game-bottom"><div class="game-price">{{ money(game.price_cents) }}</div><div class="game-draw">aposta mínima<br /><strong>prêmio estimado</strong></div></div></article></section>
      <section class="feature-row"><article class="feature"><div class="feature-icon"><ShieldCheck :size="20" /></div><strong>Jogue com segurança</strong><p>Pagamentos protegidos e acompanhamento claro do status de cada aposta.</p></article><article class="feature"><div class="feature-icon"><Ticket :size="20" /></div><strong>Bolões que cabem no bolso</strong><p>Mais combinações, mais diversão e participação fácil de acompanhar.</p></article><article class="feature"><div class="feature-icon"><Clock3 :size="20" /></div><strong>Resultado sem ansiedade</strong><p>Assim que a Caixa publica, a conferência acontece automaticamente.</p></article></section>
      <div class="section-head"><div><h2>Histórias que inspiram</h2><p>Conteúdo demonstrativo para a experiência da plataforma.</p></div></div>
      <section class="testimonials"><article v-for="(quote, index) in [{ name: 'Camila R.', month: 'Junho · demonstração', text: 'O fluxo é leve e eu consigo conferir todas as minhas apostas sem perder o horário do sorteio.' }, { name: 'Bruno M.', month: 'Maio · demonstração', text: 'Entrei em um bolão e gostei de ver as cotas, o valor e o status em uma tela só.' }, { name: 'Lívia S.', month: 'Abril · demonstração', text: 'A experiência é simples até para escolher os números e finalizar o pedido.' }]" :key="quote.name" class="quote"><p>“{{ quote.text }}”</p><div class="quote-foot"><div class="avatar">{{ quote.name[0] }}</div><div><strong>{{ quote.name }}</strong><small>{{ quote.month }}</small></div></div></article></section>
      <p class="notice" style="margin-top:18px">*Valores e depoimentos exibidos nesta versão são ilustrativos para demonstração do produto e não representam promessa de prêmio ou ganho real.</p>
    </main>

    <main v-else-if="view === 'games'" class="main"><div class="section-head"><div><div class="eyebrow" style="color:var(--purple)">Jogos oficiais</div><h2>Monte sua aposta</h2><p>Selecione uma modalidade e marque seus números.</p></div><button class="btn btn-primary btn-small" @click="view = 'pools'">Ver bolões</button></div><div class="filters"><button v-for="group in gameGroups" :key="group" class="chip" :class="{ active: selectedFilter === group }" @click="selectedFilter = group">{{ group }}</button></div><div class="page-grid"><section class="panel"><div class="games" style="grid-template-columns:repeat(3,1fr)"><article v-for="game in gamesToShow" :key="game.id" class="game-card" :style="{ '--game-color': game.color }" @click="chooseGame(game)"><div class="game-top"><div class="game-logo">{{ game.short_name.slice(0, 2) }}</div><span class="status success">ativo</span></div><h3>{{ game.name }}</h3><div class="sub">{{ game.numbers_required }} números · até {{ game.range_max }}</div><div class="game-bottom"><div class="game-price">{{ money(game.price_cents) }}</div><div class="game-draw">Concurso<br /><strong>{{ game.next_draw?.contest_number }}</strong></div></div></article></div></section><aside class="panel summary"><div class="panel-title"><h2>{{ selectedGame ? selectedGame.name : 'Sua aposta' }}</h2><Sparkles :size="20" color="#ffc94e" /></div><template v-if="selectedGame"><p style="color:#ded2f9;font-size:13px;line-height:1.5">Marque {{ selectedGame.numbers_required }} números. Você selecionou <strong style="color:white">{{ selectedNumbers.length }}</strong>.</p><div class="number-grid" style="margin-top:18px"><button v-for="number in numbers" :key="number" class="number" :class="{ selected: selectedNumbers.includes(number) }" @click="toggleNumber(number)">{{ String(number).padStart(2, '0') }}</button></div><div class="summary-total"><span>Aposta mínima</span><strong>{{ formattedAmount }}</strong></div><button class="btn btn-yellow" :disabled="loading" @click="submitBet">{{ loading ? 'Preparando...' : 'Continuar para pagamento' }}</button><div class="notice">O pagamento só confirma a aposta após aprovação do provedor. Em teste, use as chaves de homologação do Stripe.</div></template><div v-else class="empty" style="color:#ded2f9">Escolha um jogo ao lado para começar.</div></aside></div></main>

    <main v-else-if="view === 'pools'" class="main"><div class="section-head"><div><div class="eyebrow" style="color:var(--purple)">Mais combinações</div><h2>Bolões em destaque</h2><p>Participe de grupos com cotas transparentes e acompanhamento por concurso.</p></div><button class="btn btn-primary" @click="view = 'games'">Fazer aposta simples</button></div><section class="games"><article v-for="pool in [{ game: 'Mega-Sena', title: 'Milionário da Semana', shares: '87/100 cotas', price: 12.5, color:'#31b8b2' }, { game:'Lotofácil', title:'Fácil Premiado', shares:'132/200 cotas', price:7.9, color:'#8c5be5' }, { game:'Quina', title:'Quina Turbo', shares:'42/80 cotas', price:9.5, color:'#ef9151' }]" :key="pool.title" class="pool-card game-card" :style="{ '--game-color': pool.color }"><div class="game-top"><div class="game-logo"><Trophy :size="19" /></div><span class="status success">aberto</span></div><h3>{{ pool.title }}</h3><div class="sub">{{ pool.game }} · {{ pool.shares }}</div><div class="game-bottom"><div class="game-price">{{ pool.price.toLocaleString('pt-BR',{style:'currency',currency:'BRL'}) }}<small style="font-size:11px;color:var(--muted)"> / cota</small></div><button class="btn btn-primary btn-small" @click="openLogin()">Entrar</button></div></article></section><div class="panel" style="margin-top:20px;display:flex;align-items:center;gap:16px"><CircleDollarSign color="#5c2db8" /><div><strong>Como funciona</strong><p style="color:var(--muted);font-size:13px;margin-top:4px">Você compra uma ou mais cotas, nós registramos as combinações do bolão e o resultado é dividido conforme as cotas confirmadas.</p></div></div></main>

    <main v-else-if="view === 'admin'" class="main"><div class="admin-header"><div><div class="eyebrow" style="color:var(--purple)">Visão administrativa</div><h1>Operação da sorte</h1><p>Controle financeiro, exposição e liquidação dos concursos.</p></div><button class="btn btn-primary" @click="notify('Sincronização de resultados adicionada à fila.')"><Clock3 :size="16" /> Sincronizar resultados</button></div><section class="kpis"><article v-for="item in [{ label:'Apostado no período', value:money(adminData?.kpis?.revenue_cents ?? 0), icon:WalletCards, change:'+12,8%' },{label:'Prêmios provisionados',value:money(adminData?.kpis?.payout_cents ?? 0),icon:Trophy,change:'sob controle'},{label:'Margem operacional',value:money(adminData?.kpis?.margin_cents ?? 0),icon:BarChart3,change:'+8,4%'},{label:'Apostas ativas',value:(adminData?.kpis?.active_bets ?? 0).toLocaleString('pt-BR'),icon:Ticket,change:'últimos 30 dias'}]" :key="item.label" class="kpi"><div class="kpi-top"><span>{{ item.label }}</span><component :is="item.icon" :size="18" color="#5c2db8" /></div><strong>{{ item.value }}</strong><small>{{ item.change }}</small></article></section><section class="admin-grid"><article class="panel chart"><div class="panel-title"><div><h2>Volume x prêmios</h2><p style="color:var(--muted);font-size:12px;margin-top:4px">Acompanhamento diário</p></div><select class="chip"><option>Últimos 7 dias</option><option>Últimos 30 dias</option></select></div><apexchart type="line" height="255" :options="chartOptions" :series="adminData?.chart ?? fallbackChart" /></article><article class="panel"><div class="panel-title"><h2>Exposição por jogo</h2><ShieldCheck :size="19" color="#179980" /></div><div v-for="row in [{name:'Mega-Sena',exposure:'R$ 48.200',limit:'R$ 80.000',percent:60,color:'#31b8b2'},{name:'Lotofácil',exposure:'R$ 22.700',limit:'R$ 35.000',percent:65,color:'#8c5be5'},{name:'Quina',exposure:'R$ 12.400',limit:'R$ 25.000',percent:49,color:'#ef9151'},{name:'Demais jogos',exposure:'R$ 7.800',limit:'R$ 18.000',percent:43,color:'#f64c9d'}]" :key="row.name" style="margin-bottom:19px"><div style="display:flex;justify-content:space-between;font-size:13px"><strong>{{ row.name }}</strong><span style="color:var(--muted)">{{ row.exposure }} / {{ row.limit }}</span></div><div style="height:8px;border-radius:8px;background:#eee8f6;margin-top:9px;overflow:hidden"><div :style="{width:row.percent+'%',background:row.color,height:'100%',borderRadius:'8px'}"></div></div></div><div class="notice">Limite global configurável por concurso. Novas apostas podem ser pausadas automaticamente quando a exposição exceder a reserva disponível.</div></article></section><section class="panel" style="margin-top:17px"><div class="panel-title"><h2>Apostas recentes</h2><button class="link" @click="notify('Filtros avançados em breve.')">Ver todas <ChevronRight :size="14" /></button></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Cliente</th><th>Jogo</th><th>Valor</th><th>Status</th></tr></thead><tbody><tr v-for="bet in adminData?.bets ?? []" :key="bet.id"><td><strong>{{ bet.id }}</strong></td><td>{{ bet.player }}</td><td>{{ bet.game }}</td><td>{{ money(bet.amount_cents) }}</td><td><span class="status" :class="bet.status === 'won' ? 'success' : bet.status === 'pending' ? 'pending' : 'success'">{{ bet.status === 'won' ? 'ganhou' : bet.status === 'pending' ? 'aguardando' : 'pago' }}</span></td></tr></tbody></table></div></section></main>

    <main v-else class="auth-wrap"><section class="auth-card"><button class="brand" @click="view='home'"><span class="brand-mark">✦</span> Loterias Online</button><h1>{{ loginPortal === 'admin' ? 'Acesso administrativo' : 'Bem-vindo de volta' }}</h1><p>{{ loginPortal === 'admin' ? 'Controle sua operação com clareza.' : 'Entre para acompanhar suas apostas e bolões.' }}</p><div class="filters" style="justify-content:center;margin-top:24px;margin-bottom:0"><button class="chip" :class="{active:loginPortal==='cliente'}" @click="loginPortal='cliente'">Cliente</button><button class="chip" :class="{active:loginPortal==='admin'}" @click="loginPortal='admin'">Admin</button></div><div class="field"><label>E-mail</label><input v-model="email" type="email" placeholder="voce@email.com" /></div><div class="field"><label>Senha</label><input v-model="password" type="password" placeholder="••••••••" @keyup.enter="submitLogin" /></div><p v-if="loginError" style="color:#bd2856;font-size:12px;margin-top:12px">{{ loginError }}</p><button class="btn btn-primary" :disabled="loading" @click="submitLogin">{{ loading ? 'Entrando...' : 'Entrar na conta' }}</button><div class="auth-switch">Ainda não tem conta? <button @click="notify('Cadastro será liberado após validar KYC e regras de operação.')">Criar cadastro</button></div><div class="notice"><strong>Demo:</strong> {{ loginPortal === 'admin' ? 'admin@loterias.online' : 'cliente@loterias.online' }} · senha <strong>Loterias@2026!</strong></div></section></main>

    <div v-if="toast" class="toast"><CheckCircle2 :size="16" style="vertical-align:-3px;margin-right:6px" />{{ toast }}</div>
  </div>
</template>

