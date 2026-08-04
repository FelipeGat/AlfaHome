// Utilitários dos testes de migração (feature migracao-prod-lxc).
//
// Estes testes falam com os servidores de verdade: não há mock. É proposital —
// o que está sendo provado é o estado da infraestrutura, não o do código.
// Todos são somente-leitura, exceto uma requisição de login que apenas checa
// se o formulário é aceito.
import { execFile } from 'node:child_process'
import { promisify } from 'node:util'

const exec = promisify(execFile)

export const LXC = process.env.LXC ?? '114'
export const APP_DIR = process.env.APP_DIR ?? '/var/www/alfahome'
export const BASE = process.env.ALFAHOME_URL ?? 'https://home.alfasolucoes.cloud'
export const VPS_HOST = process.env.VPS_HOST ?? ''
export const VPS_PASS = process.env.VPS_PASS ?? ''

/** Roda um comando no host alfa-server (Proxmox). */
export async function noHost(cmd) {
  const { stdout } = await exec('ssh', ['alfa-server', cmd], { maxBuffer: 32 * 1024 * 1024 })
  return stdout.trim()
}

/** Roda um comando dentro do LXC da produção nova. */
export async function noLxc(cmd) {
  return noHost(`pct exec ${LXC} -- bash -lc ${JSON.stringify(cmd)}`)
}

/** Roda um comando na VPS antiga. Exige VPS_HOST e VPS_PASS no ambiente. */
export async function naVps(cmd) {
  if (!VPS_HOST || !VPS_PASS) {
    throw new Error('VPS_HOST e VPS_PASS precisam estar no ambiente para falar com a VPS')
  }
  const { stdout } = await exec(
    'sshpass',
    ['-e', 'ssh', '-o', 'PreferredAuthentications=password', '-o', 'StrictHostKeyChecking=no',
      `root@${VPS_HOST}`, cmd],
    { env: { ...process.env, SSHPASS: VPS_PASS }, maxBuffer: 32 * 1024 * 1024 },
  )
  return stdout.trim()
}

/** Consulta SQL no banco de um dos lados ('novo' = LXC 114, 'vps' = VPS). */
export async function sql(lado, query) {
  const onde = lado === 'vps' ? naVps : noLxc
  const arquivoEnv = lado === 'vps' ? '/var/www/alfahome/.env' : `${APP_DIR}/.env`
  const senha = await onde(`grep -m1 '^MYSQL_ROOT_PASSWORD=' ${arquivoEnv} | cut -d= -f2-`)
  const saida = await onde(
    `docker exec alfa-home-db mysql -uroot -p'${senha}' -N -B -e ${JSON.stringify(query)} alfahome 2>/dev/null`,
  )
  return saida
}

/** GET simples que devolve status, cabeçalhos e corpo. */
export async function pega(url, opcoes = {}) {
  const r = await fetch(url, { redirect: 'manual', ...opcoes })
  return { status: r.status, headers: r.headers, corpo: await r.text() }
}

/** As tabelas que doeriam se perdessem linhas (as de maior volume + as de conta). */
export const TABELAS_QUENTES = [
  'despesas', 'fornecedores', 'receitas', 'categorias',
  'users', 'tenants', 'bancos', 'investimentos', 'familiares', 'migrations',
]
