// Server mock untuk pengembangan frontend tanpa Laravel. Bukan bagian dari production.
import http from 'node:http'
import { randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'

const matrix = JSON.parse(readFileSync(new URL('../../backend/database/data/permissions.json', import.meta.url)))
const PASSWORD = 'pbm-dev-2026'
const PORT = Number(process.env.PORT || 8001)

const accounts = [
  ['admin', 'Admin Contoh', 'admin@pbm.test'],
  ['kps', 'KPS Contoh', 'kps@pbm.test'],
  ['dosen', 'Dosen Contoh Satu', 'dosen1@pbm.test'],
  ['dosen', 'Dosen Contoh Dua', 'dosen2@pbm.test'],
  ['laboran', 'Laboran Contoh', 'laboran@pbm.test'],
  ['mutu', 'Penjaminan Mutu Contoh', 'mutu@pbm.test'],
  ['manajemen', 'Manajemen Contoh', 'manajemen@pbm.test'],
].map(([role, name, email], i) => ({ id: i + 1, role, name, email }))

const tokens = new Map()
const log = []
let logId = 0
const attempts = new Map()

const record = (action, userName) => log.push({ id: ++logId, aksi: action, pengguna: userName ?? null, waktu: new Date().toISOString() })

const permissionsOf = (role) => {
  const def = matrix.roles[role]
  return def.permissions.includes('*') ? Object.keys(matrix.permissions) : def.permissions
}

const present = (u) => ({
  id: u.id, name: u.name, email: u.email, nip: null,
  role: { key: u.role, label: matrix.roles[u.role].label },
  permissions: permissionsOf(u.role),
})

const courses = [1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 5]

const send = (res, status, body) => {
  res.writeHead(status, { 'Content-Type': 'application/json' })
  res.end(JSON.stringify(body))
}

const readBody = (req) => new Promise((resolve) => {
  let raw = ''
  req.on('data', (c) => (raw += c))
  req.on('end', () => { try { resolve(JSON.parse(raw || '{}')) } catch { resolve({}) } })
})

http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://x')
  const auth = (req.headers.authorization || '').replace('Bearer ', '')
  const user = tokens.get(auth)

  if (req.method === 'POST' && url.pathname === '/api/auth/login') {
    const ip = req.socket.remoteAddress
    const n = (attempts.get(ip) ?? 0) + 1
    attempts.set(ip, n)
    setTimeout(() => attempts.delete(ip), 60000).unref()
    if (n > 6) return send(res, 429, { message: 'Too Many Attempts.' })
    const { email, password } = await readBody(req)
    if (!email) return send(res, 422, { message: 'x', errors: { email: ['Email wajib diisi.'] } })
    const found = accounts.find((a) => a.email === email)
    if (!found || password !== PASSWORD) {
      record('auth.login_failed', null)
      return send(res, 422, { message: 'x', errors: { email: ['Email atau kata sandi salah.'] } })
    }
    const token = randomUUID()
    tokens.set(token, found)
    record('auth.login', found.name)
    return send(res, 200, { token, user: present(found) })
  }

  if (!user) return send(res, 401, { message: 'Sesi tidak valid. Silakan masuk kembali.' })

  if (req.method === 'GET' && url.pathname === '/api/auth/me') return send(res, 200, { user: present(user) })

  if (req.method === 'POST' && url.pathname === '/api/auth/logout') {
    record('auth.logout', user.name)
    tokens.delete(auth)
    return send(res, 200, { message: 'Anda telah keluar.' })
  }

  if (req.method === 'GET' && url.pathname === '/api/dashboard') {
    if (!permissionsOf(user.role).includes('dashboard.view')) return send(res, 403, { message: 'Anda tidak memiliki akses ke fitur ini.' })
    const per = {}
    courses.forEach((s) => (per[s] = (per[s] ?? 0) + 1))
    return send(res, 200, {
      stats: { mata_kuliah: courses.length, dosen: accounts.filter((a) => a.role === 'dosen').length, dokumen: 0, semester_aktif: 'Ganjil 2026/2027' },
      mata_kuliah_per_semester: Object.entries(per).map(([semester_ke, total]) => ({ semester_ke: Number(semester_ke), total })),
      dokumen_per_status: ['draft', 'diajukan', 'disahkan', 'ditolak', 'arsip'].map((status) => ({ status, total: 0 })),
      aktivitas_terbaru: [...log].reverse().slice(0, 8),
    })
  }

  send(res, 404, { message: 'Tidak ditemukan.' })
}).listen(PORT, () => console.log(`Server mock berjalan di http://localhost:${PORT}`))
