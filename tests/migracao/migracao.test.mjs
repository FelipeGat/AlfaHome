// T-005 — Provas dos critérios de aceite da feature migracao-prod-lxc.
//
// Antes do corte de DNS boa parte destes testes FALHA — é o esperado: eles são
// a definição de pronto da migração, não um retrato do estado atual.
// Rode com: node --test tests/migracao/
import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  APP_DIR, BASE, TABELAS_QUENTES, naVps, noHost, noLxc, pega, sql,
} from './_helpers.mjs'

test('@spec:AC-001 o endereço de sempre abre a tela de entrada', async () => {
  const { status } = await pega(`${BASE}/login`)
  assert.equal(status, 200, `${BASE}/login respondeu ${status}`)
})

test('@spec:AC-002 quem responde é o servidor novo, não a VPS', async () => {
  const { status, corpo } = await pega(`${BASE}/whoami.txt`)
  assert.equal(status, 200, `canário respondeu ${status}`)
  assert.equal(corpo.trim(), 'lxc-114', `canário disse "${corpo.trim()}"`)
})

test('@spec:AC-003 o acesso continua seguro (HTTPS)', async () => {
  const alvo = BASE.replace('https://', 'http://')
  const { status, headers } = await pega(`${alvo}/login`)
  assert.ok([301, 302, 307, 308].includes(status), `http respondeu ${status}, esperado redirecionamento`)
  assert.ok(headers.get('location')?.startsWith('https://'), `redirecionou para ${headers.get('location')}`)
  // O https em si já foi exercitado no AC-001: fetch recusa certificado inválido.
})

test('@spec:AC-004 os cadastros conferem dos dois lados', async () => {
  const consulta = TABELAS_QUENTES.map((t) => `SELECT '${t}', COUNT(*) FROM ${t}`).join(' UNION ALL ')
  const [novo, vps] = await Promise.all([sql('novo', consulta), sql('vps', consulta)])
  const paraMapa = (s) => Object.fromEntries(s.split('\n').filter(Boolean).map((l) => l.split('\t')))
  const mNovo = paraMapa(novo)
  const mVps = paraMapa(vps)
  for (const t of TABELAS_QUENTES) {
    assert.equal(mNovo[t], mVps[t], `tabela ${t}: novo=${mNovo[t]} vps=${mVps[t]}`)
  }
})

test('@spec:AC-005 o banco está na versão certa do sistema', async () => {
  const saida = await noLxc('docker exec alfa-home-app php artisan migrate:status 2>&1')
  const pendentes = saida.split('\n').filter((l) => /pending/i.test(l))
  assert.equal(pendentes.length, 0, `há migrations pendentes:\n${pendentes.join('\n')}`)
})

test('@spec:AC-006 os arquivos enviados pelos usuários continuam lá', async () => {
  // Compara storage/app — é onde moram os arquivos DOS USUÁRIOS. O resto de
  // storage/ (framework/sessions, views, cache e os arquivos de manutenção do
  // Laravel) é estado de execução: diverge por definição assim que um servidor
  // atende tráfego e o outro fica parado, e comparar isso mediria a coisa errada.
  const contar = "docker exec alfa-home-app sh -c 'find /var/www/storage/app -type f | wc -l; du -sb /var/www/storage/app | cut -f1'"
  const contarVps = contar
  const [novo, vps] = await Promise.all([noLxc(contar), naVps(contarVps)])
  const [arqNovo, bytesNovo] = novo.split('\n').map((s) => s.trim())
  const [arqVps, bytesVps] = vps.split('\n').map((s) => s.trim())
  assert.equal(arqNovo, arqVps, `arquivos: novo=${arqNovo} vps=${arqVps}`)
  assert.equal(bytesNovo, bytesVps, `bytes: novo=${bytesNovo} vps=${bytesVps}`)
})

test('@spec:AC-007 a entrada no sistema não dá erro de página expirada', async () => {
  const tela = await pega(`${BASE}/login`)
  assert.equal(tela.status, 200)

  const cookies = tela.headers.getSetCookie?.() ?? []
  assert.ok(cookies.length > 0, 'a tela de entrada não devolveu nenhum cookie')
  assert.ok(
    cookies.every((c) => /;\s*secure/i.test(c)),
    `algum cookie veio sem a marca segura: ${cookies.join(' | ')}`,
  )

  const token = tela.corpo.match(/name="_token"\s+value="([^"]+)"/)?.[1]
  assert.ok(token, 'não achei o token do formulário na tela de entrada')

  const corpo = new URLSearchParams({ _token: token, email: 'prova-migracao@exemplo.invalido', password: 'x' })
  const envio = await fetch(`${BASE}/login`, {
    method: 'POST',
    redirect: 'manual',
    headers: {
      'content-type': 'application/x-www-form-urlencoded',
      cookie: cookies.map((c) => c.split(';')[0]).join('; '),
    },
    body: corpo,
  })
  // 419 = a sessão/token não sobreviveu ao caminho até o servidor. Qualquer
  // outra resposta (302 de volta ao formulário, 422) significa que o envio foi
  // aceito e apenas as credenciais estão erradas — que é o esperado aqui.
  assert.notEqual(envio.status, 419, 'o envio do formulário voltou como página expirada (419)')
})

test('@spec:AC-008 os registros mostram o endereço real de quem acessou', async () => {
  const marca = 'PROVA-IP-REAL'
  const ip = '203.0.113.77'
  await noLxc(
    `curl -sS -o /dev/null -H 'Host: home.alfasolucoes.cloud' -H 'CF-Connecting-IP: ${ip}' -A ${marca} http://localhost:8080/login`,
  )
  const linha = await noLxc(`docker logs alfa-home-nginx 2>&1 | grep ${marca} | tail -1`)
  assert.ok(linha.startsWith(ip), `o registro começou com "${linha.split(' ')[0]}", esperado ${ip}`)
})

test('@spec:AC-010 o servidor novo aplica sozinho a versão publicada', async () => {
  const ativo = await noLxc('systemctl is-enabled deploy-tag-watcher.timer 2>&1 || true')
  assert.equal(ativo.trim(), 'enabled', `o vigia de versões está "${ativo.trim()}"`)
  // O vigia grava o resultado em public/deploy-status.json (servido pela web —
  // é dali que o painel AlfaDeploy lê). Fica vermelho até uma tag ser publicada
  // de verdade: só um deploy real prova que a ponta a ponta funciona.
  const estado = await noLxc(`cat ${APP_DIR}/public/deploy-status.json 2>/dev/null || echo "{}"`)
  assert.match(estado, /"estado"\s*:\s*"ok"/, `deploy-status.json não diz ok: ${estado}`)
})

test('@spec:AC-011 a cópia diária do banco novo existe e está fresca', async () => {
  // O backup-dados.sh do host grava em /srv/backup/db (o /srv/dados/backups-vps
  // é outra coisa: o que o host puxava da VPS).
  const saida = await noHost(
    'find /srv/backup/db -name "*alfahome-prod*" -mmin -1560 -printf "%T@ %p\\n" 2>/dev/null | sort -n | tail -1',
  )
  assert.ok(saida.length > 0, 'nenhuma cópia de alfahome-prod com menos de 26h')
})

test('@spec:AC-012 a cópia externa (nuvem) continua sendo enviada', async () => {
  const saida = await noLxc(
    'rclone --config /root/.config/rclone/rclone.conf lsl "$(grep -m1 ^RCLONE_DEST= /opt/alfahome/backup/backup.env | cut -d= -f2-)" 2>&1 | tail -5',
  )
  const hoje = new Date().toISOString().slice(0, 10)
  assert.ok(saida.includes(hoje), `a cópia de hoje (${hoje}) não apareceu no destino externo:\n${saida}`)
})

test('@spec:AC-013 a VPS fica intacta e pronta para reassumir', async () => {
  const containers = await naVps('docker ps --format "{{.Names}}" | sort | tr "\\n" " "')
  for (const c of ['alfa-home-app', 'alfa-home-db', 'alfa-home-nginx', 'alfa-home-redis']) {
    assert.ok(containers.includes(c), `container ${c} não está de pé na VPS: ${containers}`)
  }
  const vigia = await naVps('systemctl is-enabled deploy-tag-watcher.timer 2>&1 || true')
  assert.equal(vigia.trim(), 'disabled', `o vigia da VPS está "${vigia.trim()}" — precisa estar desligado`)
  const dumpFinal = await noHost('ls -1 /srv/dados/backups-vps/alfahome/*final* 2>/dev/null | tail -1')
  assert.ok(dumpFinal.length > 0, 'não há dump final da VPS guardado fora dela')
})
