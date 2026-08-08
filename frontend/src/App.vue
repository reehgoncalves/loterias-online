<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { loadStripe, type Stripe, type StripeCardElement, type StripeElements } from '@stripe/stripe-js';
import { api } from './services/api';
import { ArrowRight, Banknote, BarChart3, CarFront, CheckCircle2, ChevronLeft, ChevronRight, CircleDollarSign, Clock3, CreditCard, Heart, House, Menu, Plus, ShieldCheck, ShoppingCart, Sparkles, Target, Ticket, Trash2, Trophy, UserRound, WalletCards, X } from 'lucide-vue-next';

type View = 'home' | 'games' | 'pools' | 'combos' | 'login' | 'admin' | 'profile';
type Game = { id: number; slug: string; name: string; short_name: string; price_cents: number; price_table?: Record<string, number>; official_price_table?: Record<string, number>; color: string; range_max: number; number_min?: number; numbers_required: number; min_numbers?: number; max_numbers?: number; selection_mode?: string; special_options?: { columns?: number; special_type?: string }; rules_source_url?: string | null; next_draw?: { id?: number; contest_number: number; draw_at: string; sales_close_at?: string | null } };
type User = { id: number; name: string; email: string; portal: 'admin' | 'cliente'; is_admin?: boolean; has_stripe_customer?: boolean };
type CartTicket = { id: string; game: Game; draw_id?: number; numbers: number[] | number[][]; lines?: number[][]; special_value?: string | null; amount_cents: number; kind?: 'game' | 'pool' | 'combo'; pool_id?: number; shares?: number; combo_id?: string; combo_title?: string };
type SavedCard = { id: string; brand: string; last4: string; exp_month: number | null; exp_year: number | null; funding?: string | null };
type PixPayment = { payment_intent_id: string; payload: string | null; image_url: string | null; hosted_url: string | null; expires_at: number | null };
type PoolCard = { id: number; slug: string; game: string; title: string; shares: string; price: number; draw_id?: number; color: string; totalShares: number; soldShares: number; numbers: number[]; lines: number[][]; numbersCount: number; drawLabel: string; drawAt?: string; salesCloseAt?: string | null; description: string };
type WalletData = { wallet: { id: number; currency: string; balance_cents: number; locked_cents: number; status: string }; transactions: Array<{ id: number; type: string; amount_cents: number; balance_after_cents: number; status: string; created_at: string }>; withdrawals: Array<{ id: number; amount_cents: number; method: string; status: string; review_note?: string; requested_at: string }> };
type WinningBet = { id: number; numbers: number[]; status: string; payment_status: string; amount_cents: number; payout_cents: number; is_pool_share?: boolean; game?: Pick<Game, 'name' | 'color'>; draw?: { contest_number: number; draw_at?: string }; payout?: { status: string; approved_at?: string | null } };
type ComboOffer = { id: string; title: string; eyebrow: string; description: string; gameSlugs: string[]; tags: string[]; color: string; icon: typeof Sparkles };

const view = ref<View>('home');
const mobileOpen = ref(false);
const isLogin = ref(false);
const isRegister = ref(false);
const loginPortal = ref<'cliente' | 'admin'>('cliente');
const customerName = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const ageConfirmed = ref(false);
const termsAccepted = ref(false);
const termsModalOpen = ref(false);
const termsVersion = 'v1.0';
const user = ref<User | null>(null);
const catalog = ref<Game[]>([]);
const selectedGame = ref<Game | null>(null);
const selectedNumbers = ref<number[]>([]);
const selectedColumns = ref<number[][]>([]);
const selectedSpecialValue = ref<string>('');
const selectedFilter = ref('Todos');
const toast = ref('');
const loginError = ref('');
const loading = ref(false);
const adminData = ref<any>(null);
const adminWithdrawals = ref<any[]>([]);
const adminPayouts = ref<any[]>([]);
const adminPrices = ref<any[]>([]);
const priceModalOpen = ref(false);
const selectedAdminPrice = ref<any | null>(null);
const adminPriceDraft = ref<Record<string, string>>({});
const cartOpen = ref(false);
const cart = ref<CartTicket[]>([]);
const paymentModalOpen = ref(false);
const paymentMethodsLoading = ref(false);
const paymentMethodsConfigured = ref(true);
const savedCards = ref<SavedCard[]>([]);
const selectedPaymentMethodId = ref<string | null>(null);
const stripePublishableKey = ref<string | null>(null);
const pixPayment = ref<PixPayment | null>(null);
const accountLoading = ref(false);
const paymentMethod = ref<'card' | 'pix'>('card');
const checkoutFeedback = ref('');
const walletData = ref<WalletData | null>(null);
const walletLoading = ref(false);
const withdrawalAmount = ref('');
const pixKey = ref('');
const walletFeedback = ref('');
const cardModalOpen = ref(false);
const cardModalLoading = ref(false);
const cardModalError = ref('');
const cardModalSuccess = ref('');
const cardSetupClientSecret = ref<string | null>(null);
const winnerModalOpen = ref(false);
const winningBet = ref<WinningBet | null>(null);
const fireworksActive = ref(false);
const poolDetailsOpen = ref(false);
const selectedPool = ref<PoolCard | null>(null);
const adminResults = ref<any[]>([]);
const resultsModalOpen = ref(false);
let cardStripe: Stripe | null = null;
let cardElements: StripeElements | null = null;
let cardElement: StripeCardElement | null = null;
const activePromoIndex = ref(0);
const promoPaused = ref(false);
let promoTimer: number | undefined;
let winnerPollTimer: number | undefined;
let winnerFireworksTimer: number | undefined;

const demoCatalog: Game[] = [
  { id: 1, slug: 'mega-sena', name: 'Mega-Sena', short_name: 'MEGA', price_cents: 600, min_numbers: 6, max_numbers: 20, price_table: {6:600,7:4200,8:16800,9:50400,10:126000,11:277200,12:554400,13:1029600,14:1801800,15:3003000,16:4804800,17:7425600,18:11138400,19:16279200,20:23256000}, color: '#31b8b2', range_max: 60, numbers_required: 6, next_draw: { id: 1, contest_number: 2910, draw_at: '2026-08-08T21:00:00-03:00' } },
  { id: 2, slug: 'lotofacil', name: 'Lotofácil', short_name: 'FÁCIL', price_cents: 350, min_numbers: 15, max_numbers: 20, price_table: {15:350,16:5600,17:47600,18:285600,19:1356600,20:5426400}, color: '#8c5be5', range_max: 25, numbers_required: 15, next_draw: { id: 2, contest_number: 3480, draw_at: '2026-08-07T21:00:00-03:00' } },
  { id: 3, slug: 'quina', name: 'Quina', short_name: 'QUINA', price_cents: 300, min_numbers: 5, max_numbers: 15, price_table: {5:300,6:1800,7:6300,8:16800,9:37800,10:75600,11:138600,12:237600,13:386100,14:600600,15:900900}, color: '#ef9151', range_max: 80, numbers_required: 5, next_draw: { id: 3, contest_number: 6820, draw_at: '2026-08-07T21:00:00-03:00' } },
  { id: 4, slug: 'timemania', name: 'Timemania', short_name: 'TIME', price_cents: 350, min_numbers: 10, max_numbers: 10, color: '#f05295', range_max: 80, numbers_required: 10, special_options: { special_type: 'team' }, next_draw: { id: 4, contest_number: 2260, draw_at: '2026-08-09T21:00:00-03:00' } },
  { id: 5, slug: 'dia-de-sorte', name: 'Dia de Sorte', short_name: 'DIA', price_cents: 250, min_numbers: 7, max_numbers: 15, price_table: {7:250,8:2000,9:9000,10:30000,11:82500,12:198000,13:429000,14:858000,15:1608750}, color: '#f1b833', range_max: 31, numbers_required: 7, special_options: { special_type: 'month' }, next_draw: { id: 5, contest_number: 1080, draw_at: '2026-08-09T21:00:00-03:00' } },
  { id: 6, slug: 'dupla-sena', name: 'Dupla Sena', short_name: 'DUPLA', price_cents: 300, min_numbers: 6, max_numbers: 15, price_table: {6:300,7:2100,8:8400,9:25200,10:63000,11:138600,12:277200,13:514800,14:900900,15:1501500}, color: '#3d8de5', range_max: 50, numbers_required: 6, next_draw: { id: 6, contest_number: 2860, draw_at: '2026-08-08T21:00:00-03:00' } },
  { id: 7, slug: 'lotomania', name: 'Lotomania', short_name: 'LOTO', price_cents: 300, min_numbers: 50, max_numbers: 50, color: '#e061b7', number_min: 0, range_max: 99, numbers_required: 50, next_draw: { id: 7, contest_number: 2800, draw_at: '2026-08-08T21:00:00-03:00' } },
  { id: 8, slug: 'super-sete', name: 'Super Sete', short_name: '7', price_cents: 300, min_numbers: 7, max_numbers: 21, price_table: {7:300,8:600,9:1200,10:2400,11:4800,12:9600,13:19200,14:38400,15:57600,16:86400,17:129600,18:194400,19:291600,20:437400,21:656100}, color: '#41a86d', number_min: 0, range_max: 9, numbers_required: 7, selection_mode: 'columns', special_options: { columns: 7, special_type: 'columns' }, next_draw: { id: 8, contest_number: 730, draw_at: '2026-08-08T21:00:00-03:00' } },
];

const gameGroups = ['Todos', 'Mais jogados', 'Menor preço', 'Bolões', 'Combos'];
const comboOffers: ComboOffer[] = [
  { id: 'combo-essencial', title: 'Combo Essencial', eyebrow: '2 jogos em um pedido', description: 'Mega-Sena + Quina para variar suas escolhas e conferir tudo em um único cupom.', gameSlugs: ['mega-sena', 'quina'], tags: ['Mega-Sena', 'Quina'], color: '#31b8b2', icon: Sparkles },
  { id: 'combo-variedade', title: 'Combo Variedade', eyebrow: '3 jogos para experimentar', description: 'Lotofácil, Timemania e Dia de Sorte em uma combinação colorida para a semana.', gameSlugs: ['lotofacil', 'timemania', 'dia-de-sorte'], tags: ['Lotofácil', 'Timemania', 'Dia de Sorte'], color: '#8c5be5', icon: Ticket },
  { id: 'combo-completo', title: 'Combo Completo', eyebrow: '4 jogos em um pedido', description: 'Uma seleção ampla com os favoritos da plataforma para montar sua rotina de apostas.', gameSlugs: ['mega-sena', 'lotofacil', 'quina', 'dupla-sena'], tags: ['Mega-Sena', 'Lotofácil', 'Quina', 'Dupla Sena'], color: '#ef9151', icon: Trophy },
];
const poolOffers = ref<PoolCard[]>([
  { id: 1, slug: 'mega-sena', game: 'Mega-Sena', title: 'Milionário da Semana', shares: '87/100 cotas', price: 12.5, color: '#31b8b2', totalShares: 100, soldShares: 87, numbers: [4, 11, 19, 27, 42, 58], lines: [[4,11,19,27,42,58],[4,11,19,27,42,60],[4,11,19,27,45,58],[4,11,22,27,42,58],[4,15,19,27,42,58],[7,11,19,27,42,58],[4,11,19,33,42,58]], numbersCount: 6, drawLabel: 'Concurso 2910 · sábado, 21h', drawAt: '2026-08-08T21:00:00-03:00', description: 'Cota com 7 jogos registrados. Confira cada linha antes de comprar.' },
  { id: 2, slug: 'lotofacil', game: 'Lotofácil', title: 'Fácil Premiado', shares: '132/200 cotas', price: 7.9, color: '#8c5be5', totalShares: 200, soldShares: 132, numbers: [1, 3, 5, 7, 8, 10, 12, 14, 16, 18, 20, 21, 22, 24, 25], lines: [[1,3,5,7,8,10,12,14,16,18,20,21,22,24,25],[1,2,5,7,9,11,13,14,16,18,20,21,23,24,25],[2,3,4,8,10,12,15,17,19,20,21,22,23,24,25]], numbersCount: 15, drawLabel: 'Concurso 3480 · hoje, 21h', drawAt: '2026-08-07T21:00:00-03:00', description: 'Cota com 3 jogos registrados. Veja todas as linhas e a disponibilidade.' },
  { id: 3, slug: 'quina', game: 'Quina', title: 'Quina Turbo', shares: '42/80 cotas', price: 9.5, color: '#ef9151', totalShares: 80, soldShares: 42, numbers: [7, 18, 29, 44, 73], lines: [[7,18,29,44,73],[2,15,31,55,78],[11,24,36,49,67],[4,19,28,52,80]], numbersCount: 5, drawLabel: 'Concurso 6820 · hoje, 21h', drawAt: '2026-08-07T21:00:00-03:00', description: 'Cota com 4 jogos registrados e números completos no recibo.' },
]);
const promoSlides = [
  { key: 'mega', tone: 'mega', eyebrow: 'Bolão em destaque', title: 'Mega-Sena acumulada', amount: 'R$ 100 mi', description: 'Entre no próximo concurso com combinações selecionadas e participe por uma fração do valor.', highlight: 'Até 20% OFF', chip: 'cotas limitadas', icon: Sparkles },
  { key: 'easy', tone: 'easy', eyebrow: 'Oferta da semana', title: 'Lotofácil para jogar junto', amount: 'R$ 7,90', description: 'Escolha sua cota, acompanhe as combinações do bolão e receba o cupom no seu e-mail.', highlight: 'a partir de R$ 7,90', chip: 'bolão aberto', icon: Ticket },
  { key: 'quina', tone: 'quina', eyebrow: 'Mais chances de combinar', title: 'Quina Turbo', amount: 'R$ 9,50', description: 'Uma experiência prática para entrar no bolão e conferir o status de cada cota em um só lugar.', highlight: 'compra segura', chip: 'resultado oficial', icon: Trophy },
];
const activePromo = computed(() => promoSlides[activePromoIndex.value]);
const gamesToShow = computed(() => {
  if (selectedFilter.value === 'Mais jogados') return catalog.value.slice(0, 4);
  if (selectedFilter.value === 'Menor preço') return [...catalog.value].sort((a, b) => a.price_cents - b.price_cents);
  return catalog.value;
});
const numberMin = computed(() => selectedGame.value?.number_min ?? 1);
const numbers = computed(() => Array.from({ length: (selectedGame.value?.range_max ?? 60) - numberMin.value + 1 }, (_, i) => i + numberMin.value));
const columns = computed(() => Array.from({ length: selectedGame.value?.special_options?.columns ?? 7 }, (_, i) => i));
const minNumbers = computed(() => selectedGame.value?.min_numbers ?? selectedGame.value?.numbers_required ?? 1);
const maxNumbers = computed(() => selectedGame.value?.max_numbers ?? selectedGame.value?.numbers_required ?? minNumbers.value);
const selectedNumberCount = computed(() => selectedGame.value?.selection_mode === 'columns' ? selectedColumns.value.reduce((total, column) => total + column.length, 0) : selectedNumbers.value.length);
const amount = computed(() => selectedGame.value ? priceForGame(selectedGame.value, selectedNumberCount.value >= minNumbers.value ? selectedNumberCount.value : minNumbers.value) / 100 : 0);
const formattedAmount = computed(() => amount.value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
const canBet = computed(() => Boolean(selectedGame.value && selectedNumberCount.value >= minNumbers.value && selectedNumberCount.value <= maxNumbers.value && (selectedGame.value.special_options?.special_type !== 'team' || selectedSpecialValue.value) && (selectedGame.value.special_options?.special_type !== 'month' || selectedSpecialValue.value)));
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
function priceForGame(game: Game, count: number) { return game.price_table?.[String(count)] ?? game.price_cents; }
function flatNumbers(numbers: number[] | number[][]) { return numbers.flatMap(number => Array.isArray(number) ? number : [number]); }
function ticketSubtitle(ticket: CartTicket) { const lineText = ticket.lines?.length ? `${ticket.lines.length} jogos · ${ticket.lines[0].length} números por jogo` : `${flatNumbers(ticket.numbers).length} números`; return `${ticket.kind === 'pool' ? `1 cota · ${lineText}` : `Concurso ${ticket.game.next_draw?.contest_number ?? '—'}`} · ${flatNumbers(ticket.numbers).map(number => String(number).padStart(2, '0')).join(' · ')}${ticket.special_value ? ` · ${ticket.special_value}` : ''}`; }
function drawDateTime(value?: string) { return value ? new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'horário a confirmar'; }
function estimatedPrize(game: Game) { return ({ 'mega-sena': 'R$ 100 mi', lotofacil: 'R$ 3 mi', quina: 'R$ 12 mi', timemania: 'R$ 2,5 mi', 'dia-de-sorte': 'R$ 1,2 mi', 'dupla-sena': 'R$ 4 mi', lotomania: 'R$ 2 mi', 'super-sete': 'R$ 1 mi' } as Record<string, string>)[game.slug] ?? 'a conferir'; }
function comboPrice(combo: ComboOffer) { return combo.gameSlugs.reduce((total, slug) => total + (catalog.value.find(game => game.slug === slug)?.price_cents ?? 0), 0); }
function notify(message: string) { toast.value = message; window.setTimeout(() => { toast.value = ''; }, 3600); }
function persistCart() { localStorage.setItem('lottery_cart', JSON.stringify(cart.value)); }
function navigate(next: View) { view.value = next; mobileOpen.value = false; if (next === 'admin') loadAdmin(); if (next === 'profile' && user.value) { loadProfile(); loadWallet(); loadPaymentMethods(); void loadWinnerStatus(); } }
function gameIcon(game: Game) { return game.slug === 'mega-sena' ? Sparkles : game.slug === 'lotofacil' ? Ticket : game.slug === 'quina' ? CircleDollarSign : game.slug === 'timemania' ? Trophy : game.slug === 'dia-de-sorte' ? Banknote : game.slug === 'dupla-sena' ? WalletCards : game.slug === 'lotomania' ? Target : ShieldCheck; }
function nextPromo() { activePromoIndex.value = (activePromoIndex.value + 1) % promoSlides.length; }
function previousPromo() { activePromoIndex.value = (activePromoIndex.value - 1 + promoSlides.length) % promoSlides.length; }
function selectPromo(index: number) { activePromoIndex.value = index; }
function winnerSeenKey() { return user.value ? `lottery_winners_seen_${user.value.id}` : ''; }
function seenWinnerIds(): number[] { try { const value = JSON.parse(localStorage.getItem(winnerSeenKey()) || '[]'); return Array.isArray(value) ? value.map(Number) : []; } catch { return []; } }
function markWinnerAsSeen(id: number) { const ids = Array.from(new Set([...seenWinnerIds(), id])).slice(-100); localStorage.setItem(winnerSeenKey(), JSON.stringify(ids)); }
function announceWinner(bet: WinningBet) {
  winningBet.value = bet;
  winnerModalOpen.value = true;
  fireworksActive.value = true;
  markWinnerAsSeen(bet.id);
  if (winnerFireworksTimer) window.clearTimeout(winnerFireworksTimer);
  winnerFireworksTimer = window.setTimeout(() => { fireworksActive.value = false; }, 6200);
}
function closeWinnerCelebration() { winnerModalOpen.value = false; fireworksActive.value = false; winningBet.value = null; }
async function loadWinnerStatus() {
  if (!user.value || user.value.portal !== 'cliente') return;
  try {
    const response = await api<{ data: { data?: WinningBet[] } }>('/api/v1/my-bets');
    const winner = (response.data.data ?? []).find(bet => bet.status === 'won' && bet.payout?.status === 'approved' && !seenWinnerIds().includes(bet.id));
    if (winner) announceWinner(winner);
  } catch { /* A falha de consulta não impede o acesso do cliente. */ }
}
function startWinnerPolling() { if (winnerPollTimer) window.clearInterval(winnerPollTimer); winnerPollTimer = window.setInterval(() => { void loadWinnerStatus(); }, 30000); }
function stopWinnerPolling() { if (winnerPollTimer) { window.clearInterval(winnerPollTimer); winnerPollTimer = undefined; } }

async function loadCatalog() {
  try {
    const response = await api<{ data?: Game[] }>('/api/v1/catalog');
    catalog.value = Array.isArray(response.data) && response.data.length ? response.data : demoCatalog;
  }
  catch { catalog.value = demoCatalog; }
}
async function loadPools() {
  try {
    const response = await api<{ data?: any[] }>('/api/v1/pools');
    if (Array.isArray(response.data) && response.data.length) poolOffers.value = response.data.map(pool => ({ id: pool.id, slug: pool.slug, game: pool.game, title: pool.title, shares: `${pool.sold_shares}/${pool.total_shares} cotas`, price: pool.price_cents / 100, draw_id: pool.draw?.id, color: catalog.value.find(game => game.slug === pool.slug)?.color ?? '#5c2db8', totalShares: pool.total_shares, soldShares: pool.sold_shares, numbers: pool.lines?.[0] ?? [], lines: pool.lines ?? [], numbersCount: pool.numbers_count ?? pool.lines?.[0]?.length ?? 0, drawLabel: `Concurso ${pool.draw?.contest_number ?? '—'} · ${drawDateTime(pool.draw?.draw_at)}`, drawAt: pool.draw?.draw_at, salesCloseAt: pool.draw?.sales_close_at, description: pool.description ?? 'Bolão com números registrados e cotas atualizadas.' }));
  } catch { /* a vitrine demonstrativa mantém o catálogo utilizável offline */ }
}
function openLogin(portal: 'cliente' | 'admin' = 'cliente') { loginPortal.value = portal; isRegister.value = false; loginError.value = ''; isLogin.value = true; view.value = 'login'; mobileOpen.value = false; }
function openRegister() { loginPortal.value = 'cliente'; isRegister.value = true; ageConfirmed.value = false; termsAccepted.value = false; loginError.value = ''; isLogin.value = true; view.value = 'login'; mobileOpen.value = false; }
function logout() { stopWinnerPolling(); closeWinnerCelebration(); localStorage.removeItem('lottery_token'); user.value = null; navigate('home'); notify('Você saiu da sua conta.'); }
async function submitLogin() {
  loading.value = true; loginError.value = '';
  try {
    const response = await api<{ data: { access_token: string; profile: User } }>('/api/auth/login', { method: 'POST', body: JSON.stringify({ email: email.value, password: password.value, portal: loginPortal.value }) });
    localStorage.setItem('lottery_token', response.data.access_token); user.value = response.data.profile; isLogin.value = false; navigate(loginPortal.value === 'admin' ? 'admin' : 'games'); if (loginPortal.value === 'cliente') { startWinnerPolling(); void loadWinnerStatus(); } notify(cartCount.value ? 'Acesso autorizado. Seu carrinho foi preservado.' : 'Acesso autorizado.');
  } catch {
    const demoEmail = loginPortal.value === 'admin' ? 'admin@loterias.online' : 'cliente@loterias.online';
    if (email.value === demoEmail && password.value === 'Loterias@2026!') {
      user.value = { id: loginPortal.value === 'admin' ? 1 : 2, name: loginPortal.value === 'admin' ? 'Admin Loterias Online' : 'Cliente Demo', email: email.value, portal: loginPortal.value };
      isLogin.value = false; navigate(loginPortal.value === 'admin' ? 'admin' : 'games'); if (loginPortal.value === 'cliente') { startWinnerPolling(); void loadWinnerStatus(); } notify(cartCount.value ? 'Modo demonstração ativado. Seu carrinho foi preservado.' : 'Modo demonstração ativado.');
    } else loginError.value = 'Confira seu e-mail, senha e perfil de acesso.';
  } finally { loading.value = false; }
}
async function submitRegister() {
  if (!ageConfirmed.value || !termsAccepted.value) { loginError.value = 'Confirme que tem 18 anos ou mais e aceite os Termos de Uso para criar sua conta.'; return; }
  loading.value = true; loginError.value = '';
  try {
    const response = await api<{ data: { access_token: string; profile: User } }>('/api/auth/register', { method: 'POST', body: JSON.stringify({ name: customerName.value, email: email.value, password: password.value, password_confirmation: passwordConfirmation.value, age_confirmed: ageConfirmed.value, terms_accepted: termsAccepted.value, terms_version: termsVersion }) });
    localStorage.setItem('lottery_token', response.data.access_token); user.value = response.data.profile; isLogin.value = false; navigate('games'); startWinnerPolling(); void loadWinnerStatus(); notify(response.data.profile.has_stripe_customer ? 'Cadastro criado e Customer Stripe sincronizado.' : 'Cadastro criado. O Stripe será sincronizado no primeiro pagamento.');
  } catch (error) { loginError.value = error instanceof Error ? error.message : 'Não foi possível criar seu cadastro.'; }
  finally { loading.value = false; }
}
function chooseGame(game: Game) { selectedGame.value = game; selectedNumbers.value = []; selectedColumns.value = game.selection_mode === 'columns' ? Array.from({ length: game.special_options?.columns ?? 7 }, () => [0]) : []; selectedSpecialValue.value = ''; navigate('games'); }
function secureRandom(max: number) {
  const values = new Uint32Array(1);
  crypto.getRandomValues(values);
  return values[0] % (max + 1);
}
function localCoupon(game: Game, requestedCount?: number): number[] | number[][] {
  if (game.selection_mode === 'columns') {
    const columnCount = game.special_options?.columns ?? 7; const result = Array.from({ length: columnCount }, () => [secureRandom(game.range_max)]); let extra = Math.max(0, (requestedCount ?? game.min_numbers ?? 7) - columnCount);
    while (extra > 0) for (let index = 0; index < result.length && extra > 0; index++) if (result[index].length < 3) { const candidate = secureRandom(game.range_max); if (!result[index].includes(candidate)) { result[index].push(candidate); extra--; } }
    return result;
  }
  const pool = Array.from({ length: game.range_max - (game.number_min ?? 1) + 1 }, (_, i) => i + (game.number_min ?? 1));
  for (let i = pool.length - 1; i > 0; i--) { const j = secureRandom(i); [pool[i], pool[j]] = [pool[j], pool[i]]; }
  return pool.slice(0, requestedCount ?? game.min_numbers ?? game.numbers_required).sort((a, b) => a - b);
}
async function generateCoupon() {
  if (!selectedGame.value) return;
  try {
    if (user.value) {
      const response = await api<{ data: Array<{ numbers: number[] | number[][] }> }>('/api/v1/coupons/generate', { method: 'POST', body: JSON.stringify({ game_id: selectedGame.value.id, quantity: 1, numbers_count: selectedNumberCount.value >= minNumbers.value ? selectedNumberCount.value : minNumbers.value }) });
      if (selectedGame.value.selection_mode === 'columns') selectedColumns.value = response.data[0].numbers as number[][]; else selectedNumbers.value = response.data[0].numbers as number[];
    } else if (selectedGame.value.selection_mode === 'columns') selectedColumns.value = localCoupon(selectedGame.value, minNumbers.value) as number[][];
    else selectedNumbers.value = localCoupon(selectedGame.value, minNumbers.value) as number[];
    notify('Cupom gerado com seleção aleatória segura.');
  } catch { if (selectedGame.value.selection_mode === 'columns') selectedColumns.value = localCoupon(selectedGame.value, minNumbers.value) as number[][]; else selectedNumbers.value = localCoupon(selectedGame.value, minNumbers.value) as number[]; notify('Cupom demonstrativo gerado.'); }
}
function setColumn(index: number, event: Event) {
  selectedColumns.value[index] = [Number((event.target as HTMLSelectElement).value)];
}
function toggleColumnNumber(columnIndex: number, number: number) {
  const column = selectedColumns.value[columnIndex] ?? [];
  const index = column.indexOf(number);
  if (index >= 0) column.splice(index, 1);
  else if (selectedNumberCount.value < maxNumbers.value && column.length < (selectedNumberCount.value < 15 ? 2 : 3)) column.push(number);
  if (!column.length) column.push(number);
  selectedColumns.value[columnIndex] = [...column].sort((a, b) => a - b);
}
function toggleNumber(number: number) {
  if (!selectedGame.value) return;
  if (selectedGame.value.selection_mode === 'columns') return;
  const index = selectedNumbers.value.indexOf(number);
  if (index >= 0) selectedNumbers.value.splice(index, 1);
  else if (selectedNumbers.value.length < maxNumbers.value) selectedNumbers.value.push(number);
}
function addCurrentTicket() {
  if (!selectedGame.value || !canBet.value) return notify(`Escolha entre ${minNumbers.value} e ${maxNumbers.value} números e preencha a opção especial, quando houver.`);
  if (!user.value) { notify('Entre ou crie sua conta para guardar o cupom no carrinho.'); return openLogin(); }
  if (!selectedGame.value.next_draw?.id) return notify('Concurso indisponível no momento. Atualize o catálogo para continuar.');
  const numbers = selectedGame.value.selection_mode === 'columns' ? selectedColumns.value.map(column => [...column]) : [...selectedNumbers.value];
  cart.value.push({ id: `${selectedGame.value.id}-${Date.now()}-${Math.random().toString(36).slice(2)}`, game: selectedGame.value, draw_id: selectedGame.value.next_draw.id, numbers, special_value: selectedSpecialValue.value || null, amount_cents: priceForGame(selectedGame.value, selectedNumberCount.value), kind: 'game' });
  persistCart(); cartOpen.value = true; notify(`${selectedGame.value.name} adicionado ao carrinho.`);
}
function removeCartItem(id: string) { cart.value = cart.value.filter(ticket => ticket.id !== id); persistCart(); }
function clearCart() { cart.value = []; persistCart(); }
function addPoolToCart(pool: PoolCard) {
  const game = catalog.value.find(item => item.slug === pool.slug);
  if (!user.value) { notify('Entre ou crie sua conta para guardar a cota no carrinho.'); return openLogin(); }
  if (!game || !game.next_draw?.id) return notify('Concurso do bolão indisponível no momento.');
  cart.value.push({ id: `pool-${pool.id}-${Date.now()}`, game, draw_id: pool.draw_id ?? game.next_draw.id, numbers: pool.lines[0] ?? pool.numbers, lines: pool.lines, amount_cents: Math.round(pool.price * 100), kind: 'pool', pool_id: pool.id, shares: 1 });
  persistCart(); cartOpen.value = true; notify(`${pool.title} adicionado ao carrinho.`);
}
function openPoolDetails(pool: PoolCard) { selectedPool.value = pool; poolDetailsOpen.value = true; }
function addComboToCart(combo: ComboOffer) {
  if (!user.value) { notify('Entre ou crie sua conta para guardar o combo no carrinho.'); return openLogin(); }
  const games = combo.gameSlugs.map(slug => catalog.value.find(game => game.slug === slug)).filter((game): game is Game => Boolean(game));
  if (games.length !== combo.gameSlugs.length || games.some(game => !game.next_draw?.id)) return notify('Atualize o catálogo antes de adicionar este combo.');
  games.forEach((game, index) => { const numbers = localCoupon(game, game.min_numbers ?? game.numbers_required); cart.value.push({ id: `${combo.id}-${game.id}-${Date.now()}-${index}`, game, draw_id: game.next_draw?.id, numbers, amount_cents: priceForGame(game, game.min_numbers ?? game.numbers_required), kind: 'combo', combo_id: combo.id, combo_title: combo.title }); });
  persistCart(); cartOpen.value = true; notify(`${combo.title} adicionado com ${games.length} apostas.`);
}
async function checkoutCart() {
  if (!cartCount.value) return notify('Seu carrinho está vazio.');
  if (!user.value) { notify('Entre ou crie sua conta para continuar o pagamento.'); return openLogin(); }
  if (paymentMethod.value === 'card' && !selectedPaymentMethodId.value) { checkoutFeedback.value = 'Selecione um cartão salvo ou cadastre um novo cartão para continuar.'; return; }
  if (cart.value.some(ticket => !ticket.draw_id)) return notify('Atualize o catálogo para renovar os concursos do carrinho.');
  checkoutFeedback.value = ''; loading.value = true;
  try {
    const stableCartKey = `cart-${user.value.id}-${cart.value.map(ticket => ticket.id).join('-')}-${paymentMethod.value}-${selectedPaymentMethodId.value ?? 'new'}`;
    const checkout = await api<{ data: { checkout_url?: string; mode?: string; payment_intent_status?: string; client_secret?: string | null; pix?: { payment_intent_id: string; qr_code?: { payload?: string | null; image_url?: string | null; hosted_url?: string | null; expires_at?: number | null } | null } } }>('/api/v1/orders/checkout', { method: 'POST', headers: { 'Idempotency-Key': stableCartKey }, body: JSON.stringify({ tickets: cart.value.map(ticket => ({ game_id: ticket.game.id, draw_id: ticket.draw_id, numbers: ticket.numbers, lines: ticket.lines, special_value: ticket.special_value, pool_id: ticket.pool_id, shares: ticket.shares })), method: paymentMethod.value, ...(paymentMethod.value === 'card' && selectedPaymentMethodId.value ? { payment_method_id: selectedPaymentMethodId.value } : {}) }) });
    if (checkout.data.checkout_url) {
      clearCart(); cartOpen.value = false; paymentModalOpen.value = false; window.location.href = checkout.data.checkout_url; return;
    }
    if (paymentMethod.value === 'pix' && checkout.data.pix) {
      pixPayment.value = { payment_intent_id: checkout.data.pix.payment_intent_id, payload: checkout.data.pix.qr_code?.payload ?? null, image_url: checkout.data.pix.qr_code?.image_url ?? null, hosted_url: checkout.data.pix.qr_code?.hosted_url ?? null, expires_at: checkout.data.pix.qr_code?.expires_at ?? null };
      clearCart();
      notify('QR Code PIX gerado. Conclua o pagamento pelo seu banco.');
      return;
    }
    if (checkout.data.mode === 'payment_intent' && checkout.data.payment_intent_status === 'requires_action' && checkout.data.client_secret) {
      if (!stripePublishableKey.value) throw new Error('Seu banco pediu uma confirmação extra, mas a chave pública do Stripe ainda não foi configurada.');
      const stripe = await loadStripe(stripePublishableKey.value);
      if (!stripe) throw new Error('Não foi possível carregar a confirmação segura do cartão.');
      const action = await stripe.handleCardAction(checkout.data.client_secret);
      if (action.error) throw new Error(action.error.message || 'A confirmação do cartão não foi concluída.');
    }
    clearCart(); cartOpen.value = false; paymentModalOpen.value = false;
    notify(paymentMethod.value === 'pix' ? 'PIX criado. Conclua o pagamento na página segura do Stripe.' : 'Pagamento enviado com segurança. A confirmação aparecerá após o retorno do Stripe.');
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
async function loadPaymentMethods() {
  if (!user.value) return;
  paymentMethodsLoading.value = true;
  try {
    const response = await api<{ data: { configured: boolean; cards: SavedCard[]; publishable_key?: string | null } }>('/api/v1/profile/payment-methods');
    paymentMethodsConfigured.value = response.data.configured;
    savedCards.value = response.data.cards ?? [];
    stripePublishableKey.value = response.data.publishable_key ?? null;
    if (!selectedPaymentMethodId.value || !savedCards.value.some(card => card.id === selectedPaymentMethodId.value)) selectedPaymentMethodId.value = savedCards.value[0]?.id ?? null;
    if (!savedCards.value.length && paymentMethod.value === 'card') paymentMethod.value = 'pix';
  } catch (error) {
    paymentMethodsConfigured.value = false; savedCards.value = []; selectedPaymentMethodId.value = null;
    if (paymentMethod.value === 'card') paymentMethod.value = 'pix';
    checkoutFeedback.value = error instanceof Error ? error.message : 'Não foi possível carregar seus cartões salvos.';
  } finally { paymentMethodsLoading.value = false; }
}
async function openPaymentModal() {
  if (!cartCount.value) return notify('Seu carrinho está vazio.');
  if (!user.value) { notify('Entre ou crie sua conta para continuar o pagamento.'); return openLogin(); }
  checkoutFeedback.value = '';
  pixPayment.value = null;
  paymentModalOpen.value = true;
  await loadPaymentMethods();
}
function destroyCardElement() {
  cardElement?.destroy();
  cardElement = null;
  cardElements = null;
  cardStripe = null;
  cardSetupClientSecret.value = null;
}
async function openCardRegistrationModal() {
  if (!user.value) return openLogin();
  cardModalOpen.value = true;
  cardModalLoading.value = true;
  cardModalError.value = '';
  cardModalSuccess.value = '';
  await nextTick();
  try {
    const response = await api<{ data: { client_secret: string; publishable_key: string } }>('/api/v1/profile/setup-intent', { method: 'POST' });
    cardSetupClientSecret.value = response.data.client_secret;
    cardStripe = await loadStripe(response.data.publishable_key);
    if (!cardStripe) throw new Error('Não foi possível carregar o formulário seguro do Stripe.');
    cardElements = cardStripe.elements({
      clientSecret: response.data.client_secret,
      appearance: { theme: 'stripe', variables: { colorPrimary: '#5c2db8', borderRadius: '12px', fontFamily: 'DM Sans, sans-serif' } },
    });
    cardElement = cardElements.create('card', { hidePostalCode: true });
    cardElement.mount('#card-element');
  } catch (error) {
    cardModalError.value = error instanceof Error ? error.message : 'Não foi possível abrir o cadastro seguro do cartão.';
  } finally {
    cardModalLoading.value = false;
  }
}
function closeCardRegistrationModal() {
  cardModalOpen.value = false;
  cardModalError.value = '';
  cardModalSuccess.value = '';
  destroyCardElement();
}
async function saveCardFromModal() {
  if (!cardStripe || !cardElement || !cardSetupClientSecret.value || !user.value) return;
  cardModalLoading.value = true;
  cardModalError.value = '';
  try {
    const result = await cardStripe.confirmCardSetup(cardSetupClientSecret.value, {
      payment_method: { card: cardElement, billing_details: { name: user.value.name, email: user.value.email } },
    });
    if (result.error) throw new Error(result.error.message || 'O Stripe não confirmou o cartão.');
    cardModalSuccess.value = 'Cartão salvo com segurança no Stripe.';
    await loadPaymentMethods();
    window.setTimeout(closeCardRegistrationModal, 800);
  } catch (error) {
    cardModalError.value = error instanceof Error ? error.message : 'Não foi possível salvar o cartão.';
  } finally {
    cardModalLoading.value = false;
  }
}
function openCardRegistration() { openCardRegistrationModal(); }
async function copyPixPayload() { if (!pixPayment.value?.payload) return notify('O código PIX ainda não está disponível.'); try { await navigator.clipboard.writeText(pixPayment.value.payload); notify('Código PIX copiado.'); } catch { notify('Selecione e copie o código PIX manualmente.'); } }
function submitBet() { addCurrentTicket(); }
async function openBillingPortal() {
  if (!user.value) return openLogin();
  accountLoading.value = true;
  try { const response = await api<{ data: { url: string } }>('/api/v1/profile/billing-portal', { method: 'POST' }); if (response.data.url) window.location.href = response.data.url; }
  catch (error) { notify(error instanceof Error ? error.message : 'Não foi possível abrir o portal de pagamentos.'); }
  finally { accountLoading.value = false; }
}
async function loadAdmin() {
  try {
    const [dashboard, withdrawals, payouts, results, prices] = await Promise.all([api<{ data: any }>('/api/v1/admin/dashboard'), api<{ data: { data?: any[] } }>('/api/v1/admin/wallet-withdrawals'), api<{ data: { data?: any[] } }>('/api/v1/admin/payouts'), api<{ data?: any[] }>('/api/v1/admin/results'), api<{ data?: any[] }>('/api/v1/admin/prices')]);
    adminData.value = dashboard.data;
    adminWithdrawals.value = withdrawals.data.data ?? [];
    adminPayouts.value = payouts.data.data ?? [];
    adminResults.value = results.data ?? [];
    adminPrices.value = prices.data ?? [];
  }
  catch { adminData.value = { kpis: { revenue_cents: 3020000, payout_cents: 1240000, margin_cents: 1780000, active_bets: 1842 }, chart: fallbackChart, bets: [{ id: '#LO-10294', player: 'Mariana Costa', game: 'Mega-Sena', amount_cents: 600, status: 'paid' }, { id: '#LO-10293', player: 'Rafael Lima', game: 'Lotofácil', amount_cents: 350, status: 'won' }, { id: '#LO-10292', player: 'João Pedro', game: 'Quina', amount_cents: 300, status: 'pending' }] }; }
}
function openPriceEditor(game: any) {
  selectedAdminPrice.value = game;
  adminPriceDraft.value = Object.fromEntries(Object.entries(game.price_table ?? {}).map(([count, cents]) => [count, ((Number(cents) || 0) / 100).toFixed(2).replace('.', ',')]));
  priceModalOpen.value = true;
}
async function saveAdminPrices() {
  if (!selectedAdminPrice.value) return;
  loading.value = true;
  try {
    const prices = Object.fromEntries(Object.entries(adminPriceDraft.value).map(([count, value]) => [count, Math.round(Number(String(value).replace(',', '.')) * 100)]));
    const response = await api<{ data: any }>('/api/v1/admin/games/' + selectedAdminPrice.value.id + '/prices', { method: 'PUT', body: JSON.stringify({ prices }) });
    const index = adminPrices.value.findIndex((game) => game.id === selectedAdminPrice.value.id);
    if (index >= 0) adminPrices.value[index] = response.data;
    priceModalOpen.value = false;
    notify('Tabela de preços atualizada. O piso oficial foi preservado.');
  } catch (error) {
    notify(error instanceof Error ? error.message : 'Não foi possível salvar a tabela de preços.');
  } finally { loading.value = false; }
}
async function syncAdminResults() { loading.value = true; try { const response = await api<{ data?: any[] }>('/api/v1/admin/results/sync', { method: 'POST', body: JSON.stringify({}) }); adminResults.value = response.data ?? []; resultsModalOpen.value = true; notify('Resultados consultados na fonte oficial e apuração enfileirada.'); } catch (error) { notify(error instanceof Error ? error.message : 'Não foi possível sincronizar os resultados agora.'); } finally { loading.value = false; } }
async function loadProfile() {
  try {
    const response = await api<{ data: { has_stripe_customer?: boolean } }>('/api/v1/profile');
    if (user.value) user.value = { ...user.value, has_stripe_customer: response.data.has_stripe_customer };
  } catch { /* mantém a tela utilizável durante uma indisponibilidade momentânea */ }
}
async function loadWallet() { if (!user.value) return; walletLoading.value = true; try { const response = await api<{ data: WalletData }>('/api/v1/wallet'); walletData.value = response.data; } catch { walletData.value = null; } finally { walletLoading.value = false; } }
async function requestWithdrawal() {
  const amountCents = Math.round(Number(withdrawalAmount.value.replace(',', '.')) * 100);
  if (!amountCents || !pixKey.value.trim()) return notify('Informe o valor e a chave PIX para solicitar o saque.');
  walletLoading.value = true; walletFeedback.value = '';
  try { await api('/api/v1/wallet/withdrawals', { method: 'POST', body: JSON.stringify({ amount_cents: amountCents, method: 'pix', pix_key: pixKey.value.trim() }) }); withdrawalAmount.value = ''; pixKey.value = ''; walletFeedback.value = 'Solicitação enviada para análise manual e KYC.'; await loadWallet(); }
  catch (error) { walletFeedback.value = error instanceof Error ? error.message : 'Não foi possível solicitar o saque.'; }
  finally { walletLoading.value = false; }
}
async function reviewWithdrawal(id: number, status: 'approved' | 'rejected' | 'paid') { try { await api(`/api/v1/admin/wallet-withdrawals/${id}/review`, { method: 'POST', body: JSON.stringify({ status, note: status === 'paid' ? 'Baixa simulada de homologação.' : undefined }) }); notify(status === 'paid' ? 'Saque baixado em modo de homologação.' : `Saque ${status === 'approved' ? 'aprovado' : 'rejeitado'}.`); await loadAdmin(); } catch (error) { notify(error instanceof Error ? error.message : 'Não foi possível atualizar o saque.'); } }
async function approvePayout(id: number, simulate = false) { try { await api(`/api/v1/admin/payouts/${id}/approve`, { method: 'POST', body: JSON.stringify({ simulate }) }); notify('Crédito aprovado e lançado na carteira do cliente.'); await loadAdmin(); } catch (error) { notify(error instanceof Error ? error.message : 'O prêmio ainda está no período de conferência.'); } }
function showAdmin() { if (user.value?.portal === 'admin') navigate('admin'); else openLogin('admin'); }
onMounted(async () => { promoTimer = window.setInterval(() => { if (!promoPaused.value) nextPromo(); }, 5200); await loadCatalog(); await loadPools(); try { const saved = JSON.parse(localStorage.getItem('lottery_cart') || '[]'); if (Array.isArray(saved)) cart.value = saved; } catch { cart.value = []; } if (localStorage.getItem('lottery_token')) { try { const response = await api<{ data: User }>('/api/v1/me'); user.value = response.data; await loadWallet(); if (user.value.portal === 'cliente') { startWinnerPolling(); await loadWinnerStatus(); } } catch { stopWinnerPolling(); localStorage.removeItem('lottery_token'); } } });
onBeforeUnmount(() => { if (promoTimer) window.clearInterval(promoTimer); stopWinnerPolling(); if (winnerFireworksTimer) window.clearTimeout(winnerFireworksTimer); destroyCardElement(); });
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
          <button :class="{ active: view === 'combos' }" @click="navigate('combos')">Combos</button>
          <button @click="user?.portal === 'admin' ? resultsModalOpen = true : notify('Resultados sincronizados com a Caixa após publicação oficial.')">Resultados</button>
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
      <div v-if="mobileOpen" class="nav mobile-nav"><button @click="navigate('home')">Início</button><button @click="navigate('games')">Jogos</button><button @click="navigate('pools')">Bolões</button><button @click="navigate('combos')">Combos</button><button v-if="user" @click="navigate('profile')">Minha conta</button></div>
    </header>

    <main v-if="view === 'home'" class="main">
      <section class="hero">
        <div class="hero-copy"><div class="eyebrow">Prêmio acumulado em destaque</div><h1>R$ 100 milhões podem mudar o seu próximo capítulo.</h1><p>Escolha seus números, participe de bolões inteligentes e acompanhe tudo em um só lugar — com transparência em cada etapa.</p><button class="btn btn-yellow" @click="navigate('games')">Escolher meu jogo <ArrowRight :size="16" /></button><small class="hero-note">*Campanha visual demonstrativa. Confira o valor oficial do concurso antes de apostar.</small></div>
        <div class="hero-badge"><div><strong>R$ 100 mi</strong>em destaque*</div></div>
      </section>
      <section class="promo-carousel" aria-label="Promoções de bolões" @mouseenter="promoPaused = true" @mouseleave="promoPaused = false">
        <div class="promo-stage">
          <Transition name="promo-fade" mode="out-in">
            <article :key="activePromo.key" class="promo-banner" :class="`promo-${activePromo.tone}`">
              <div class="promo-copy"><div class="promo-eyebrow"><span class="promo-live-dot"></span>{{ activePromo.eyebrow }}</div><h2>{{ activePromo.title }}</h2><p>{{ activePromo.description }}</p><div class="promo-benefit"><strong>{{ activePromo.highlight }}</strong><span>• {{ activePromo.chip }}</span></div><button class="btn btn-yellow" @click="navigate('pools')">Ver bolões <ArrowRight :size="16" /></button></div>
              <div class="promo-art" aria-hidden="true"><div class="promo-glow"></div><div class="promo-orb promo-orb-one"></div><div class="promo-orb promo-orb-two"></div><div class="promo-ticket"><component :is="activePromo.icon" :size="28" /></div><div class="promo-amount">{{ activePromo.amount }}</div><div class="promo-art-caption">prêmio em destaque*</div></div>
            </article>
          </Transition>
        </div>
        <div class="promo-controls"><button class="promo-arrow" aria-label="Promoção anterior" @click="previousPromo"><ChevronLeft :size="17" /></button><div class="promo-dots"><button v-for="(promo, index) in promoSlides" :key="promo.key" class="promo-dot" :class="{ active: activePromoIndex === index }" :aria-label="`Ver promoção ${index + 1}`" @click="selectPromo(index)"></button></div><button class="promo-arrow" aria-label="Próxima promoção" @click="nextPromo"><ChevronRight :size="17" /></button><span class="promo-counter">{{ activePromoIndex + 1 }} / {{ promoSlides.length }}</span></div>
      </section>
      <div class="section-head product-section-head"><div><div class="eyebrow" style="color:var(--purple)">Catálogo completo</div><h2>Todos os <span>produtos</span></h2><p>Escolha sua modalidade, confira o próximo concurso e monte seu cupom.</p></div><button class="link" @click="navigate('games')">Ver detalhes dos jogos <ChevronRight :size="14" /></button></div>
      <section class="product-grid"><article v-for="game in catalog" :key="game.id" class="product-card" :style="{ '--game-color': game.color }"><div class="product-card-head"><div class="product-brand"><div class="product-logo"><component :is="gameIcon(game)" :size="19" /></div><strong>{{ game.name }}</strong></div><button class="favorite" aria-label="Salvar jogo" @click.stop="notify('Jogo salvo nos favoritos.')"><Heart :size="15" /></button></div><div class="product-prize-label">Prêmio estimado do concurso {{ game.next_draw?.contest_number ?? '—' }}</div><strong class="product-prize">{{ estimatedPrize(game) }}</strong><div class="product-meta">Sorteio {{ drawDateTime(game.next_draw?.draw_at) }}</div><div class="product-meta product-cutoff">Apostas abertas até o próximo concurso</div><button class="product-cta" @click.stop="chooseGame(game)">A partir de {{ money(game.price_cents) }} <ArrowRight :size="15" /></button></article></section>
      <div class="section-head combo-section-head"><div><div class="eyebrow" style="color:var(--purple)">Escolhas prontas</div><h2>Combos de apostas</h2><p>Junte modalidades no mesmo pedido. Cada cupom aparece separado no seu carrinho.</p></div><button class="link" @click="navigate('combos')">Ver todos os combos <ChevronRight :size="14" /></button></div>
      <section class="combo-grid"><article v-for="combo in comboOffers" :key="combo.id" class="combo-card" :style="{ '--combo-color': combo.color }"><div class="combo-card-head"><div class="combo-icon"><component :is="combo.icon" :size="20" /></div><span>{{ combo.eyebrow }}</span></div><h3>{{ combo.title }}</h3><p>{{ combo.description }}</p><div class="combo-tags"><span v-for="tag in combo.tags" :key="tag">{{ tag }}</span></div><div class="combo-card-foot"><div><small>Total dos jogos</small><strong>{{ money(comboPrice(combo)) }}</strong></div><button class="product-cta combo-cta" @click.stop="addComboToCart(combo)">Adicionar <Plus :size="15" /></button></div></article></section>
      <div class="section-head dream-heading"><div><div class="eyebrow" style="color:var(--purple)">Imagine o seu próximo capítulo</div><h2>O que você faria com um prêmio?</h2><p>Inspiração para jogar com responsabilidade — sem promessa de ganho.</p></div></div>
      <section class="dream-grid"><article class="dream-card dream-house"><House :size="27" /><strong>Uma casa nova</strong><span>mais espaço para viver seus planos</span></article><article class="dream-card dream-car"><CarFront :size="27" /><strong>O carro dos sonhos</strong><span>liberdade para ir mais longe</span></article><article class="dream-card dream-money"><Banknote :size="27" /><strong>Dinheiro organizado</strong><span>tranquilidade para o futuro</span></article><article class="dream-card dream-project"><WalletCards :size="27" /><strong>Seu grande projeto</strong><span>um começo para novas histórias</span></article></section>
      <section class="feature-row"><article class="feature"><div class="feature-icon"><ShieldCheck :size="20" /></div><strong>Jogue com segurança</strong><p>Pagamentos protegidos e acompanhamento claro do status de cada aposta.</p></article><article class="feature"><div class="feature-icon"><Ticket :size="20" /></div><strong>Bolões que cabem no bolso</strong><p>Mais combinações, mais diversão e participação fácil de acompanhar.</p></article><article class="feature"><div class="feature-icon"><Clock3 :size="20" /></div><strong>Resultado sem ansiedade</strong><p>Assim que a Caixa publica, a conferência acontece automaticamente.</p></article></section>
      <div class="section-head"><div><h2>Histórias que inspiram</h2><p>Conteúdo demonstrativo para a experiência da plataforma.</p></div></div>
      <section class="testimonials"><article v-for="(quote, index) in [{ name: 'Camila R.', month: 'Junho · demonstração', text: 'O fluxo é leve e eu consigo conferir todas as minhas apostas sem perder o horário do sorteio.' }, { name: 'Bruno M.', month: 'Maio · demonstração', text: 'Entrei em um bolão e gostei de ver as cotas, o valor e o status em uma tela só.' }, { name: 'Lívia S.', month: 'Abril · demonstração', text: 'A experiência é simples até para escolher os números e finalizar o pedido.' }]" :key="quote.name" class="quote"><p>“{{ quote.text }}”</p><div class="quote-foot"><div class="avatar">{{ quote.name[0] }}</div><div><strong>{{ quote.name }}</strong><small>{{ quote.month }}</small></div></div></article></section>
      <p class="notice" style="margin-top:18px">*Valores e depoimentos exibidos nesta versão são ilustrativos para demonstração do produto e não representam promessa de prêmio ou ganho real.</p>
    </main>

    <main v-else-if="view === 'games'" class="main">
      <div class="section-head"><div><div class="eyebrow" style="color:var(--purple)">Jogos oficiais</div><h2>Monte sua aposta</h2><p>Escolha a modalidade, gere uma Surpresinha ou marque seus números.</p></div><button class="btn btn-primary btn-small" @click="navigate('pools')"><Trophy :size="15" /> Ver bolões</button></div>
      <div class="filters"><button v-for="group in gameGroups" :key="group" class="chip" :class="{ active: selectedFilter === group }" @click="group === 'Bolões' ? navigate('pools') : group === 'Combos' ? navigate('combos') : selectedFilter = group">{{ group }}</button></div>
      <div class="page-grid">
        <section class="panel"><div class="games game-catalog-grid"><article v-for="game in gamesToShow" :key="game.id" class="game-card game-card-large" :style="{ '--game-color': game.color }" @click="chooseGame(game)"><div class="game-top"><div class="game-logo"><component :is="gameIcon(game)" :size="21" /></div><span class="status success">ativo</span></div><h3>{{ game.name }}</h3><div class="sub">{{ game.min_numbers ?? game.numbers_required }}{{ (game.max_numbers ?? game.numbers_required) !== (game.min_numbers ?? game.numbers_required) ? `–${game.max_numbers}` : '' }} números · faixa {{ game.number_min ?? 1 }}–{{ game.range_max }}</div><div class="game-bottom"><div class="game-price">{{ money(game.price_cents) }}</div><div class="game-draw">Concurso<br /><strong>{{ game.next_draw?.contest_number ?? '—' }}</strong><small>{{ drawDateTime(game.next_draw?.draw_at) }}</small></div></div></article></div></section>
        <aside class="panel summary"><div class="panel-title"><div><span class="summary-kicker">Cupom digital</span><h2>{{ selectedGame ? selectedGame.name : 'Sua aposta' }}</h2></div><Sparkles :size="20" color="#ffc94e" /></div>
          <template v-if="selectedGame">
            <p style="color:#ded2f9;font-size:13px;line-height:1.5">{{ selectedGame.selection_mode === 'columns' ? 'Escolha de 1 a 3 dígitos por coluna.' : `Marque entre ${minNumbers} e ${maxNumbers} números.` }} Você selecionou <strong style="color:white">{{ selectedNumberCount }}</strong>.</p>
            <div v-if="selectedGame.selection_mode === 'columns'" class="column-picks"><div v-for="column in columns" :key="column" class="super-column"><label>Coluna {{ column + 1 }}</label><div class="column-digit-row"><button v-for="number in numbers" :key="number" class="column-digit" :class="{ selected: selectedColumns[column]?.includes(number) }" @click="toggleColumnNumber(column, number)">{{ number }}</button></div></div></div>
            <div v-else class="number-grid" style="margin-top:18px"><button v-for="number in numbers" :key="number" class="number" :class="{ selected: selectedNumbers.includes(number) }" @click="toggleNumber(number)">{{ String(number).padStart(2, '0') }}</button></div>
            <div v-if="selectedGame.special_options?.special_type === 'team'" class="special-select"><label for="heart-team">Time do Coração</label><input id="heart-team" v-model="selectedSpecialValue" type="text" maxlength="80" placeholder="Digite o seu time" /></div>
            <div v-if="selectedGame.special_options?.special_type === 'month'" class="special-select"><label>Mês da Sorte</label><select v-model="selectedSpecialValue"><option value="">Selecione o mês</option><option v-for="(month, index) in ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro']" :key="month" :value="String(index + 1)">{{ month }}</option></select></div>
            <div class="summary-total"><span>{{ selectedNumberCount >= minNumbers ? 'Valor da aposta' : 'Aposta mínima' }}</span><strong>{{ formattedAmount }}</strong></div>
            <div class="summary-actions"><button class="btn btn-ghost" :disabled="loading" @click="generateCoupon"><Sparkles :size="15" /> Surpresinha</button><button class="btn btn-yellow" :disabled="loading" @click="addCurrentTicket"><Plus :size="15" /> Adicionar</button></div>
            <button class="cart-callout" @click="cartOpen = true"><ShoppingCart :size="17" /><span>{{ cartCount ? `${cartCount} item(ns) no carrinho` : 'Ver carrinho e pagar' }}</span><ChevronRight :size="16" /></button>
            <div class="notice">Você pode gerar e conferir o cupom sem entrar. Para guardar no carrinho, pagar e receber a confirmação por e-mail, entre ou crie sua conta.</div>
          </template><div v-else class="empty" style="color:#ded2f9">Escolha um jogo ao lado para começar.</div>
        </aside>
      </div>
    </main>

    <main v-else-if="view === 'combos'" class="main">
      <div class="section-head"><div><div class="eyebrow" style="color:var(--purple)">Combinações da semana</div><h2>Monte um combo completo</h2><p>Adicione vários jogos com números aleatórios ao carrinho e pague tudo em uma única etapa.</p></div><button class="btn btn-primary" @click="navigate('games')">Escolher jogo simples <ArrowRight :size="16" /></button></div>
      <section class="combo-grid combo-grid-page"><article v-for="combo in comboOffers" :key="combo.id" class="combo-card combo-card-featured" :style="{ '--combo-color': combo.color }"><div class="combo-card-head"><div class="combo-icon"><component :is="combo.icon" :size="22" /></div><span>{{ combo.eyebrow }}</span></div><h3>{{ combo.title }}</h3><p>{{ combo.description }}</p><div class="combo-tags"><span v-for="tag in combo.tags" :key="tag">{{ tag }}</span></div><div class="combo-card-foot"><div><small>{{ combo.tags.length }} cupons no carrinho</small><strong>{{ money(comboPrice(combo)) }}</strong></div><button class="product-cta combo-cta" @click="addComboToCart(combo)">Adicionar combo <Plus :size="15" /></button></div></article></section>
      <div class="panel combo-how"><div class="combo-how-icon"><ShieldCheck :size="20" /></div><div><strong>Como os combos funcionam</strong><p>Geramos uma seleção aleatória para cada modalidade. O valor exibido é a soma das apostas mínimas, sem promessa de prêmio ou desconto oculto. Você revisa todos os cupons antes do pagamento.</p></div></div>
    </main>

    <main v-else-if="view === 'pools'" class="main"><div class="section-head"><div><div class="eyebrow" style="color:var(--purple)">Mais combinações</div><h2>Bolões em destaque</h2><p>Visualize todas as linhas, cotas e o horário oficial antes de adicionar ao carrinho.</p></div><button class="btn btn-primary" @click="navigate('games')">Fazer aposta simples</button></div><section class="pool-grid"><article v-for="pool in poolOffers" :key="pool.title" class="pool-card game-card" :style="{ '--game-color': pool.color }"><div class="game-top"><div class="game-logo"><Trophy :size="19" /></div><span class="status success">aberto</span></div><div class="pool-pill">{{ pool.game }}</div><h3>{{ pool.title }}</h3><div class="sub">{{ pool.lines.length }} jogos · {{ pool.numbersCount }} números por jogo · {{ pool.shares }}</div><div class="pool-preview-numbers"><b v-for="number in pool.numbers.slice(0, 6)" :key="number">{{ String(number).padStart(2, '0') }}</b><span v-if="pool.numbers.length > 6">+{{ pool.numbers.length - 6 }}</span></div><div class="pool-card-draw">{{ pool.drawLabel }}</div><div class="pool-card-actions"><button class="btn btn-outline btn-small" @click.stop="openPoolDetails(pool)">Ver cotas e números</button><button class="btn btn-primary btn-small" @click.stop="addPoolToCart(pool)"><Plus :size="14" /> Comprar cota</button></div><div class="game-bottom"><div class="game-price">{{ pool.price.toLocaleString('pt-BR',{style:'currency',currency:'BRL'}) }}<small style="font-size:11px;color:var(--muted)"> / cota</small></div><span class="pool-availability">{{ pool.totalShares - pool.soldShares }} livres</span></div></article></section><div class="panel pool-how"><CircleDollarSign color="#5c2db8" /><div><strong>Como funciona</strong><p>Você escolhe uma cota, confere cada linha do bolão e acompanha o concurso. O recibo guarda todos os jogos, não apenas uma combinação.</p></div></div></main>

    <main v-else-if="view === 'admin'" class="main">
      <div class="admin-header"><div><div class="eyebrow" style="color:var(--purple)">Visão administrativa</div><h1>Operação da sorte</h1><p>Receita, exposição, carteiras e liquidações em uma visão financeira.</p></div><div class="admin-header-actions"><button class="btn btn-ghost" :disabled="!adminResults.length" @click="resultsModalOpen = true"><Ticket :size="16" /> Ver resultados</button><button class="btn btn-outline" :disabled="loading" @click="syncAdminResults"><Target :size="16" /> Sincronizar resultados</button><button class="btn btn-primary" @click="loadAdmin"><Clock3 :size="16" /> Atualizar dados</button></div></div>
      <div v-if="adminData?.risk?.test_mode" class="test-mode-banner"><ShieldCheck :size="18" /><div><strong>Homologação ativa</strong><span>Crédito simulado de {{ money(adminData.risk.test_credit_cents) }}. Não representa dinheiro disponível para saque real.</span></div></div>
      <section class="kpis"><article v-for="item in [{ label:'Receita confirmada', value:money(adminData?.kpis?.revenue_cents ?? 0), icon:WalletCards, change:'pagamentos aprovados' },{label:'Prêmios provisionados',value:money(adminData?.kpis?.payout_cents ?? 0),icon:Trophy,change:'com reserva'},{label:'Margem operacional',value:money(adminData?.kpis?.margin_cents ?? 0),icon:BarChart3,change:'receita − prêmios'},{label:'Apostas ativas',value:(adminData?.kpis?.active_bets ?? 0).toLocaleString('pt-BR'),icon:Ticket,change:'em acompanhamento'}]" :key="item.label" class="kpi"><div class="kpi-top"><span>{{ item.label }}</span><component :is="item.icon" :size="18" color="#5c2db8" /></div><strong>{{ item.value }}</strong><small>{{ item.change }}</small></article></section>
      <section class="finance-strip"><article><small>Caixa elegível</small><strong>{{ money(adminData?.risk?.eligible_cash_cents ?? 0) }}</strong><span>apostas e prêmios</span></article><article><small>Passivo das carteiras</small><strong>{{ money(adminData?.finance?.wallet_liability_cents ?? 0) }}</strong><span>saldo + saques bloqueados</span></article><article><small>Saques em revisão</small><strong>{{ money(adminData?.finance?.pending_withdrawals_cents ?? 0) }}</strong><span>KYC/manual</span></article><article><small>Pagamentos pendentes</small><strong>{{ money(adminData?.finance?.pending_payments_cents ?? 0) }}</strong><span>aguardando Stripe</span></article></section>
      <section class="panel admin-pricing"><div class="panel-title"><div><h2>Tabela de preços</h2><p class="panel-subtitle">Configure o valor vendido por modalidade e quantidade de números.</p></div><ShieldCheck :size="19" color="#179980" /></div><div class="price-admin-grid"><article v-for="game in adminPrices" :key="game.id" class="price-admin-card"><div><strong>{{ game.name }}</strong><small>{{ game.min_numbers }}{{ game.max_numbers !== game.min_numbers ? '–' + game.max_numbers : '' }} números · fonte CAIXA</small></div><div class="price-admin-values"><span>A partir de <b>{{ money(game.price_table?.[String(game.min_numbers)] ?? 0) }}</b></span><button class="btn btn-outline btn-small" @click="openPriceEditor(game)">Editar valores</button></div></article></div><div class="notice">O valor comercial nunca pode ficar abaixo do preço oficial da modalidade. Descontos de bolão são controlados separadamente por cota e precisam respeitar a reserva.</div></section>
      <section class="admin-grid"><article class="panel chart"><div class="panel-title"><div><h2>Volume x prêmios</h2><p style="color:var(--muted);font-size:12px;margin-top:4px">Acompanhamento diário</p></div><select class="chip"><option>Últimos 7 dias</option><option>Últimos 30 dias</option></select></div><apexchart type="line" height="255" :options="chartOptions" :series="adminData?.chart ?? fallbackChart" /></article><article class="panel"><div class="panel-title"><div><h2>Controle de reserva</h2><p class="panel-subtitle">O limite acompanha o caixa elegível</p></div><ShieldCheck :size="19" color="#179980" /></div><div class="reserve-meter"><div class="reserve-meter-top"><strong>{{ money(adminData?.risk?.eligible_cash_cents ?? 0) }}</strong><span>disponível</span></div><div class="reserve-track"><div :style="{ width: adminData?.risk?.eligible_cash_cents ? '100%' : '0%' }"></div></div></div><div class="risk-list"><div><span>Reserva mínima</span><strong>{{ money(adminData?.risk?.min_reserve_cents ?? 0) }}</strong></div><div><span>Crédito simulado</span><strong>{{ money(adminData?.risk?.test_credit_cents ?? 0) }}</strong></div></div><div class="notice">Novas apostas são pausadas automaticamente quando a exposição máxima excede a reserva elegível.</div></article></section>
      <section class="panel admin-payouts"><div class="panel-title"><div><h2>Prêmios aguardando conferência</h2><p class="panel-subtitle">O crédito fica bloqueado até completar 24 horas e sua aprovação.</p></div><Trophy :size="19" color="#bf7d00" /></div><div v-if="adminPayouts.length" class="withdrawal-list"><div v-for="payout in adminPayouts.slice(0, 8)" :key="payout.id" class="withdrawal-row"><div><strong>#PRÊMIO-{{ payout.id }} · {{ payout.user?.name ?? 'Cliente' }}</strong><small>{{ payout.bet?.game?.name ?? 'Jogo' }} · {{ money(payout.amount_cents) }} · disponível em {{ payout.credit_available_at ? new Date(payout.credit_available_at).toLocaleString('pt-BR') : 'após conferência' }}</small></div><span class="status" :class="payout.status === 'approved' ? 'success' : 'pending'">{{ payout.status === 'approved' ? 'crédito aprovado' : 'aguardando' }}</span><div v-if="payout.status === 'manual_review'" class="withdrawal-actions"><button class="btn btn-primary btn-small" @click="approvePayout(payout.id, !!adminData?.risk?.test_mode)">{{ adminData?.risk?.test_mode ? 'Aprovar simulação' : 'Aprovar crédito' }}</button></div></div></div><div v-else class="empty">Nenhum prêmio aguardando conferência.</div></section>
      <section class="admin-grid admin-lower"><article class="panel"><div class="panel-title"><h2>Apostas recentes</h2><button class="link" @click="notify('Filtros avançados em breve.')">Ver todas <ChevronRight :size="14" /></button></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Cliente</th><th>Jogo</th><th>Valor</th><th>Status</th></tr></thead><tbody><tr v-for="bet in adminData?.bets ?? []" :key="bet.id"><td><strong>{{ bet.id }}</strong></td><td>{{ bet.player }}</td><td>{{ bet.game }}</td><td>{{ money(bet.amount_cents) }}</td><td><span class="status" :class="bet.status === 'won' ? 'success' : bet.status === 'pending' ? 'pending' : 'success'">{{ bet.status === 'won' ? 'ganhou' : bet.status === 'pending' ? 'aguardando' : 'pago' }}</span></td></tr></tbody></table></div></article><article class="panel"><div class="panel-title"><div><h2>Saques da carteira</h2><p class="panel-subtitle">Revisão antes de qualquer transferência</p></div><WalletCards :size="19" color="#5c2db8" /></div><div v-if="adminWithdrawals.length" class="withdrawal-list"><div v-for="withdrawal in adminWithdrawals.slice(0, 5)" :key="withdrawal.id" class="withdrawal-row"><div><strong>#SAQUE-{{ withdrawal.id }}</strong><small>{{ withdrawal.user?.name ?? 'Cliente' }} · {{ money(withdrawal.amount_cents) }}</small></div><span class="status" :class="withdrawal.status === 'paid' ? 'success' : withdrawal.status === 'rejected' ? 'danger' : 'pending'">{{ withdrawal.status === 'manual_review' ? 'em análise' : withdrawal.status }}</span><div v-if="withdrawal.status === 'manual_review'" class="withdrawal-actions"><button class="link" @click="reviewWithdrawal(withdrawal.id, 'approved')">Aprovar</button><button class="link danger-link" @click="reviewWithdrawal(withdrawal.id, 'rejected')">Rejeitar</button><button v-if="adminData?.risk?.test_mode" class="link" @click="reviewWithdrawal(withdrawal.id, 'paid')">Baixar teste</button></div></div></div><div v-else class="empty">Nenhum saque aguardando revisão.</div></article></section>
    </main>

    <main v-else-if="view === 'profile'" class="main"><div class="profile-cover"><div><div class="eyebrow">Área do cliente</div><h1>Olá, {{ user?.name?.split(' ')[0] }}.</h1><p>Cuide dos seus dados, acompanhe pedidos e deixe o pagamento pronto para o próximo jogo.</p></div><div class="profile-avatar"><UserRound :size="30" /></div></div><div class="profile-grid"><section class="panel"><div class="panel-title"><div><span class="summary-kicker">Conta pessoal</span><h2>Seus dados</h2></div><ShieldCheck :size="20" color="#179980" /></div><div class="profile-data"><div><small>Nome</small><strong>{{ user?.name }}</strong></div><div><small>E-mail</small><strong>{{ user?.email }}</strong></div><div><small>Perfil</small><strong>Cliente verificado para demonstração</strong></div></div><div class="notice">Para operar em produção, complete KYC, aceite os termos e mantenha seus dados de contato atualizados.</div></section><section class="panel payment-panel"><div class="panel-title"><div><span class="summary-kicker">Pagamentos</span><h2>Carteira segura</h2></div><CreditCard :size="20" color="#5c2db8" /></div><p>Cadastre e gerencie seus cartões nesta modal. Os dados são tokenizados pelo Stripe e nunca passam pelo nosso servidor.</p><button class="btn btn-primary" :disabled="cardModalLoading" @click="openCardRegistrationModal"><CreditCard :size="16" /> {{ cardModalLoading ? 'Abrindo formulário...' : savedCards.length ? 'Gerenciar cartões' : 'Cadastrar cartão' }}</button><div v-if="paymentMethodsLoading" class="payment-method-loading">Consultando métodos de pagamento...</div><div v-else-if="savedCards.length" class="saved-card-list profile-saved-cards"><div v-for="card in savedCards" :key="card.id" class="saved-card-row"><CreditCard :size="16" /><span><strong>{{ card.brand }} final {{ card.last4 }}</strong><small>Validade {{ String(card.exp_month).padStart(2, '0') }}/{{ card.exp_year }}</small></span><span class="status success">pronto</span></div></div><div v-else class="payment-method-empty"><CreditCard :size="18" /><span>Nenhum cartão salvo ainda. Cadastre pela modal segura para usar pagamento rápido.</span></div><div class="payment-methods"><span><CreditCard :size="15" /> Cartão salvo</span><span><CircleDollarSign :size="15" /> PIX no checkout</span></div><div class="notice">PIX não precisa ser cadastrado: um novo código é gerado e confirmado em cada pedido. Boleto está temporariamente desativado.</div></section></div><section class="wallet-client panel"><div class="panel-title"><div><span class="summary-kicker">Ganhos e transferências</span><h2>Minha carteira</h2><p class="panel-subtitle">Prêmios confirmados entram aqui após a conferência e o KYC.</p></div><WalletCards :size="22" color="#5c2db8" /></div><div v-if="walletLoading && !walletData" class="empty">Carregando saldo seguro...</div><template v-else><div class="wallet-balance-row"><div><small>Saldo disponível</small><strong>{{ money(walletData?.wallet?.balance_cents ?? 0) }}</strong></div><div><small>Em análise</small><strong>{{ money(walletData?.wallet?.locked_cents ?? 0) }}</strong></div><span class="status success">{{ walletData?.wallet?.status === 'active' ? 'carteira ativa' : 'bloqueada' }}</span></div><div class="wallet-form"><div class="field"><label>Valor do saque (R$)</label><input v-model="withdrawalAmount" inputmode="decimal" placeholder="10,00" /></div><div class="field"><label>Chave PIX</label><input v-model="pixKey" placeholder="CPF, e-mail ou chave aleatória" /></div><button class="btn btn-primary" :disabled="walletLoading" @click="requestWithdrawal">Solicitar transferência <ArrowRight :size="16" /></button></div><div v-if="walletFeedback" class="notice">{{ walletFeedback }}</div><div class="notice">Toda transferência fica em análise manual, depende de KYC e não é executada automaticamente nesta versão.</div><div class="wallet-history"><div class="panel-title"><h3>Últimas movimentações</h3><span class="summary-kicker">Ledger protegido</span></div><div v-if="walletData?.transactions?.length" class="transaction-list"><div v-for="transaction in walletData.transactions.slice(0, 8)" :key="transaction.id"><span :class="transaction.amount_cents >= 0 ? 'transaction-plus' : 'transaction-minus'">{{ transaction.amount_cents >= 0 ? '+' : '' }}{{ money(transaction.amount_cents) }}</span><span>{{ transaction.type === 'prize_credit' ? 'Prêmio creditado' : transaction.type === 'withdrawal_requested' ? 'Saque solicitado' : 'Ajuste de saldo' }}</span><small>{{ new Date(transaction.created_at).toLocaleDateString('pt-BR') }}</small></div></div><div v-else class="empty">Nenhuma movimentação de prêmio ainda.</div></div></template></section><section class="profile-cart panel"><div><span class="summary-kicker">Seu carrinho</span><h2>{{ cartCount ? `${cartCount} item(ns) aguardando` : 'Nenhum item salvo' }}</h2><p>{{ cartCount ? 'Seus cupons continuam salvos neste navegador.' : 'Escolha um jogo ou bolão para começar.' }}</p></div><button class="btn btn-primary" @click="cartOpen = true">Abrir carrinho <ShoppingCart :size="16" /></button></section></main>

    <main v-else class="auth-wrap"><section class="auth-card"><button class="brand" @click="navigate('home')"><span class="brand-mark">✦</span> Loterias Online</button><h1>{{ isRegister ? 'Crie sua conta' : loginPortal === 'admin' ? 'Acesso administrativo' : 'Bem-vindo de volta' }}</h1><p>{{ isRegister ? 'Salve seu carrinho e acompanhe seus cupons em um só lugar.' : loginPortal === 'admin' ? 'Controle sua operação com clareza.' : 'Entre para acompanhar suas apostas e bolões.' }}</p><div v-if="!isRegister" class="filters" style="justify-content:center;margin-top:24px;margin-bottom:0"><button class="chip" :class="{active:loginPortal==='cliente'}" @click="loginPortal='cliente'">Cliente</button><button class="chip" :class="{active:loginPortal==='admin'}" @click="loginPortal='admin'">Admin</button></div><div v-if="isRegister" class="field"><label>Seu nome</label><input v-model="customerName" type="text" placeholder="Como podemos chamar você?" /></div><div class="field"><label>E-mail</label><input v-model="email" type="email" placeholder="voce@email.com" /></div><div class="field"><label>Senha</label><input v-model="password" type="password" placeholder="Mínimo de 8 caracteres" @keyup.enter="isRegister ? submitRegister() : submitLogin()" /></div><div v-if="isRegister" class="field"><label>Confirme a senha</label><input v-model="passwordConfirmation" type="password" placeholder="Repita sua senha" @keyup.enter="submitRegister" /></div><div v-if="isRegister" class="legal-checks"><label class="legal-check"><input v-model="ageConfirmed" type="checkbox" /><span>Confirmo que tenho <strong>18 anos ou mais</strong> e posso utilizar esta plataforma.</span></label><label class="legal-check"><input v-model="termsAccepted" type="checkbox" /><span>Li e aceito os <button type="button" class="inline-link" @click.prevent.stop="termsModalOpen = true">Termos de Uso</button> e a Política de Privacidade.</span></label><p>Jogue com responsabilidade. Resultados e prêmios dependem do concurso oficial e da validação da operação.</p></div><p v-if="loginError" style="color:#bd2856;font-size:12px;margin-top:12px">{{ loginError }}</p><button class="btn btn-primary" :disabled="loading || (isRegister && (!ageConfirmed || !termsAccepted))" @click="isRegister ? submitRegister() : submitLogin()">{{ loading ? 'Aguarde...' : isRegister ? 'Criar conta e continuar' : 'Entrar na conta' }}</button><div class="auth-switch" v-if="!isRegister">Ainda não tem conta? <button @click="openRegister">Criar cadastro</button></div><div class="auth-switch" v-else>Já tem conta? <button @click="openLogin()">Entrar</button></div><div class="notice" v-if="!isRegister"><strong>Demo:</strong> {{ loginPortal === 'admin' ? 'admin@loterias.online' : 'cliente@loterias.online' }} · senha <strong>Loterias@2026!</strong></div><div class="notice" v-else>Ao criar a conta, seu carrinho atual continua salvo. O pagamento só acontece depois da revisão do pedido.</div></section></main>

    <div v-if="termsModalOpen" class="modal-overlay terms-overlay" @click.self="termsModalOpen = false"><section class="payment-modal terms-modal" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title"><div class="modal-head"><div><span class="summary-kicker">Loterias Online · {{ termsVersion }}</span><h2 id="terms-modal-title">Termos de Uso</h2><p>Leia as regras essenciais antes de criar sua conta.</p></div><button class="icon-button" aria-label="Fechar termos" @click="termsModalOpen = false"><X :size="18" /></button></div><div class="terms-body"><div class="terms-highlight"><ShieldCheck :size="19" /><strong>Uso exclusivo para maiores de 18 anos</strong></div><h3>1. Elegibilidade e cadastro</h3><p>A plataforma é destinada exclusivamente a pessoas com 18 anos ou mais. Você deve informar dados verdadeiros, manter sua conta protegida e comunicar qualquer alteração cadastral. Podemos solicitar confirmação de identidade e outros dados para prevenção a fraude e cumprimento de obrigações aplicáveis.</p><h3>2. Apostas e resultados</h3><p>As apostas só ficam registradas após pagamento aprovado e confirmação do pedido. Os resultados considerados são os divulgados oficialmente para o concurso correspondente. Uma tela de prévia ou um cupom ainda não pago não representa aposta válida.</p><h3>3. Pagamentos e créditos</h3><p>Cartões são tokenizados diretamente pelo Stripe. No PIX, o pedido depende da confirmação do pagamento. Prêmios passam por conferência administrativa, validação do resultado e análise de identidade antes de qualquer crédito ou transferência.</p><h3>4. Jogo responsável</h3><p>Não aposte valores que comprometam seu orçamento. A plataforma não promete ganhos, não garante prêmio e pode limitar ou suspender operações quando houver inconsistência, risco financeiro ou necessidade de análise.</p><h3>5. Privacidade e comunicações</h3><p>Usaremos seus dados para operar a conta, processar pedidos, enviar confirmações e cumprir obrigações legais. Comunicações de marketing dependem da sua escolha e podem ser canceladas.</p><h3>6. Atualizações</h3><p>Podemos atualizar estes termos para refletir mudanças no serviço. A versão aceita fica registrada no cadastro para auditoria e transparência.</p><small class="terms-version-label">Versão {{ termsVersion }} · revisão em 07/08/2026 · Este texto é informativo e não substitui orientação jurídica.</small></div><div class="modal-actions"><button class="btn btn-outline" @click="termsModalOpen = false">Fechar</button><button class="btn btn-primary" @click="termsAccepted = true; termsModalOpen = false">Li e aceito os termos <CheckCircle2 :size="16" /></button></div></section></div>

    <div v-if="poolDetailsOpen && selectedPool" class="modal-overlay" @click.self="poolDetailsOpen = false"><section class="payment-modal pool-details-modal" role="dialog" aria-modal="true" aria-labelledby="pool-details-title"><div class="modal-head"><div><span class="summary-kicker" :style="{ color: selectedPool.color }">Bolão · {{ selectedPool.game }}</span><h2 id="pool-details-title">{{ selectedPool.title }}</h2><p>{{ selectedPool.description }}</p></div><button class="icon-button" aria-label="Fechar detalhes do bolão" @click="poolDetailsOpen = false"><X :size="18" /></button></div><div class="pool-detail-summary" :style="{ '--game-color': selectedPool.color }"><div><small>Concurso</small><strong>{{ selectedPool.drawLabel }}</strong></div><div><small>Valor da cota</small><strong>{{ selectedPool.price.toLocaleString('pt-BR',{style:'currency',currency:'BRL'}) }}</strong></div></div><div class="pool-detail-section"><div class="pool-detail-heading"><div><span class="summary-kicker">Recibo completo</span><h3>Todos os jogos da cota</h3></div><span class="status success">{{ selectedPool.lines.length }} jogos · {{ selectedPool.numbersCount }} números</span></div><div class="pool-lines-list"><div v-for="(line, lineIndex) in selectedPool.lines" :key="lineIndex" class="pool-line-row"><span>Jogo {{ String(lineIndex + 1).padStart(2, '0') }}</span><div class="pool-detail-numbers" :style="{ '--game-color': selectedPool.color }"><b v-for="number in line" :key="number">{{ String(number).padStart(2, '0') }}</b></div></div></div></div><div class="pool-detail-section"><div class="pool-detail-heading"><div><span class="summary-kicker">Transparência da cota</span><h3>Cotas do bolão</h3></div><strong class="pool-detail-percent">{{ Math.round(selectedPool.soldShares / selectedPool.totalShares * 100) }}% preenchido</strong></div><div class="share-meter"><div :style="{ width: `${selectedPool.soldShares / selectedPool.totalShares * 100}%`, background: selectedPool.color }"></div></div><div class="share-counts"><span><strong>{{ selectedPool.soldShares }}</strong> cotas vendidas</span><span><strong>{{ selectedPool.totalShares - selectedPool.soldShares }}</strong> disponíveis</span><span><strong>{{ selectedPool.totalShares }}</strong> total</span></div></div><div class="modal-actions"><button class="btn btn-outline" @click="poolDetailsOpen = false">Voltar</button><button class="btn btn-primary" @click="poolDetailsOpen = false; addPoolToCart(selectedPool)"><Plus :size="16" /> Comprar uma cota</button></div><div class="secure-note"><ShieldCheck :size="15" /> Os números e a disponibilidade são atualizados por concurso.</div></section></div>

<div v-if="resultsModalOpen" class="modal-overlay" @click.self="resultsModalOpen = false"><section class="payment-modal results-modal" role="dialog" aria-modal="true" aria-labelledby="results-modal-title"><div class="modal-head"><div><span class="summary-kicker">Fonte oficial · CAIXA</span><h2 id="results-modal-title">Resultados sincronizados</h2><p>Conferência de concurso, números, apostas e cotas da plataforma.</p></div><button class="icon-button" @click="resultsModalOpen = false"><X :size="18" /></button></div><div v-if="adminResults.length" class="results-list"><article v-for="result in adminResults" :key="result.id" class="result-card"><div class="result-card-head"><div><strong>{{ result.game.name }} · concurso {{ result.contest_number }}</strong><small>Sorteio {{ drawDateTime(result.draw_at) }} · sincronizado {{ result.synced_at ? drawDateTime(result.synced_at) : 'agora' }}</small></div><span class="status" :class="result.status === 'settled' ? 'success' : 'pending'">{{ result.status === 'settled' ? 'apurado' : 'resultado recebido' }}</span></div><div class="pool-detail-numbers result-numbers"><b v-for="number in (result.results?.numbers ?? [])" :key="number">{{ String(number).padStart(2, '0') }}</b></div><div class="result-stats"><span>{{ result.bets_count }} apostas</span><span>{{ result.pool_bets }} linhas de bolão</span><span>{{ result.winning_bets }} ganhadoras</span></div><div v-if="result.bets?.length" class="result-bets"><div v-for="bet in result.bets.slice(0, 12)" :key="bet.id"><span>#{{ bet.id }} · {{ bet.player ?? 'Cliente' }}</span><strong>{{ bet.is_pool_share ? `Cota · ${bet.pool ?? 'bolão'}` : 'Jogo simples' }}</strong><small class="result-bet-numbers">{{ flatNumbers(bet.numbers ?? []).map(number => String(number).padStart(2, '0')).join(' · ') }}{{ bet.special_value ? ' · '+bet.special_value : '' }}</small><em :class="bet.status === 'won' ? 'winner' : ''">{{ bet.status === 'won' ? `ganhou ${money(bet.payout_cents)}` : bet.status }}</em></div></div></article></div><div v-else class="empty">Nenhum resultado sincronizado ainda.</div><div class="modal-actions"><button class="btn btn-primary" @click="resultsModalOpen = false">Fechar</button></div><div class="secure-note"><ShieldCheck :size="15" /> A sincronização é idempotente e agenda a apuração automática das apostas pagas.</div></section></div>    <div v-if="cartOpen" class="cart-overlay" @click.self="cartOpen = false"><aside class="cart-drawer"><div class="cart-head"><div><span class="summary-kicker">Pedido</span><h2>Seu carrinho</h2></div><button class="icon-button" @click="cartOpen = false"><X :size="18" /></button></div><div v-if="cartCount" class="cart-items"><article v-for="ticket in cart" :key="ticket.id" class="cart-item"><div class="cart-item-icon" :style="{ background: ticket.game.color }"><component :is="gameIcon(ticket.game)" :size="17" /></div><div class="cart-item-copy"><strong>{{ ticket.kind === 'pool' ? 'Bolão · ' : ticket.kind === 'combo' ? 'Combo · ' : '' }}{{ ticket.game.name }}</strong><small>{{ ticket.combo_title ? `${ticket.combo_title} · ` : '' }}{{ ticketSubtitle(ticket) }}</small></div><strong class="cart-item-price">{{ money(ticket.amount_cents) }}</strong><button class="remove-item" @click="removeCartItem(ticket.id)"><Trash2 :size="15" /></button></article></div><div v-else class="cart-empty"><ShoppingCart :size="35" /><strong>Seu carrinho está vazio</strong><p>Escolha um jogo, bolão ou combo para adicionar seu primeiro cupom.</p><button class="btn btn-primary" @click="cartOpen = false; navigate('games')">Escolher jogo</button></div><div v-if="cartCount" class="cart-footer"><div class="cart-total"><span>Total do pedido</span><strong>{{ cartTotalLabel }}</strong></div><div class="payment-preview"><div><small>Pagamento</small><strong>{{ paymentMethod === 'pix' ? 'PIX' : selectedPaymentMethodId ? `Cartão ${savedCards.find(card => card.id === selectedPaymentMethodId)?.brand ?? ''} final ${savedCards.find(card => card.id === selectedPaymentMethodId)?.last4 ?? ''}` : 'Escolher cartão ou PIX' }}</strong></div><CreditCard v-if="paymentMethod === 'card'" :size="18" /><CircleDollarSign v-else :size="18" /></div><button class="btn btn-primary checkout-button" :disabled="loading" @click="openPaymentModal">{{ loading ? 'Preparando pedido...' : user ? 'Escolher pagamento' : 'Entrar para pagar' }} <ArrowRight :size="16" /></button><button class="clear-cart" @click="clearCart">Limpar carrinho</button><div class="notice">O pedido só é confirmado após aprovação do Stripe e do controle de reserva da operação. Boleto está temporariamente desativado.</div></div></aside></div>

    <div v-if="paymentModalOpen" class="modal-overlay" @click.self="paymentModalOpen = false"><section class="payment-modal" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title"><div class="modal-head"><div><span class="summary-kicker">Checkout seguro</span><h2 id="payment-modal-title">Como você quer pagar?</h2><p>Escolha um cartão da sua conta ou gere um PIX para este pedido.</p></div><button class="icon-button" @click="paymentModalOpen = false"><X :size="18" /></button></div><div class="payment-choice-tabs"><button :class="{ active: paymentMethod === 'card' }" @click="paymentMethod = 'card'"><CreditCard :size="18" /><span>Cartão salvo<small>Pagamento rápido</small></span></button><button :class="{ active: paymentMethod === 'pix' }" @click="paymentMethod = 'pix'"><CircleDollarSign :size="18" /><span>PIX<small>Código por pedido</small></span></button></div><div v-if="paymentMethodsLoading" class="payment-loading"><span class="loading-dot"></span>Consultando seus métodos de pagamento no Stripe...</div><template v-else-if="paymentMethod === 'card'"><div v-if="savedCards.length" class="saved-card-list"><label v-for="card in savedCards" :key="card.id" class="saved-card-option" :class="{ selected: selectedPaymentMethodId === card.id }"><input v-model="selectedPaymentMethodId" type="radio" :value="card.id" name="saved-card" /><span class="saved-card-icon"><CreditCard :size="18" /></span><span class="saved-card-copy"><strong>{{ card.brand }} final {{ card.last4 }}</strong><small>Validade {{ String(card.exp_month).padStart(2, '0') }}/{{ card.exp_year }} · {{ card.funding === 'credit' ? 'crédito' : 'cartão' }}</small></span><CheckCircle2 v-if="selectedPaymentMethodId === card.id" class="saved-card-check" :size="19" /></label></div><div v-else class="payment-method-empty large"><CreditCard :size="25" /><strong>Nenhum cartão cadastrado</strong><span>Cadastre um cartão na modal segura da Loterias Online para usar pagamento rápido.</span><button class="btn btn-outline" :disabled="!paymentMethodsConfigured" @click="openCardRegistration"><CreditCard :size="16" /> Cadastrar cartão</button></div><button v-if="savedCards.length" class="add-card-link" @click="openCardRegistration"><Plus :size="16" /> Cadastrar outro cartão</button></template><div v-else class="pix-payment-card"><div class="pix-payment-icon"><CircleDollarSign :size="25" /></div><div><strong>PIX seguro via Stripe</strong><p>Ao continuar, geraremos o QR Code nesta mesma experiência. O pedido só será confirmado depois do webhook de pagamento aprovado.</p></div></div><div v-if="checkoutFeedback" class="checkout-feedback">{{ checkoutFeedback }}</div><div class="modal-actions"><button class="btn btn-outline" @click="paymentModalOpen = false">Voltar</button><button class="btn btn-primary" :disabled="loading || (paymentMethod === 'card' && !selectedPaymentMethodId)" @click="checkoutCart">{{ loading ? 'Confirmando...' : paymentMethod === 'pix' ? 'Gerar PIX' : 'Pagar com cartão' }} <ArrowRight :size="16" /></button></div><div class="secure-note"><ShieldCheck :size="15" /> Dados do cartão são processados pelo Stripe; a plataforma não armazena número ou código de segurança.</div></section></div>

    <div v-if="cardModalOpen" class="modal-overlay" @click.self="closeCardRegistrationModal"><section class="payment-modal card-registration-modal" role="dialog" aria-modal="true" aria-labelledby="card-modal-title"><div class="modal-head"><div><span class="summary-kicker">Carteira segura</span><h2 id="card-modal-title">Adicionar cartão</h2><p>Cadastre seu cartão nesta modal. O Stripe criptografa os dados diretamente no navegador.</p></div><button class="icon-button" @click="closeCardRegistrationModal"><X :size="18" /></button></div><div class="card-modal-brand"><CreditCard :size="20" /><div><strong>Cartão de crédito ou débito</strong><small>Visa, Mastercard e outras bandeiras aceitas</small></div></div><div v-if="cardModalLoading && !cardElement" class="payment-loading"><span class="loading-dot"></span>Preparando formulário seguro...</div><div id="card-element" class="stripe-card-element"></div><p v-if="cardModalError" class="checkout-feedback">{{ cardModalError }}</p><p v-if="cardModalSuccess" class="card-success"><CheckCircle2 :size="16" /> {{ cardModalSuccess }}</p><div class="modal-actions"><button class="btn btn-outline" @click="closeCardRegistrationModal">Cancelar</button><button class="btn btn-primary" :disabled="cardModalLoading || !cardElement" @click="saveCardFromModal">{{ cardModalLoading ? 'Salvando...' : 'Salvar cartão' }} <ArrowRight :size="16" /></button></div><div class="secure-note"><ShieldCheck :size="15" /> O número do cartão e o CVC não são armazenados pela Loterias Online.</div></section></div>

    <div v-if="paymentModalOpen && pixPayment" class="modal-overlay pix-qr-overlay" @click.self="paymentModalOpen = false"><section class="payment-modal pix-qr-modal" role="dialog" aria-modal="true" aria-labelledby="pix-modal-title"><div class="modal-head"><div><span class="summary-kicker">Pagamento PIX</span><h2 id="pix-modal-title">Escaneie para pagar</h2><p>Use o app do seu banco para ler o QR Code ou copie o código.</p></div><button class="icon-button" @click="paymentModalOpen = false"><X :size="18" /></button></div><img v-if="pixPayment.image_url" class="pix-qr-image" :src="pixPayment.image_url" alt="QR Code PIX do pedido" /><div v-else class="pix-qr-fallback"><CircleDollarSign :size="27" /><span>O Stripe não enviou a imagem do QR Code neste momento.</span><a v-if="pixPayment.hosted_url" :href="pixPayment.hosted_url" target="_blank" rel="noreferrer">Abrir instruções PIX</a></div><div v-if="pixPayment.payload" class="pix-copy-box"><textarea readonly :value="pixPayment.payload" aria-label="Código PIX copia e cola"></textarea><button class="btn btn-outline btn-small" @click="copyPixPayload">Copiar código</button></div><small v-if="pixPayment.expires_at" class="pix-expiry">Expira em {{ new Date(pixPayment.expires_at * 1000).toLocaleString('pt-BR') }}</small><div class="modal-actions"><button class="btn btn-primary" @click="paymentModalOpen = false">Fechar</button></div><div class="secure-note"><ShieldCheck :size="15" /> O pedido só será confirmado após o webhook do Stripe.</div></section></div>
    <div v-if="winnerModalOpen && winningBet" class="winner-celebration" role="dialog" aria-modal="true" aria-labelledby="winner-modal-title">
      <div v-if="fireworksActive" class="fireworks" aria-hidden="true"><span class="firework firework-one"></span><span class="firework firework-two"></span><span class="firework firework-three"></span><span class="firework firework-four"></span></div>
      <section class="winner-modal">
        <button class="winner-close" aria-label="Fechar celebração" @click="closeWinnerCelebration"><X :size="18" /></button>
        <div class="winner-crown"><Trophy :size="30" /></div>
        <span class="winner-eyebrow">Resultado confirmado pelo admin</span>
        <h2 id="winner-modal-title">Parabéns, você ganhou! 🎉</h2>
        <p class="winner-lead">{{ winningBet.is_pool_share ? 'Sua cota premiada está pronta.' : 'Seu cupom premiado foi localizado.' }}</p>
        <div class="winner-ticket" :style="{ '--winner-color': winningBet.game?.color ?? '#5c2db8' }">
          <div class="winner-ticket-head"><strong>{{ winningBet.game?.name ?? 'Jogo premiado' }}</strong><span>Concurso {{ winningBet.draw?.contest_number ?? '—' }}</span></div>
          <div class="winner-numbers"><b v-for="number in winningBet.numbers" :key="number">{{ String(number).padStart(2, '0') }}</b></div>
          <div class="winner-ticket-foot"><span>{{ winningBet.is_pool_share ? 'Cota vencedora' : 'Cupom vencedor' }}</span><strong>{{ money(winningBet.payout_cents) }}</strong></div>
        </div>
        <div class="winner-notice"><CheckCircle2 :size="17" /> Crédito liberado na carteira após aprovação administrativa. A transferência continua sujeita a KYC e revisão.</div>
        <button class="btn btn-primary winner-action" @click="closeWinnerCelebration(); navigate('profile')">Abrir minha carteira e cota <ArrowRight :size="16" /></button>
      </section>
    </div>
    <button v-if="view === 'games' && selectedGame" class="coupon-quick" @click="generateCoupon"><Sparkles :size="15" /> Gerar cupom Surpresinha</button>
    <div v-if="toast" class="toast"><CheckCircle2 :size="16" style="vertical-align:-3px;margin-right:6px" />{{ toast }}</div>
  </div>
    <div v-if="priceModalOpen && selectedAdminPrice" class="modal-overlay" @click.self="priceModalOpen = false"><section class="payment-modal price-modal" role="dialog" aria-modal="true" aria-labelledby="price-modal-title"><div class="modal-head"><div><span class="summary-kicker">Configuração comercial</span><h2 id="price-modal-title">{{ selectedAdminPrice.name }}</h2><p>Valores em reais por quantidade de números.</p></div><button class="icon-button" @click="priceModalOpen = false"><X :size="18" /></button></div><div class="admin-price-editor"><div v-for="(_, count) in selectedAdminPrice.official_price_table" :key="count" class="admin-price-row"><div><strong>{{ count }} números</strong><small>Piso oficial: {{ money(selectedAdminPrice.official_price_table[count]) }}</small></div><input v-model="adminPriceDraft[String(count)]" type="text" inputmode="decimal" aria-label="Preço em reais" /><span>R$</span></div></div><div class="notice">O piso oficial é validado no servidor. Para reduzir abaixo dele, ajuste a política de subsídio e a reserva financeira — não é permitido nesta tela.</div><div class="modal-actions"><button class="btn btn-outline" @click="priceModalOpen = false">Cancelar</button><button class="btn btn-primary" :disabled="loading" @click="saveAdminPrices">{{ loading ? 'Salvando...' : 'Salvar tabela' }} <CheckCircle2 :size="16" /></button></div></section></div>
</template>
