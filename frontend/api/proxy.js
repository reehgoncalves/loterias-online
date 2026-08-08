const API_ORIGIN = 'https://loterias-online-api.vercel.app';

const hopByHopHeaders = new Set([
  'connection',
  'content-length',
  'host',
  'keep-alive',
  'transfer-encoding',
  'upgrade',
]);

export default async function handler(req, res) {
  const rawPath = Array.isArray(req.query?.path)
    ? req.query.path.join('/')
    : String(req.query?.path || '');
  const path = rawPath.split('/').map((part) => decodeURIComponent(part)).filter(Boolean).join('/');
  const requestUrl = new URL(req.url, 'http://localhost');
  const target = new URL(`/api/${path}`, API_ORIGIN);

  requestUrl.searchParams.forEach((value, key) => {
    if (key !== 'path') target.searchParams.append(key, value);
  });

  const headers = new Headers();
  for (const [name, value] of Object.entries(req.headers)) {
    if (!hopByHopHeaders.has(name.toLowerCase()) && value) {
      headers.set(name, Array.isArray(value) ? value.join(', ') : value);
    }
  }

  const body = ['GET', 'HEAD'].includes(req.method)
    ? undefined
    : typeof req.body === 'string'
      ? req.body
      : req.body == null
        ? undefined
        : JSON.stringify(req.body);

  try {
    const upstream = await fetch(target, { method: req.method, headers, body });
    upstream.headers.forEach((value, name) => {
      if (!hopByHopHeaders.has(name.toLowerCase())) res.setHeader(name, value);
    });
    res.status(upstream.status).send(Buffer.from(await upstream.arrayBuffer()));
  } catch {
    res.status(502).json({ message: 'Não foi possível conectar à API da plataforma.' });
  }
}
