<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { loadStripe, type Stripe, type StripeCardElement, type StripeElements } from '@stripe/stripe-js';
import { api } from './services/api';
import { ArrowRight, Banknote, BarChart3, CarFront, CheckCircle2, ChevronLeft, ChevronRight, CircleDollarSign, Clock3, CreditCard, Heart, House, Menu, Plus, ShieldCheck, ShoppingCart, Sparkles, Target, Ticket, Trash2, Trophy, UserRound, WalletCards, X } from 'lucide-vue-next';

type View = 'home' | 'games' | 'pools' | 'login' | 'admin' | 'profile';
type Game = { id: number; slug: string; name: string; short_name: string; price_cents: number; color: string; range_max: number; number_min?: number; numbers_required: number; selection_mode?: string; special_options?: { columns?: number }; next_draw?: { id?: number; contest_number: number; draw_at: string } };
type User = { id: number; name: string; email: string; portal: 'admin' | 'cliente'; is_admin?: boolean; has_stripe_customer?: boolean };
type CartTicket = { id: string; game: Game; draw_id?: number; numbers: number[]; amount_cents: number; kind?: 'game' | 'pool'; pool_id?: number; shares?: number };
type SavedCard = { id: string; brand: string; last4: string; exp_month: number | null; exp_year: number | null; funding?: string | null };
type PixPayment = { payment_intent_id: string; payload: string | null; image_url: string | null; hosted_url: string | null; expires_at: number | null };
type PoolCard = { id: number; slug: string; game: string; title: string; shares: string; price: number; draw_id?: number; color: string };
type WalletData = { wallet: { id: number; currency: string; balance_cents: number; locked_cents: number; status: string }; transactions: Array<{ id: number; type: string; amount_cents: number; balance_after_cents: number; status: string; created_at: string }>; withdrawals: Array<{ id: number; amount_cents: number; method: string; status: string; review_note?: string; requested_at: string }> };

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
const adminWithdrawals = ref<any[]>([]);
const adminPayouts = ref<any[]>([]);
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
let cardStripe: Stripe | null = null;
let cardElements: StripeElements | null = null;
let cardElement: StripeCardElement | null = null;
const activePromoIndex = ref(0);
const promoPaused = ref(false);
let promoTimer: number | undefined;

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
function navigate(next: View) { view.value = next; mobileOpen.value = false; if (next === 'admin') loadAdmin(); if (next === 'profile' && user.value) { loadProfile(); loadWallet(); loadPaymentMethods(); } }
function gameIcon(game: Game) { return game.slug === 'mega-sena' ? Sparkles : game.slug === 'lotofacil' ? Ticket : game.slug === 'quina' ? CircleDollarSign : game.slug === 'timemania' ? Trophy : game.slug === 'dia-de-sorte' ? Banknote : game.slug === 'dupla-sena' ? WalletCards : game.slug === 'lotomania' ? Target : ShieldCheck; }
function nextPromo() { activePromoIndex.value = (activePromoIndex.value + 1) % promoSlides.length; }
function previousPromo() { activePromoIndex.value = (activePromoIndex.value - 1 + promoSlides.length) % promoSlides.length; }
function selectPromo(index: number) { activePromoIndex.value = index; }

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
    localStorage.setItem('lottery_token', response.data.access_token); user.value = response.data.profile; isLogin.value = false; navigate('games'); notify(response.data.profile.has_stripe_customer ? 'Cadastro criado e Customer Stripe sincronizado.' : 'Cadastro criado. O Stripe será sincronizado no primeiro pagamento.');
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
  if (paymentMethod.value === 'card' && !selectedPaymentMethodId.value) { checkoutFeedback.value = 'Selecione um cartão salvo ou cadastre um novo cartão para continuar.'; return; }
  if (cart.value.some(ticket => !ticket.draw_id)) return notify('Atualize o catálogo para renovar os concursos do carrinho.');
  checkoutFeedback.value = ''; loading.value = true;
  try {
    const stableCartKey = `cart-${user.value.id}-${cart.value.map(ticket => ticket.id).join('-')}-${paymentMethod.value}-${selectedPaymentMethodId.value ?? 'new'}`;
    const checkout = await api<{ data: { checkout_url?: string; mode?: string; payment_intent_status?: string; client_secret?: string | null; pix?: { payment_intent_id: string; qr_code?: { payload?: string | null; image_url?: string | null; hosted_url?: string | null; expires_at?: number | null } | null } } }>('/api/v1/orders/checkout', { method: 'POST', headers: { 'Idempotency-Key': stableCartKey }, body: JSON.stringify({ tickets: cart.value.map(ticket => ({ game_id: ticket.game.id, draw_id: ticket.draw_id, numbers: ticket.numbers, pool_id: ticket.pool_id, shares: ticket.shares })), method: paymentMethod.value, ...(paymentMethod.value === 'card' && selectedPaymentMethodId.value ? { payment_method_id: selectedPaymentMethodId.value } : {}) }) });
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
    const [dashboard, withdrawals, payouts] = await Promise.all([api<{ data: any }>('/api/v1/admin/dashboard'), api<{ data: { data?: any[] } }>('/api/v1/admin/wallet-withdrawals'), api<{ data: { data?: any[] } }>('/api/v1/admin/payouts')]);
    adminData.value = dashboard.data;
    adminWithdrawals.value = withdrawals.data.data ?? [];
    adminPayouts.value = payouts.data.data ?? [];
  }
  catch { adminData.value = { kpis: { revenue_cents: 3020000, payout_cents: 1240000, margin_cents: 1780000, active_bets: 1842 }, chart: fallbackChart, bets: [{ id: '#LO-10294', player: 'Mariana Costa', game: 'Mega-Sena', amount_cents: 500, status: 'paid' }, { id: '#LO-10293', player: 'Rafael Lima', game: 'Lotofácil', amount_cents: 350, status: 'won' }, { id: '#LO-10292', player: 'João Pedro', game: 'Quina', amount_cents: 300, status: 'pending' }] }; }
}
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
onMounted(async () => { promoTimer = window.setInterval(() => { if (!promoPaused.value) nextPromo(); }, 5200); await loadCatalog(); try { const saved = JSON.parse(localStorage.getItem('lottery_cart') || '[]'); if (Array.isArray(saved)) cart.value = saved; } catch { cart.value = []; } if (localStorage.getItem('lottery_token')) { try { const response = await api<{ data: User }>('/api/v1/me'); user.value = response.data; await loadWallet(); } catch { localStorage.removeItem('lottery_token'); } } });
onBeforeUnmount(() => { if (promoTimer) window.clearInterval(promoTimer); destroyCardElement(); });
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

    <main v-else-if="view === 'admin'" class="main">
      <div class="admin-header"><div><div class="eyebrow" style="color:var(--purple)">Visão administrativa</div><h1>Operação da sorte</h1><p>Receita, exposição, carteiras e liquidações em uma visão financeira.</p></div><button class="btn btn-primary" @click="loadAdmin"><Clock3 :size="16" /> Atualizar dados</button></div>
      <div v-if="adminData?.risk?.test_mode" class="test-mode-banner"><ShieldCheck :size="18" /><div><strong>Homologação ativa</strong><span>Crédito simulado de {{ money(adminData.risk.test_credit_cents) }}. Não representa dinheiro disponível para saque real.</span></div></div>
      <section class="kpis"><article v-for="item in [{ label:'Receita confirmada', value:money(adminData?.kpis?.revenue_cents ?? 0), icon:WalletCards, change:'pagamentos aprovados' },{label:'Prêmios provisionados',value:money(adminData?.kpis?.payout_cents ?? 0),icon:Trophy,change:'com reserva'},{label:'Margem operacional',value:money(adminData?.kpis?.margin_cents ?? 0),icon:BarChart3,change:'receita − prêmios'},{label:'Apostas ativas',value:(adminData?.kpis?.active_bets ?? 0).toLocaleString('pt-BR'),icon:Ticket,change:'em acompanhamento'}]" :key="item.label" class="kpi"><div class="kpi-top"><span>{{ item.label }}</span><component :is="item.icon" :size="18" color="#5c2db8" /></div><strong>{{ item.value }}</strong><small>{{ item.change }}</small></article></section>
      <section class="finance-strip"><article><small>Caixa elegível</small><strong>{{ money(adminData?.risk?.eligible_cash_cents ?? 0) }}</strong><span>apostas e prêmios</span></article><article><small>Passivo das carteiras</small><strong>{{ money(adminData?.finance?.wallet_liability_cents ?? 0) }}</strong><span>saldo + saques bloqueados</span></article><article><small>Saques em revisão</small><strong>{{ money(adminData?.finance?.pending_withdrawals_cents ?? 0) }}</strong><span>KYC/manual</span></article><article><small>Pagamentos pendentes</small><strong>{{ money(adminData?.finance?.pending_payments_cents ?? 0) }}</strong><span>aguardando Stripe</span></article></section>
      <section class="admin-grid"><article class="panel chart"><div class="panel-title"><div><h2>Volume x prêmios</h2><p style="color:var(--muted);font-size:12px;margin-top:4px">Acompanhamento diário</p></div><select class="chip"><option>Últimos 7 dias</option><option>Últimos 30 dias</option></select></div><apexchart type="line" height="255" :options="chartOptions" :series="adminData?.chart ?? fallbackChart" /></article><article class="panel"><div class="panel-title"><div><h2>Controle de reserva</h2><p class="panel-subtitle">O limite acompanha o caixa elegível</p></div><ShieldCheck :size="19" color="#179980" /></div><div class="reserve-meter"><div class="reserve-meter-top"><strong>{{ money(adminData?.risk?.eligible_cash_cents ?? 0) }}</strong><span>disponível</span></div><div class="reserve-track"><div :style="{ width: adminData?.risk?.eligible_cash_cents ? '100%' : '0%' }"></div></div></div><div class="risk-list"><div><span>Reserva mínima</span><strong>{{ money(adminData?.risk?.min_reserve_cents ?? 0) }}</strong></div><div><span>Crédito simulado</span><strong>{{ money(adminData?.risk?.test_credit_cents ?? 0) }}</strong></div></div><div class="notice">Novas apostas são pausadas automaticamente quando a exposição máxima excede a reserva elegível.</div></article></section>
      <section class="panel admin-payouts"><div class="panel-title"><div><h2>Prêmios aguardando conferência</h2><p class="panel-subtitle">O crédito fica bloqueado até completar 24 horas e sua aprovação.</p></div><Trophy :size="19" color="#bf7d00" /></div><div v-if="adminPayouts.length" class="withdrawal-list"><div v-for="payout in adminPayouts.slice(0, 8)" :key="payout.id" class="withdrawal-row"><div><strong>#PRÊMIO-{{ payout.id }} · {{ payout.user?.name ?? 'Cliente' }}</strong><small>{{ payout.bet?.game?.name ?? 'Jogo' }} · {{ money(payout.amount_cents) }} · disponível em {{ payout.credit_available_at ? new Date(payout.credit_available_at).toLocaleString('pt-BR') : 'após conferência' }}</small></div><span class="status" :class="payout.status === 'approved' ? 'success' : 'pending'">{{ payout.status === 'approved' ? 'crédito aprovado' : 'aguardando' }}</span><div v-if="payout.status === 'manual_review'" class="withdrawal-actions"><button class="btn btn-primary btn-small" @click="approvePayout(payout.id, !!adminData?.risk?.test_mode)">{{ adminData?.risk?.test_mode ? 'Aprovar simulação' : 'Aprovar crédito' }}</button></div></div></div><div v-else class="empty">Nenhum prêmio aguardando conferência.</div></section>
      <section class="admin-grid admin-lower"><article class="panel"><div class="panel-title"><h2>Apostas recentes</h2><button class="link" @click="notify('Filtros avançados em breve.')">Ver todas <ChevronRight :size="14" /></button></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Cliente</th><th>Jogo</th><th>Valor</th><th>Status</th></tr></thead><tbody><tr v-for="bet in adminData?.bets ?? []" :key="bet.id"><td><strong>{{ bet.id }}</strong></td><td>{{ bet.player }}</td><td>{{ bet.game }}</td><td>{{ money(bet.amount_cents) }}</td><td><span class="status" :class="bet.status === 'won' ? 'success' : bet.status === 'pending' ? 'pending' : 'success'">{{ bet.status === 'won' ? 'ganhou' : bet.status === 'pending' ? 'aguardando' : 'pago' }}</span></td></tr></tbody></table></div></article><article class="panel"><div class="panel-title"><div><h2>Saques da carteira</h2><p class="panel-subtitle">Revisão antes de qualquer transferência</p></div><WalletCards :size="19" color="#5c2db8" /></div><div v-if="adminWithdrawals.length" class="withdrawal-list"><div v-for="withdrawal in adminWithdrawals.slice(0, 5)" :key="withdrawal.id" class="withdrawal-row"><div><strong>#SAQUE-{{ withdrawal.id }}</strong><small>{{ withdrawal.user?.name ?? 'Cliente' }} · {{ money(withdrawal.amount_cents) }}</small></div><span class="status" :class="withdrawal.status === 'paid' ? 'success' : withdrawal.status === 'rejected' ? 'danger' : 'pending'">{{ withdrawal.status === 'manual_review' ? 'em análise' : withdrawal.status }}</span><div v-if="withdrawal.status === 'manual_review'" class="withdrawal-actions"><button class="link" @click="reviewWithdrawal(withdrawal.id, 'approved')">Aprovar</button><button class="link danger-link" @click="reviewWithdrawal(withdrawal.id, 'rejected')">Rejeitar</button><button v-if="adminData?.risk?.test_mode" class="link" @click="reviewWithdrawal(withdrawal.id, 'paid')">Baixar teste</button></div></div></div><div v-else class="empty">Nenhum saque aguardando revisão.</div></article></section>
    </main>

    <main v-else-if="view === 'profile'" class="main"><div class="profile-cover"><div><div class="eyebrow">Área do cliente</div><h1>Olá, {{ user?.name?.split(' ')[0] }}.</h1><p>Cuide dos seus dados, acompanhe pedidos e deixe o pagamento pronto para o próximo jogo.</p></div><div class="profile-avatar"><UserRound :size="30" /></div></div><div class="profile-grid"><section class="panel"><div class="panel-title"><div><span class="summary-kicker">Conta pessoal</span><h2>Seus dados</h2></div><ShieldCheck :size="20" color="#179980" /></div><div class="profile-data"><div><small>Nome</small><strong>{{ user?.name }}</strong></div><div><small>E-mail</small><strong>{{ user?.email }}</strong></div><div><small>Perfil</small><strong>Cliente verificado para demonstração</strong></div></div><div class="notice">Para operar em produção, complete KYC, aceite os termos e mantenha seus dados de contato atualizados.</div></section><section class="panel payment-panel"><div class="panel-title"><div><span class="summary-kicker">Pagamentos</span><h2>Carteira segura</h2></div><CreditCard :size="20" color="#5c2db8" /></div><p>Cadastre e gerencie seus cartões nesta modal. Os dados são tokenizados pelo Stripe e nunca passam pelo nosso servidor.</p><button class="btn btn-primary" :disabled="cardModalLoading" @click="openCardRegistrationModal"><CreditCard :size="16" /> {{ cardModalLoading ? 'Abrindo formulário...' : savedCards.length ? 'Gerenciar cartões' : 'Cadastrar cartão' }}</button><div v-if="paymentMethodsLoading" class="payment-method-loading">Consultando métodos de pagamento...</div><div v-else-if="savedCards.length" class="saved-card-list profile-saved-cards"><div v-for="card in savedCards" :key="card.id" class="saved-card-row"><CreditCard :size="16" /><span><strong>{{ card.brand }} final {{ card.last4 }}</strong><small>Validade {{ String(card.exp_month).padStart(2, '0') }}/{{ card.exp_year }}</small></span><span class="status success">pronto</span></div></div><div v-else class="payment-method-empty"><CreditCard :size="18" /><span>Nenhum cartão salvo ainda. Cadastre pela modal segura para usar pagamento rápido.</span></div><div class="payment-methods"><span><CreditCard :size="15" /> Cartão salvo</span><span><CircleDollarSign :size="15" /> PIX no checkout</span></div><div class="notice">PIX não precisa ser cadastrado: um novo código é gerado e confirmado em cada pedido. Boleto está temporariamente desativado.</div></section></div><section class="wallet-client panel"><div class="panel-title"><div><span class="summary-kicker">Ganhos e transferências</span><h2>Minha carteira</h2><p class="panel-subtitle">Prêmios confirmados entram aqui após a conferência e o KYC.</p></div><WalletCards :size="22" color="#5c2db8" /></div><div v-if="walletLoading && !walletData" class="empty">Carregando saldo seguro...</div><template v-else><div class="wallet-balance-row"><div><small>Saldo disponível</small><strong>{{ money(walletData?.wallet?.balance_cents ?? 0) }}</strong></div><div><small>Em análise</small><strong>{{ money(walletData?.wallet?.locked_cents ?? 0) }}</strong></div><span class="status success">{{ walletData?.wallet?.status === 'active' ? 'carteira ativa' : 'bloqueada' }}</span></div><div class="wallet-form"><div class="field"><label>Valor do saque (R$)</label><input v-model="withdrawalAmount" inputmode="decimal" placeholder="10,00" /></div><div class="field"><label>Chave PIX</label><input v-model="pixKey" placeholder="CPF, e-mail ou chave aleatória" /></div><button class="btn btn-primary" :disabled="walletLoading" @click="requestWithdrawal">Solicitar transferência <ArrowRight :size="16" /></button></div><div v-if="walletFeedback" class="notice">{{ walletFeedback }}</div><div class="notice">Toda transferência fica em análise manual, depende de KYC e não é executada automaticamente nesta versão.</div><div class="wallet-history"><div class="panel-title"><h3>Últimas movimentações</h3><span class="summary-kicker">Ledger protegido</span></div><div v-if="walletData?.transactions?.length" class="transaction-list"><div v-for="transaction in walletData.transactions.slice(0, 8)" :key="transaction.id"><span :class="transaction.amount_cents >= 0 ? 'transaction-plus' : 'transaction-minus'">{{ transaction.amount_cents >= 0 ? '+' : '' }}{{ money(transaction.amount_cents) }}</span><span>{{ transaction.type === 'prize_credit' ? 'Prêmio creditado' : transaction.type === 'withdrawal_requested' ? 'Saque solicitado' : 'Ajuste de saldo' }}</span><small>{{ new Date(transaction.created_at).toLocaleDateString('pt-BR') }}</small></div></div><div v-else class="empty">Nenhuma movimentação de prêmio ainda.</div></div></template></section><section class="profile-cart panel"><div><span class="summary-kicker">Seu carrinho</span><h2>{{ cartCount ? `${cartCount} item(ns) aguardando` : 'Nenhum item salvo' }}</h2><p>{{ cartCount ? 'Seus cupons continuam salvos neste navegador.' : 'Escolha um jogo ou bolão para começar.' }}</p></div><button class="btn btn-primary" @click="cartOpen = true">Abrir carrinho <ShoppingCart :size="16" /></button></section></main>

    <main v-else class="auth-wrap"><section class="auth-card"><button class="brand" @click="navigate('home')"><span class="brand-mark">✦</span> Loterias Online</button><h1>{{ isRegister ? 'Crie sua conta' : loginPortal === 'admin' ? 'Acesso administrativo' : 'Bem-vindo de volta' }}</h1><p>{{ isRegister ? 'Salve seu carrinho e acompanhe seus cupons em um só lugar.' : loginPortal === 'admin' ? 'Controle sua operação com clareza.' : 'Entre para acompanhar suas apostas e bolões.' }}</p><div v-if="!isRegister" class="filters" style="justify-content:center;margin-top:24px;margin-bottom:0"><button class="chip" :class="{active:loginPortal==='cliente'}" @click="loginPortal='cliente'">Cliente</button><button class="chip" :class="{active:loginPortal==='admin'}" @click="loginPortal='admin'">Admin</button></div><div v-if="isRegister" class="field"><label>Seu nome</label><input v-model="customerName" type="text" placeholder="Como podemos chamar você?" /></div><div class="field"><label>E-mail</label><input v-model="email" type="email" placeholder="voce@email.com" /></div><div class="field"><label>Senha</label><input v-model="password" type="password" placeholder="Mínimo de 8 caracteres" @keyup.enter="isRegister ? submitRegister() : submitLogin()" /></div><div v-if="isRegister" class="field"><label>Confirme a senha</label><input v-model="passwordConfirmation" type="password" placeholder="Repita sua senha" @keyup.enter="submitRegister" /></div><p v-if="loginError" style="color:#bd2856;font-size:12px;margin-top:12px">{{ loginError }}</p><button class="btn btn-primary" :disabled="loading" @click="isRegister ? submitRegister() : submitLogin()">{{ loading ? 'Aguarde...' : isRegister ? 'Criar conta e continuar' : 'Entrar na conta' }}</button><div class="auth-switch" v-if="!isRegister">Ainda não tem conta? <button @click="openRegister">Criar cadastro</button></div><div class="auth-switch" v-else>Já tem conta? <button @click="openLogin()">Entrar</button></div><div class="notice" v-if="!isRegister"><strong>Demo:</strong> {{ loginPortal === 'admin' ? 'admin@loterias.online' : 'cliente@loterias.online' }} · senha <strong>Loterias@2026!</strong></div><div class="notice" v-else>Ao criar a conta, seu carrinho atual continua salvo. O pagamento só acontece depois da revisão do pedido.</div></section></main>

    <div v-if="cartOpen" class="cart-overlay" @click.self="cartOpen = false"><aside class="cart-drawer"><div class="cart-head"><div><span class="summary-kicker">Pedido</span><h2>Seu carrinho</h2></div><button class="icon-button" @click="cartOpen = false"><X :size="18" /></button></div><div v-if="cartCount" class="cart-items"><article v-for="ticket in cart" :key="ticket.id" class="cart-item"><div class="cart-item-icon" :style="{ background: ticket.game.color }"><component :is="gameIcon(ticket.game)" :size="17" /></div><div class="cart-item-copy"><strong>{{ ticket.kind === 'pool' ? 'Bolão · ' : '' }}{{ ticket.game.name }}</strong><small>{{ ticketSubtitle(ticket) }}</small></div><strong class="cart-item-price">{{ money(ticket.amount_cents) }}</strong><button class="remove-item" @click="removeCartItem(ticket.id)"><Trash2 :size="15" /></button></article></div><div v-else class="cart-empty"><ShoppingCart :size="35" /><strong>Seu carrinho está vazio</strong><p>Escolha um jogo ou bolão para adicionar seu primeiro cupom.</p><button class="btn btn-primary" @click="cartOpen = false; navigate('games')">Escolher jogo</button></div><div v-if="cartCount" class="cart-footer"><div class="cart-total"><span>Total do pedido</span><strong>{{ cartTotalLabel }}</strong></div><div class="payment-preview"><div><small>Pagamento</small><strong>{{ paymentMethod === 'pix' ? 'PIX' : selectedPaymentMethodId ? `Cartão ${savedCards.find(card => card.id === selectedPaymentMethodId)?.brand ?? ''} final ${savedCards.find(card => card.id === selectedPaymentMethodId)?.last4 ?? ''}` : 'Escolher cartão ou PIX' }}</strong></div><CreditCard v-if="paymentMethod === 'card'" :size="18" /><CircleDollarSign v-else :size="18" /></div><button class="btn btn-primary checkout-button" :disabled="loading" @click="openPaymentModal">{{ loading ? 'Preparando pedido...' : user ? 'Escolher pagamento' : 'Entrar para pagar' }} <ArrowRight :size="16" /></button><button class="clear-cart" @click="clearCart">Limpar carrinho</button><div class="notice">O pedido só é confirmado após aprovação do Stripe e do controle de reserva da operação. Boleto está temporariamente desativado.</div></div></aside></div>

    <div v-if="paymentModalOpen" class="modal-overlay" @click.self="paymentModalOpen = false"><section class="payment-modal" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title"><div class="modal-head"><div><span class="summary-kicker">Checkout seguro</span><h2 id="payment-modal-title">Como você quer pagar?</h2><p>Escolha um cartão da sua conta ou gere um PIX para este pedido.</p></div><button class="icon-button" @click="paymentModalOpen = false"><X :size="18" /></button></div><div class="payment-choice-tabs"><button :class="{ active: paymentMethod === 'card' }" @click="paymentMethod = 'card'"><CreditCard :size="18" /><span>Cartão salvo<small>Pagamento rápido</small></span></button><button :class="{ active: paymentMethod === 'pix' }" @click="paymentMethod = 'pix'"><CircleDollarSign :size="18" /><span>PIX<small>Código por pedido</small></span></button></div><div v-if="paymentMethodsLoading" class="payment-loading"><span class="loading-dot"></span>Consultando seus métodos de pagamento no Stripe...</div><template v-else-if="paymentMethod === 'card'"><div v-if="savedCards.length" class="saved-card-list"><label v-for="card in savedCards" :key="card.id" class="saved-card-option" :class="{ selected: selectedPaymentMethodId === card.id }"><input v-model="selectedPaymentMethodId" type="radio" :value="card.id" name="saved-card" /><span class="saved-card-icon"><CreditCard :size="18" /></span><span class="saved-card-copy"><strong>{{ card.brand }} final {{ card.last4 }}</strong><small>Validade {{ String(card.exp_month).padStart(2, '0') }}/{{ card.exp_year }} · {{ card.funding === 'credit' ? 'crédito' : 'cartão' }}</small></span><CheckCircle2 v-if="selectedPaymentMethodId === card.id" class="saved-card-check" :size="19" /></label></div><div v-else class="payment-method-empty large"><CreditCard :size="25" /><strong>Nenhum cartão cadastrado</strong><span>Cadastre um cartão na modal segura da Loterias Online para usar pagamento rápido.</span><button class="btn btn-outline" :disabled="!paymentMethodsConfigured" @click="openCardRegistration"><CreditCard :size="16" /> Cadastrar cartão</button></div><button v-if="savedCards.length" class="add-card-link" @click="openCardRegistration"><Plus :size="16" /> Cadastrar outro cartão</button></template><div v-else class="pix-payment-card"><div class="pix-payment-icon"><CircleDollarSign :size="25" /></div><div><strong>PIX seguro via Stripe</strong><p>Ao continuar, geraremos o QR Code nesta mesma experiência. O pedido só será confirmado depois do webhook de pagamento aprovado.</p></div></div><div v-if="checkoutFeedback" class="checkout-feedback">{{ checkoutFeedback }}</div><div class="modal-actions"><button class="btn btn-outline" @click="paymentModalOpen = false">Voltar</button><button class="btn btn-primary" :disabled="loading || (paymentMethod === 'card' && !selectedPaymentMethodId)" @click="checkoutCart">{{ loading ? 'Confirmando...' : paymentMethod === 'pix' ? 'Gerar PIX' : 'Pagar com cartão' }} <ArrowRight :size="16" /></button></div><div class="secure-note"><ShieldCheck :size="15" /> Dados do cartão são processados pelo Stripe; a plataforma não armazena número ou código de segurança.</div></section></div>

    <div v-if="cardModalOpen" class="modal-overlay" @click.self="closeCardRegistrationModal"><section class="payment-modal card-registration-modal" role="dialog" aria-modal="true" aria-labelledby="card-modal-title"><div class="modal-head"><div><span class="summary-kicker">Carteira segura</span><h2 id="card-modal-title">Adicionar cartão</h2><p>Cadastre seu cartão nesta modal. O Stripe criptografa os dados diretamente no navegador.</p></div><button class="icon-button" @click="closeCardRegistrationModal"><X :size="18" /></button></div><div class="card-modal-brand"><CreditCard :size="20" /><div><strong>Cartão de crédito ou débito</strong><small>Visa, Mastercard e outras bandeiras aceitas</small></div></div><div v-if="cardModalLoading && !cardElement" class="payment-loading"><span class="loading-dot"></span>Preparando formulário seguro...</div><div id="card-element" class="stripe-card-element"></div><p v-if="cardModalError" class="checkout-feedback">{{ cardModalError }}</p><p v-if="cardModalSuccess" class="card-success"><CheckCircle2 :size="16" /> {{ cardModalSuccess }}</p><div class="modal-actions"><button class="btn btn-outline" @click="closeCardRegistrationModal">Cancelar</button><button class="btn btn-primary" :disabled="cardModalLoading || !cardElement" @click="saveCardFromModal">{{ cardModalLoading ? 'Salvando...' : 'Salvar cartão' }} <ArrowRight :size="16" /></button></div><div class="secure-note"><ShieldCheck :size="15" /> O número do cartão e o CVC não são armazenados pela Loterias Online.</div></section></div>

    <div v-if="paymentModalOpen && pixPayment" class="modal-overlay pix-qr-overlay" @click.self="paymentModalOpen = false"><section class="payment-modal pix-qr-modal" role="dialog" aria-modal="true" aria-labelledby="pix-modal-title"><div class="modal-head"><div><span class="summary-kicker">Pagamento PIX</span><h2 id="pix-modal-title">Escaneie para pagar</h2><p>Use o app do seu banco para ler o QR Code ou copie o código.</p></div><button class="icon-button" @click="paymentModalOpen = false"><X :size="18" /></button></div><img v-if="pixPayment.image_url" class="pix-qr-image" :src="pixPayment.image_url" alt="QR Code PIX do pedido" /><div v-else class="pix-qr-fallback"><CircleDollarSign :size="27" /><span>O Stripe não enviou a imagem do QR Code neste momento.</span><a v-if="pixPayment.hosted_url" :href="pixPayment.hosted_url" target="_blank" rel="noreferrer">Abrir instruções PIX</a></div><div v-if="pixPayment.payload" class="pix-copy-box"><textarea readonly :value="pixPayment.payload" aria-label="Código PIX copia e cola"></textarea><button class="btn btn-outline btn-small" @click="copyPixPayload">Copiar código</button></div><small v-if="pixPayment.expires_at" class="pix-expiry">Expira em {{ new Date(pixPayment.expires_at * 1000).toLocaleString('pt-BR') }}</small><div class="modal-actions"><button class="btn btn-primary" @click="paymentModalOpen = false">Fechar</button></div><div class="secure-note"><ShieldCheck :size="15" /> O pedido só será confirmado após o webhook do Stripe.</div></section></div>
    <button v-if="view === 'games' && selectedGame" class="coupon-quick" @click="generateCoupon"><Sparkles :size="15" /> Gerar cupom Surpresinha</button>
    <div v-if="toast" class="toast"><CheckCircle2 :size="16" style="vertical-align:-3px;margin-right:6px" />{{ toast }}</div>
  </div>
</template>
