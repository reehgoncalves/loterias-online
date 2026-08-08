const configuredApiUrl = import.meta.env.VITE_API_URL || (import.meta.env.PROD ? window.location.origin : 'http://127.0.0.1:8000');
const API_URL = configuredApiUrl.replace(/\/$/, '');
const API_PREFIX = import.meta.env.PROD ? '/backend' : '';

export type ApiOptions = RequestInit & { token?: string | null };

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
  const token = options.token ?? localStorage.getItem('lottery_token');
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const requestPath = API_PREFIX ? path.replace(/^\/api/, '') : path;
  const response = await fetch(`${API_URL}${API_PREFIX}${requestPath}`, { ...options, headers, credentials: 'include' });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(payload.message || 'Não foi possível completar a solicitação.') as Error & { status?: number };
    error.status = response.status;
    throw error;
  }
  return payload as T;
}

export function apiUrl() { return API_URL; }
