// T-009 — Prova de que a migração do AlfaHome não afetou os outros endereços
// da zona alfasolucoes.cloud.
//
// O retrato de referência (subdominios-baseline.json) foi capturado ANTES do
// corte de DNS. Este teste refaz as mesmas consultas e exige o mesmo resultado.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const referencia = JSON.parse(
  readFileSync(new URL('./subdominios-baseline.json', import.meta.url), 'utf8'),
)

test('@spec:AC-009 os demais endereços da zona seguem respondendo', async () => {
  const divergencias = []
  for (const [host, esperado] of Object.entries(referencia.hosts)) {
    const r = await fetch(`https://${host}.alfasolucoes.cloud/`, { redirect: 'manual' })
    if (r.status !== esperado.status) {
      divergencias.push(`${host}: antes ${esperado.status}, agora ${r.status}`)
    }
  }
  assert.deepEqual(
    divergencias,
    [],
    `endereço(s) da zona mudaram de comportamento depois do corte:\n${divergencias.join('\n')}`,
  )
})
