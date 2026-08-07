const API_URL = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');

export type ApiOptions = RequestInit & { token?: string | null };

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
  const token = options.token ?? localStorage.getItem('lottery_token');
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const response = await fetch(`${API_URL}${path}`, { ...options, headers });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.message || 'Não foi possível completar a solicitação.');
  return payload as T;
}

export function apiUrl() { return API_URL; }

