# Research: planejamento-planilha

## Decision 1 — Onde moram os dados da planilha

**Decision**: tabelas próprias (`plan_*` e `planilha_importacoes`), separadas de
`despesas`, `receitas` e `bancos`.

**Rationale**: `despesas`/`receitas` têm observers que movem saldo de conta e
fatura de cartão (`app/Observers/DespesaObserver.php`), e a criação de despesa
no crédito troca a data da compra pelo vencimento da fatura
(`app/Models/Despesa.php`). Gravar a planilha ali alteraria saldos (viola
FR-031) e deformaria datas e valores (viola FR-022/FR-033). A planilha digita
limite utilizado e fatura; `bancos.saldo_cartao` é derivado.

**Alternatives considered**: (a) reaproveitar `despesas`/`receitas` com
`origem=planilha` — rejeitada pelos efeitos colaterais acima; (b) guardar o
arquivo e calcular tudo em tempo de leitura — rejeitada: sem diff, sem
histórico, lento no app.

## Decision 2 — Leitura do .xlsx

**Decision**: leitor próprio (`ZipArchive` + `XMLReader`/`SimpleXML`) que lê
`xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, `xl/sharedStrings.xml` e cada
`xl/worksheets/sheetN.xml`, usando o valor em cache (`<v>`) das células.

**Rationale**: a extensão `zip` já está na imagem (`Dockerfile`); o arquivo de
referência tem 64 KB e 10 abas; só precisamos de valores, não de estilos nem de
recalcular fórmulas. Evita dependência nova (`composer.json` hoje tem 5
pacotes). O mesmo método já leu o arquivo de referência por inteiro em
03/10/2026.

**Alternatives considered**: `phpoffice/phpspreadsheet` — funcional, mas
dependência grande para ler 6 abas de valores.

**Consequência**: colunas calculadas por fórmula (Mês, Limite Disponível, Valor
Parcela, Restantes, Saldo, Falta, % Concluído) não são lidas da planilha: o
sistema as recalcula a partir das colunas digitadas. Datas vêm como número
serial do Excel (base 1899-12-30).

## Decision 3 — Identidade da linha (idempotência)

**Decision**: cada registro guarda `chave` = SHA-1 dos campos de identidade
normalizados + ordinal de repetição, e `conteudo_hash` = SHA-1 de todos os
campos importados. A importação compara por `chave`: ausente no banco → inclui;
presente com `conteudo_hash` diferente → atualiza; presente e igual → mantém;
no banco e ausente do arquivo → remove.

| Aba | Campos de identidade |
|-----|----------------------|
| Lançamentos | data, tipo, descrição, valor previsto |
| Contas Fixas | conta |
| Cartões | cartão |
| Compras Parceladas | compra, cartão, data |
| Dívidas | dívida, credor |
| Metas | meta |

O ordinal distingue linhas idênticas (ex.: três "Trimestral (Grupo soluções)"
de R$ 1.000,00 em dias diferentes têm datas distintas; duas "Devolução dízimo"
no mesmo dia e valor receberiam ordinais 1 e 2).

**Rationale**: a coluna ID só está preenchida em 11 das 52 linhas, então não
serve como chave. A chave por conteúdo é estável quando ela muda o realizado, o
status ou a observação (caso comum), e degrada com segurança (remove + inclui)
quando muda a identidade.

**Alternatives considered**: (a) apagar e reinserir tudo — resultado igual, mas
troca os IDs a cada importação e quebra FR-004; (b) número da linha — quebra ao
inserir uma linha no meio.

## Decision 4 — Normalização de textos da planilha

**Decision**: aparar espaços; comparar sem diferenciar maiúsculas e acentos
apenas para **reconhecer** valores de lista (tipo, forma, status, prioridade);
gravar o texto de categoria e descrição como está escrito (aparado).

| Planilha | Sistema |
|----------|---------|
| Status Pago, Realizado | `concluido` |
| Status Previsto, Pendente | `pendente` |
| Status Ativa / Aberta | gravado como texto informado |
| Forma Cartão, "Cartão de crédito" | `cartao` |
| Forma PIX / Débito / Transferência / Boleto / Dinheiro | `pix` / `debito` / `transferencia` / `boleto` / `dinheiro` |

Tipo fora de Receita/Despesa, prioridade fora de Alta/Média/Baixa, valor não
numérico e data ilegível → linha rejeitada com motivo (FR-003). Forma e status
fora das listas não entram em conta nenhuma, então não derrubam a importação:
são mantidos como escritos (status `outro`) e viram aviso no relatório.

## Decision 5 — Definição única dos números

**Decision**: `App\Services\Planejamento\PlanejamentoService` é o único lugar
que calcula resumo do mês, despesas por categoria, série anual, resumo de
cartões, total de dívidas e progresso de metas. Controllers web, API e o bloco
do painel inicial só o chamam.

- Previsto do mês = Σ `valor_previsto` dos lançamentos da planilha com data no
  mês + Σ previsto das despesas/receitas manuais com vencimento no mês.
- Realizado do mês = Σ `valor_realizado` dos lançamentos da planilha com data no
  mês + Σ `valor` das despesas/receitas manuais com pagamento no mês.
- Economia % = saldo realizado ÷ receita realizada; Comprometimento % = despesa
  realizada ÷ receita realizada; divisor zero → 0.
- Limite disponível = Σ (limite total − limite utilizado) dos cartões com os
  dois valores informados; faturas em aberto = Σ fatura atual com situação
  "Aberta"; % utilizado = Σ utilizado ÷ Σ limite total.
- Saldo de dívidas = Σ saldo atual; progresso médio das metas = média de
  (valor atual ÷ valor alvo).

**Rationale**: constitution IV. As fórmulas reproduzem as da planilha (lidas das
células em 03/10/2026), corrigindo as três que estão quebradas lá (saldo e
economia do Dashboard, Planejamento Anual).

## Decision 6 — Previsto x realizado nos lançamentos manuais

**Decision**: coluna `valor_previsto` (nullable) em `despesas` e `receitas`,
preenchida na baixa quando o valor informado difere do original.
Previsto de um lançamento manual = `valor_previsto ?? valor`.

**Rationale**: FR-009; hoje `FluxoCaixaController` sobrescreve `valor` na baixa
e o previsto se perde.

## Decision 7 — Carga inicial

**Decision**: comando `php artisan planilha:importar {arquivo} {--tenant=}`,
que chama o mesmo `PlanilhaImportService` da tela.

**Rationale**: FR-029 sem caminho de código paralelo.
