# Changelog

## AlfaHome — 04/05/2026 — Correção de indisponibilidade total do sistema

### Correções
- O sistema ficou inacessível para todos os usuários devido a uma regra de rede legada no servidor que redirecionava todo o tráfego HTTPS para uma porta inexistente. A regra foi identificada e removida, restaurando o acesso imediatamente. O servidor foi ajustado para evitar que o problema ocorra novamente em futuras reinicializações.

---

## AlfaHome — 21/04/2026 — Cor de destaque atualizada para verde

### Melhorias
- A cor de destaque do sistema (botões, links, indicadores ativos) foi alterada para o verde `#57BA87`.

---

## AlfaHome — 22/04/2026 — Padronização completa dos botões com bordas arredondadas

### Melhorias
- Todos os botões do sistema agora têm bordas totalmente arredondadas (estilo "pill"), incluindo botões de filtro (Hoje, Mês Atual, Limpar filtros) nas telas de Despesas, Receitas e Lançamentos, botões de cupom e convite, botão de permissões de membros, botão de instalação do app e ícone de notificações.

---

## AlfaHome — 21/04/2026 — Visual dos botões atualizado para bordas arredondadas

### Melhorias
- Todos os botões do sistema agora exibem bordas totalmente arredondadas (pill), proporcionando uma aparência mais moderna e consistente em todas as telas.

---

## AlfaHome — 21/04/2026 — Ícone de calendário visível no modo escuro

### Correções
- O ícone de calendário nos campos de data (despesas, receitas, fluxo de caixa e outras telas) agora aparece corretamente no modo escuro, sem ficar invisível sobre o fundo escuro.

## AlfaHome — 15/04/2026 — Tema escuro consistente e sidebar mais acessível

### Novidades
- Os botões **Editar perfil** e **Sair** no rodapé da barra lateral ganharam uma linha própria, com rótulos visíveis e área de clique muito maior. Antes eram ícones minúsculos de ~11px colados ao nome do usuário; agora ficam lado a lado como dois botões confortáveis, fáceis de acertar em um clique. Com a barra lateral recolhida, viram dois quadrados de 36×36 empilhados.

### Melhorias
- **Tema escuro padronizado em todas as telas principais**: Despesas, Receitas, Investimentos, Membros, Fornecedores, Categorias, Indicar Amigos, Bancos, Alertas, Dashboard, Fluxo de Caixa e Lançamentos. Cores de fundo, bordas, textos e ícones agora se adaptam automaticamente ao tema ativo, sem áreas "presas" no claro quando o resto está escuro.
- **Ícones do menu lateral recolhido** ficam maiores e perfeitamente centralizados. Antes apareciam deslocados para a esquerda porque um espaço invisível do rótulo ainda ocupava a linha.

### Correções
- **Ao trocar do tema escuro para o claro**, o fundo da página às vezes continuava escuro até o usuário dar F5. Agora a troca é imediata e completa.
- **Card do C6 Bank** (que tem a cor de identidade preta) voltou a ter a borda superior visível no tema escuro. Antes ficava invisível sobre o fundo escuro e dava a impressão de que o card não tinha borda.
- **Nome, e-mail e botões do usuário** no rodapé da sidebar ficavam quase ilegíveis no tema escuro (cinza muito escuro sobre fundo escuro). Agora usam um cinza mais claro, bem legível.

## AlfaHome — 15/04/2026 — Sair da conta agora funciona mesmo em abas antigas

### Melhorias
- Ao clicar em **Sair**, o usuário agora é levado diretamente para a tela de login (antes ia para a página inicial pública).

### Correções
- Corrigido o erro "Página expirada" (419) que aparecia ao clicar em **Sair** depois de restaurar uma aba antiga do navegador. Agora o sistema encerra a sessão normalmente e leva o usuário de volta para a tela de login, mesmo quando a aba já estava aberta há muito tempo.
- Quando qualquer formulário expira por inatividade, em vez de mostrar uma página de erro genérica, o AlfaHome agora redireciona o usuário de volta com a mensagem "Sua sessão expirou. Recarregue a página e tente novamente.", preservando o que já tinha sido digitado (exceto senhas).

## AlfaHome — 15/04/2026 — Landing page mais leve e rápida

### Melhorias
- Imagens da landing page convertidas para o formato moderno **WebP**, reduzindo o peso das capturas de tela em cerca de 62% (de ~3 MB para ~1,15 MB no total).
- Carregamento inteligente das imagens: só o dashboard do topo é baixado imediatamente; as capturas de tela das próximas seções são carregadas conforme o visitante rola a página.
- Fonte Inter carregada com menos variações (400, 600, 700, 800), cortando ~33% do peso de fontes.
- Preloading prioritário da imagem principal do hero, acelerando a renderização do conteúdo maior da primeira dobra (LCP).

## AlfaHome — 15/04/2026 — Favicon corrigido

### Correções
- Favicon da landing page e das páginas de Política de Privacidade e Termos de Uso voltou a exibir o ícone quadrado do AlfaHome, em vez de uma versão esticada do logotipo horizontal.

## AlfaHome — 15/04/2026 — CTA final, SEO e correção de links quebrados

### Novidades
- Novo bloco de **chamada final** no rodapé da landing ("Pronto para organizar as finanças da sua família?"), em faixa verde, com botão direto para a seção de planos.
- Os planos **Individual**, **Casal** e **Família** agora levam direto para o WhatsApp comercial, com a mensagem de "Quero assinar o plano X" já preenchida. O cliente conversa, confirma e assina sem formulário.
- Landing page passa a incluir metadados completos de **SEO e redes sociais** (Open Graph e Twitter Card): ao compartilhar o link no WhatsApp, Instagram ou Facebook, o preview mostra título, descrição e imagem do dashboard.
- Novos arquivos `robots.txt` e `sitemap.xml` publicados para melhorar a indexação no Google.

### Melhorias
- Tema escuro da landing page agora mantém a alternância de cores de fundo nas seções corretamente — não há mais "faixas" de cor inconsistentes no meio da página.
- Navbar da landing agora inclui o link **Como funciona**, facilitando o acesso direto à seção de passos.

### Correções
- Corrigidos todos os links internos da landing que ainda apontavam para uma antiga seção "Contato", que foi removida. Todos os botões agora têm um destino válido (planos, WhatsApp ou lista de planos).

## AlfaHome — 15/04/2026 — Landing com "Como funciona" e bloco de segurança

### Novidades
- Nova seção **Como funciona** na landing page: explica em 3 passos simples como começar a usar o AlfaHome (criar conta, cadastrar contas e cartões, lançar receitas e despesas).
- Nova seção **Segurança & Privacidade** com destaque para os diferenciais do produto: sem acesso ao banco do cliente, criptografia ponta a ponta, backup diário automático e conformidade com a LGPD. Inclui link direto para a Política de Privacidade.

### Melhorias
- Seção de **Planos** agora aparece com fundo claro no tema light, mantendo a alternância visual entre as seções.
- Rodapé no tema light passa a usar fundo branco com borda sutil, separando-o visualmente do FAQ e quebrando o efeito de "mancha única" que havia antes.
- Fundo do hero ajustado para tons neutros (brancos e cinzas claros), eliminando o azul que destoava da identidade verde do AlfaHome.

## AlfaHome — 15/04/2026 — Ajustes visuais na landing e CNPJ nas páginas legais

### Melhorias
- Seções da landing page agora intercalam corretamente as cores de fundo, deixando a leitura mais fluida e com separação visual clara entre blocos.

### Correções
- CNPJ oficial da Alfa Soluções Tecnológicas incluído nas páginas de Política de Privacidade e Termos de Uso, em conformidade com a LGPD e o Código de Defesa do Consumidor.

## AlfaHome — 15/04/2026 — Landing page finalizada, páginas legais e chat via WhatsApp

### Novidades
- Páginas de **Política de Privacidade** e **Termos de Uso** publicadas em `/politica-privacidade` e `/termos-de-uso`, com conteúdo em linguagem clara, seguindo a LGPD e o Código de Defesa do Consumidor, com alternância de tema claro/escuro.
- Chat da landing agora abre direto no **WhatsApp comercial** com nome e contato já preenchidos na mensagem — atendimento imediato no canal que o cliente já usa.
- Novos links de contato no rodapé: **WhatsApp**, **Instagram** e **e-mail** em botões de ícone, além do endereço da empresa e link para "Criar conta".

### Melhorias
- Landing page agora abre por padrão no **tema claro**, mais alinhado à identidade visual do AlfaHome (o tema escuro continua disponível e é lembrado por visitante).
- Toda a landing foi reescrita para refletir o produto real (gestão financeira familiar): textos, destaques, seções de funcionalidades, planos e FAQ agora descrevem receitas, despesas, cartões, bancos e investimentos — não mais conteúdo de imobiliária.
- Capturas de tela reais do sistema (Dashboard, Despesas, Bancos e Investimentos) substituíram os mockups ilustrativos no hero e nas seções de destaque.
- Preços e limites dos planos Individual, Casal e Família exibidos na landing agora batem exatamente com os cadastrados no sistema.
- Cor de destaque da landing passou do laranja para o **verde da identidade AlfaHome**, e a logo da navbar foi calibrada para ficar proporcional ao restante da barra.
- Rodapé reorganizado: colunas Produto, Contato e Legal, com links funcionais e descrição honesta do serviço.

### Correções
- Removidas seções obsoletas herdadas do template original (Programa de Revenda, Depoimentos e formulário de contato comercial) que não se aplicavam ao AlfaHome.
- Links quebrados no rodapé que apontavam para páginas inexistentes foram corrigidos ou removidos.

## AlfaHome — 13/04/2026 — Correção no lançamento de despesas no cartão de crédito

### Correções
- Corrigido erro ao lançar despesas parceladas no cartão de crédito, que estavam falhando com erro 500.

## AlfaHome — 10/04/2026 — Landing page totalmente redesenhada

### Novidades
- Página de apresentação do AlfaHome completamente refeita com design premium e profissional
- Nova seção com demonstração visual do sistema (dashboard, contratos, financeiro)
- Planos atualizados: Starter, Professional e Enterprise para imobiliárias de todos os tamanhos
- Programa de revenda para parceiros que atendem múltiplas imobiliárias
- Chat integrado para falar com nossa equipe direto pela página

### Melhorias
- Visual idêntico ao padrão dos produtos Alfa Soluções (mesma qualidade do AlfaGym)
- Tema claro e escuro com transição suave
- Página 100% responsiva (celular, tablet e computador)
- Animações de entrada ao rolar a página
- Ao acessar o site, visitantes agora veem a landing page diretamente (sem precisar ir em /landing)

## AlfaHome — 10/04/2026 — Saldo corrigido, ícones e programa de indicação

### Novidades
- Novo programa de indicação: compartilhe seu cupom com amigos e ganhe 20% de desconto na próxima mensalidade quando alguém se cadastrar usando seu código.
- Tela "Indicar Amigos" disponível no menu lateral com seu cupom pessoal, link de convite e histórico de indicações.

### Correções
- O saldo das contas bancárias no dashboard agora é atualizado automaticamente ao marcar lançamentos como pagos ou recebidos.
- Ícones dos bancos na tela de lançamentos agora exibem corretamente os logos (Itaú, Nubank, etc.) em vez de ícones genéricos.

## AlfaHome — 09/04/2026 — Correção no endpoint de polling de manutenção

### Correções
- Corrigido endpoint de verificação de manutenção em tempo real (`/status-manutencao`) que não estava sendo acessível por usuários logados devido a cache interno do servidor

## AlfaHome — 09/04/2026 — Correção no aviso de manutenção agendada

### Correções
- O aviso de manutenção programada (banner laranja no topo) agora é exibido para **todos os usuários** quando há uma manutenção agendada, e não apenas para o administrador

## AlfaHome — 09/04/2026 — Melhorias no modo manutenção

### Melhorias
- Quando o sistema entra em manutenção, os usuários são redirecionados diretamente para a tela de login com um aviso em destaque, em vez de uma tela separada de manutenção
- O aviso na tela de login informa o título e a mensagem configurados pelo administrador

## AlfaHome — 09/04/2026 — Correções no sistema de manutenção e fuso horário

### Correções
- Corrigido problema em que o modo manutenção não ativava corretamente: o sistema usava horário UTC internamente, mas os horários digitados pelo usuário são no fuso de Brasília (BRT). Agora o sistema usa o fuso horário correto (America/Sao_Paulo)
- Adicionado alerta visual no painel de manutenção quando o modo está ativado mas a janela de tempo já expirou, orientando o administrador a redefinir as datas
- Melhorada a validação do formulário: o campo "Fim" agora rejeita datas já passadas, evitando configurações que nunca seriam ativadas
- Corrigidos erros de carregamento de gráficos (Chart.js) e do sistema de instalação como app (PWA) causados pelas novas regras de segurança CSP

## AlfaHome — 09/04/2026 — Correção de compatibilidade do CSP

### Correções
- Resolvido problema que impedia o carregamento correto de gráficos e do sistema de instalação como app (PWA) após a ativação das regras de segurança CSP
- Corrigidos erros no console do browser relacionados ao Chart.js, Alpine.js e beacon do Cloudflare

## AlfaHome — 09/04/2026 — Sistema de manutenção programada

### Novidades
- Novo sistema de manutenção programada: o administrador pode colocar o sistema em manutenção a qualquer momento, com título e mensagem personalizados
- Suporte a agendamento: é possível definir data e hora de início e fim da manutenção — o sistema ativa e desativa automaticamente na janela configurada
- Contador regressivo: usuários veem o tempo restante até o fim da manutenção em tempo real
- Durante a manutenção, o sistema desconecta automaticamente todos os usuários e exibe uma tela informativa
- Administrador (super_admin) continua acessando o sistema normalmente e vê um aviso em destaque enquanto a manutenção está ativa

## AlfaHome — 09/04/2026 — Melhorias de segurança

### Segurança
- Proteção contra ataques de falsificação de origem de rede (IP spoofing): o sistema agora valida corretamente a origem das requisições, impedindo que atacantes simulem endereços confiáveis
- Ativação do HSTS (HTTP Strict Transport Security): browsers são instruídos a usar sempre HTTPS, bloqueando ataques de interceptação SSL
- Adição de Content-Security-Policy (CSP): o browser agora bloqueia automaticamente scripts e recursos de origens não autorizadas, reduzindo a superfície de ataques XSS
- Remoção de permissão CORS excessiva em arquivos de usuário: arquivos financeiros (extratos, comprovantes) não são mais acessíveis por domínios externos
- Correção no arquivo de configuração de exemplo: debug desativado por padrão para evitar exposição acidental de informações sensíveis em novos deploys

## AlfaHome — 09/04/2026 — Sistema de backup automático

### Novidades
- Backup diário automático dos dados do sistema, realizado às 03h (horário de Brasília). Os dados são armazenados com segurança no Google Drive.
- Possibilidade de restaurar o sistema a qualquer momento a partir de um backup anterior, por data.

### Melhorias
- Retenção inteligente de backups: diária por 7 dias, semanal por 4 semanas e mensal por 3 meses — sem acúmulo desnecessário de espaço.
- Notificação via Telegram ao concluir ou falhar o backup.

---

## AlfaHome — 09/04/2026 — Desempenho e estabilidade

### Melhorias
- Sistema agora usa um mecanismo de cache mais rápido para guardar sessões e dados temporários, tornando o carregamento das telas mais ágil após o login.
- Infraestrutura do servidor foi ajustada para reduzir o tempo de resposta de páginas internas.

### Correções
- Resolvido um problema que podia causar instabilidade após atualizações do sistema no servidor (a tela inicial podia ficar indisponível por alguns instantes).

## AlfaHome — 09/04/2026 — Acesso ao sistema e melhorias de navegação

### Correções
- Resolvido um problema que impedia o acesso ao sistema em alguns momentos (erro ao carregar a tela de login).
- Botão de recolher a barra lateral agora responde de forma mais consistente, sem duplo clique acidental.

### Melhorias
- Ícone do aviso de "Instalar AlfaHome" atualizado para melhor reconhecimento visual.
