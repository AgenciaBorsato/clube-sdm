# CLAUDE.md — Agência Borsato (Contexto Completo e Sem Filtro)

> Este arquivo consolida TUDO que foi feito, discutido, configurado e aprendido nas sessões
> anteriores. Coloque na raiz da pasta onde abre o Claude Code para ter toda a memória da agência.

---

## Quem é

- **Nome:** Wladimir Borsato
- **Email:** wladimirborsato@gmail.com
- **Negócio:** Agência Borsato — agência de marketing digital que gerencia sites WordPress de clientes
- **Idioma de trabalho:** Português (BR)
- **Ambiente principal:** MacBook (está migrando para outro MacBook)
- **Ferramentas que usa:** Claude Code, Claude Cowork (Desktop), GitHub Desktop, cPanel/Softaculous

---

## Hospedagem — Napoleon / cPanel

- **Hospedagem:** Napoleon (hospedagem compartilhada)
- **Usuário cPanel:** wladborsato
- **Domínio primário:** wladborsato.com.br
- **IP do servidor:** 186.209.113.106
- **Diretório home:** /home/wladborsato
- **Disco usado:** 61,4 GB / 97,66 GB (62,88%)
- **Banco de dados usado:** 1,81 GB / 38,06 GB (4,76%)

### Stack tecnológica dos sites

- WordPress 6.9.4
- Elementor v4.0.1
- Elementor Pro v3.25.2
- Yoast SEO (integrado e respondendo via API — lê e ajusta meta tags)
- Jetpack
- MainWP Dashboard (gerenciamento centralizado de todos os sites)

### Funcionalidades do cPanel disponíveis

- WordPress Management
- WordPress Manager by Softaculous
- Gerenciador de Arquivos
- Backup
- Git Version Control
- phpMyAdmin
- Gerenciamento de emails (criar contas, redirecionamentos)
- Gerenciamento de domínios e subdomínios
- Monitoramento de uso de disco e performance

### Acesso ao cPanel — regras

- Login manual necessário a cada sessão (sessão expira ~20-30 minutos de inatividade)
- Não é possível salvar sessões do cPanel entre conversas
- Token de segurança do cPanel pode invalidar durante a sessão
- Para usar o cPanel via Claude: Borsato abre o cPanel no Chrome → avisa que está logado → Claude trabalha via Claude in Chrome
- Dentro da mesma conversa, Claude consegue fazer várias tarefas seguidas; numa conversa nova, precisa relogar

---

## Lista de Sites WordPress (23 sites gerenciados)

Todos hospedados na Napoleon sob o usuário wladborsato. Gerenciados via MainWP Dashboard.

| # | URL | Cliente/Segmento |
|---|-----|-----------------|
| 1 | drwagnermoreno.com.br | Médico / Cirurgião |
| 2-23 | (listados no MainWP e no Softaculous) | Diversos segmentos |

> **Para obter a lista completa atualizada:** acessar o MainWP Dashboard via `wp-json/mainwp/v1/sites`
> ou navegar no Softaculous WordPress Manager no cPanel.
> A lista dos 23 sites com URLs, nomes e segmentos de cada um foi visualizada nos prints do Softaculous.

---

## API REST WordPress — Configuração e Testes

### drwagnermoreno.com.br (Application Password configurada)

- **Application Password:** criada via JavaScript direto no wp-admin (logado como admin, usando cookies de sessão ativa)
- **Endpoint base:** `https://drwagnermoreno.com.br/wp-json/wp/v2/`
- **Usuário admin:** logado como "admin"

### O que foi testado e confirmado funcionando via API:

- **Posts** — leitura e criação confirmados. Post existente encontrado: "Check-up digestivo no começo do ano..." (publicado janeiro 2026)
- **Páginas** — encontradas: "Postar Artigo", "Blog" e "Início"
- **Categorias** — apenas "Uncategorized" existente (oportunidade de organizar melhor)
- **Mídia** — acesso a imagens e arquivos confirmado
- **Yoast SEO** — integrado e respondendo (lê e ajusta meta tags de title e description)

### O que é possível fazer via API REST:

1. Criar e publicar posts com título, conteúdo, categorias, tags e imagem destacada
2. Editar SEO de qualquer página/post (title, meta description) via Yoast
3. Criar categorias e tags para organizar o blog
4. Fazer auditoria SEO completa do site
5. Subir landing pages com a skill especializada

### Exemplos de comandos curl prontos

```bash
# Listar posts
curl -s -u "usuario:XXXX XXXX XXXX XXXX XXXX XXXX" \
  "https://drwagnermoreno.com.br/wp-json/wp/v2/posts"

# Criar post (rascunho)
curl -X POST -u "usuario:XXXX XXXX XXXX XXXX XXXX XXXX" \
  -H "Content-Type: application/json" \
  -d '{"title":"Título","content":"Conteúdo","status":"draft"}' \
  "https://drwagnermoreno.com.br/wp-json/wp/v2/posts"

# Listar páginas
curl -s -u "usuario:XXXX XXXX XXXX XXXX XXXX XXXX" \
  "https://drwagnermoreno.com.br/wp-json/wp/v2/pages"
```

### Como criar Application Password em novos sites

WordPress Admin → Usuários → Perfil → rolar até "Senhas de Aplicação" → criar novo nome → salvar a senha gerada

> **IMPORTANTE:** Application Passwords NÃO expiram — uma vez criada, funciona sempre sem login.
> Para atualizar plugins via API, são necessárias permissões específicas adicionais.

---

## Estratégia de ferramentas — o melhor dos dois mundos

Decisão tomada nas sessões anteriores:

- **Chrome (cPanel/Softaculous)** para tarefas de **infraestrutura**: criar subdomínio, instalar WordPress via Softaculous, gerenciar File Manager, acessar phpMyAdmin, upload de arquivos
- **API REST** para tarefas de **conteúdo**: criar posts, ajustar SEO, gerenciar páginas
- **MainWP** ou **Softaculous WordPress Manager** para atualizar plugins em massa

---

## Comparação Claude Code vs Chat vs Cowork

Discussão feita na sessão anterior para decidir qual ferramenta usar:

### Claude Code (terminal)
- Memória persistente via CLAUDE.md (guarda credenciais, URLs, fluxos)
- Roda comandos direto no computador (curl para APIs)
- Acesso a arquivos locais (criar e salvar landing pages)
- Acesso ao Chrome via Claude in Chrome (beta — oficialmente suportado)
- Pode navegar, clicar, preencher formulários, rodar JavaScript, tirar screenshots
- **Recomendação final: ferramenta principal para gerenciar a agência**

### Claude Chat (claude.ai)
- Projects e Memory para guardar contexto entre conversas
- NÃO roda código, NÃO acessa arquivos, NÃO navega em sites
- Útil para planejar conteúdo e escrever textos apenas

### Cowork (Claude Desktop)
- Chrome + ferramentas de arquivo + código
- Skills customizadas carregam automaticamente
- NÃO tem memória entre conversas (cada sessão começa do zero)
- Skills ficam salvas na máquina e carregam a cada conversa nova

### Decisão
- Claude Code como ferramenta principal (memória + Chrome + código + arquivos)
- Cowork para tarefas que precisem das skills visuais ou conectores específicos

---

## Projeto Borsato CRM — WhatsApp (GitHub)

### Visão geral

- **Nome do sistema:** Borsato CRM
- **Integração:** WhatsApp via Evolution API
- **Deploy:** Railway (redeploy automático a cada commit ~2 minutos)
- **Repositório:** GitHub (acessado via GitHub MCP)
- **Banco de dados:** MySQL (com charset `utf8` — NÃO `utf8mb4`, o que causa problemas com emojis de 4 bytes)

### Arquivos principais do projeto

- **server.js** — arquivo principal do backend (~60KB quando íntegro). Contém TODAS as rotas: leads, kanban, chats, processamento de mensagens, tenants, etc.
- **whatsapp.js** — integração com a Evolution API, envio/recebimento de mensagens
- **api.js** — chamadas do frontend para o backend
- **MiscViews.jsx** — views diversas do frontend
- **SettingsView** — componente de configurações do tenant (onde salva prompt da IA)

### Funcionalidades do sistema

- Leads e kanban
- Chats e processamento de mensagens WhatsApp
- Gerenciamento de tenants (multi-inquilino)
- Assistente IA com prompt/personalidade customizável por tenant
- Webhook para receber mensagens do Evolution API

### Bugs corrigidos

1. **Spam de erros `fetchProfilePicUrl` nos logs** → CORRIGIDO
2. **Sanitização do `aiPrompt` antes de salvar no banco** → CORRIGIDO
   - Problema: emojis e caracteres de 4 bytes que MySQL `utf8` rejeita
   - Solução: função `sanitizeForDb()` aplicada antes do save
   - O catch do endpoint agora faz `console.error('PUT /api/tenants error:', e.message, e.stack)`
3. **Adição de `setWebhook`** no whatsapp.js → IMPLEMENTADO

### Investigação do erro 500 no PUT /api/tenants

- **Endpoint:** `PUT /api/tenants/t1774358038300`
- **Erro:** 500 Internal Server Error ao salvar configurações da IA (botão "Salvar prompt")
- **Causa mais provável:** prompt com emojis/caracteres especiais que MySQL não aceita na coluna `ai_prompt` (criada sem `utf8mb4`)
- **Campos investigados:** `ai_prompt`, `assistant_enabled`, `custom_fields`, `personality`, `ai_enabled`, `settings` JSON
- **No frontend (`SettingsView`):** usa `JSON.parse(tenant.custom_fields || '[]')` que pode falhar se custom_fields estiver corrompido
- **Sugestão discutida:** possível refatoração para `PUT /api/tenants/:id/ai-settings` separada, mas só se ajustar o front junto
- **Logs adicionados:** body recebido, campos montados para update, erro real no catch, stack trace

### Investigação do erro 401 do webhook Evolution API

- Erro 401 do webhook do Evolution em profundidade — investigação iniciada

### INCIDENTE CRÍTICO — server.js substituído

- **O que aconteceu:** Um sub-agente substituiu o server.js inteiro por uma versão incompleta — de 60KB caiu para 31KB. A versão nova tinha rotas de "contacts", "companies", "opportunities" que NÃO existiam no sistema original. Metade do código foi perdida: rotas de leads, kanban, chats, processamento de mensagens.
- **Commits de referência:**
  - `1c73bb7acd` — último commit bom ANTES de qualquer intervenção do Claude
  - `66b66d430b` — outro commit referenciado
  - `d81ac1fd67` — segundo fix, antes das substituições
  - SHA do server.js original: `b1e6c78dc7ac709723cb3952e011404f5271800f` (60,491 bytes)
- **Status:** restauração estava em andamento (buscando server.js do commit correto)

### REGRAS OBRIGATÓRIAS para este projeto

1. **NUNCA substituir o server.js inteiro** — sempre fazer edições pontuais e cirúrgicas
2. **NUNCA usar sub-agentes para editar arquivos grandes** sem supervisão
3. **SEMPRE verificar o tamanho do arquivo** antes e depois de edições (server.js deve ter ~60KB)
4. **SEMPRE fazer backup/commit** antes de qualquer mudança significativa no server.js
5. **SEMPRE verificar** que as rotas existentes (leads, kanban, chats, mensagens) continuam no arquivo após edições

### Prompt de investigação completa (do ChatGPT, usado como referência)

Para futuras investigações no CRM, usar esta checklist:

1. Localizar a rota responsável (ex: `PUT /api/tenants/:id`)
2. Analisar: payload esperado vs payload enviado pelo front
3. Verificar divergência entre nomes de campos (front, backend, banco)
4. Verificar campos obrigatórios sendo sobrescritos com undefined/null
5. Verificar se prompt da IA excede limite de coluna no banco
6. Verificar parse/stringify incorreto em campos JSON
7. Verificar model/schema/migration da tabela
8. Adicionar logs temporários limpos (body, campos do update, erro, stack trace)
9. Campos para procurar no código: `ai prompt`, `assistant prompt`, `personality`, `tenant settings`, `tenant update`, `ai_enabled`, `assistant_enabled`, `settings JSON`

---

## Fluxo de Trabalho Principal — Landing Pages

### Como funciona na agência

1. Cliente contrata landing page
2. Borsato define referência de layout (URL ou briefing)
3. Claude analisa o layout de referência via `web_fetch`
4. Identifica blocos estruturais: hero, benefícios, sobre, depoimentos, FAQ, CTA, rodapé
5. Lista blocos para o usuário confirmar quais manter, remover ou adaptar
6. Anota paleta de cores, tipografia, estilo visual (clean, bold, elegante, popular)
7. Coleta conteúdo bloco a bloco (um de cada vez, nunca tudo junto)
8. Gera arquivo PHP único (HTML + CSS + JS embutidos)
9. Upload no servidor via cPanel/Gerenciador de Arquivos ou FTP
10. Configura como template de página no WordPress

### Fluxo de criação de landing pages envolve

- Criar subdomínios no cPanel quando necessário
- Instalar WordPress via Softaculous quando for site novo
- Subir arquivos PHP no File Manager
- Mexer em banco de dados via phpMyAdmin quando necessário

---

## Skill completa: landing-page-wp

### Etapa 0 — Perguntas obrigatórias antes de começar

Antes de qualquer código, confirmar com o usuário:

1. **URL de referência** — qual site/layout vai servir de base? Se não tiver, pedir que descreva o objetivo e o público da página.
2. **Herdar header/footer do WordPress?** — sim ou não. Se sim, o arquivo usará `get_header()` / `get_footer()`. Se não, a página será completamente standalone.
3. **Identidade visual do cliente** — cores primária/secundária, fontes, logo. **Nunca usar a identidade da Agência Borsato como padrão.**
4. **Objetivo de conversão** — o que a página deve fazer? (WhatsApp, formulário, ligação, cadastro)
5. **Nome do cliente / nicho** — para calibrar tom de copy e elementos visuais.

Se o usuário não responder algum item, perguntar diretamente antes de avançar.

### Etapa 1 — Análise do layout de referência

Se uma URL foi fornecida:
- Usar `web_fetch` para carregar a página.
- Identificar os blocos estruturais: hero, benefícios, sobre, depoimentos, FAQ, CTA, rodapé, etc.
- Listar os blocos encontrados para o usuário confirmar quais manter, remover ou adaptar.
- Anotar: paleta de cores dominante, tipografia, estilo visual (clean, bold, elegante, popular).

Se não houver URL:
- Propor uma estrutura padrão baseada no nicho e objetivo informados.
- Apresentar a estrutura para aprovação antes de coletar conteúdo.

### Etapa 2 — Coleta de conteúdo (bloco a bloco)

Solicitar o conteúdo UM BLOCO POR VEZ, na ordem da página. Para cada bloco:

```
Bloco: [NOME DO BLOCO]
→ Headline principal:
→ Subtítulo (se houver):
→ Texto de apoio:
→ CTA (botão/link):
→ Imagem/foto (descreva ou cole URL):
→ Observações especiais:
```

Não solicitar o próximo bloco até receber confirmação do bloco atual.
Se o usuário não tiver conteúdo pronto, oferecer sugestão de copy baseada no nicho — deixando claro que é rascunho para aprovação.

### Coleta mínima obrigatória

- Nome completo do profissional/empresa e título/especialidade
- Headline principal da página
- Subheadline ou texto de apoio
- CTA principal (texto do botão + destino: link, WhatsApp, formulário)
- Identidade visual: cores primária/secundária/fundo, fonte preferida (se souber)
- Logo (URL ou o usuário vai fornecer depois)

### Etapa 3 — Build da página

#### Estrutura PHP — Standalone (sem herança de tema):

```php
<?php
/*
Template Name: [Nome da Landing Page]
*/
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>[Título da Página]</title>
  <!-- Google Fonts, FontAwesome, etc. via CDN -->
  <style>
    /* Todo CSS aqui — nenhum arquivo externo */
    /* Usar variáveis CSS: --cor-primaria, --cor-secundaria, --cor-fundo, --cor-texto */
  </style>
</head>
<body>
  <!-- BLOCOS DA LANDING PAGE -->
  <script>
    /* Todo JS aqui — nenhum arquivo externo */
  </script>
</body>
</html>
```

#### Estrutura PHP — Herdando header/footer do WordPress:

```php
<?php
/*
Template Name: [Nome da Landing Page]
*/
get_header(); ?>

<style>
  /* CSS específico da landing page */
</style>

<!-- HTML do conteúdo aqui -->

<script>
  // JS aqui
</script>

<?php get_footer(); ?>
```

### Padrões de qualidade obrigatórios

**Responsividade:**
- Mobile-first CSS
- Breakpoints: 480px, 768px, 1024px
- Testar layout em todas as larguras

**Identidade visual:**
- Usar as cores e fontes do cliente, nunca as da Agência Borsato
- Fontes via Google Fonts (link no `<head>` ou `@import`)
- Variáveis CSS para cores: `--cor-primaria`, `--cor-secundaria`, `--cor-fundo`, `--cor-texto`

**Performance:**
- Imagens com `loading="lazy"`
- CSS inline (sem arquivos externos além de Google Fonts)
- JS no final do body
- Sem JS desnecessário. Animações só se acrescentarem percepção de valor.

**Conversão:**
- Botão de WhatsApp flutuante (se aplicável ao cliente)
- CTAs com contraste alto
- Formulário simples se necessário (sem dependência de plugins)
- CTA fixo no mobile: botão de WhatsApp ou ação principal sempre visível

**Acessibilidade básica:**
- `alt` em imagens
- Contraste adequado
- Hierarquia de headings (`h1` único por página)

**Sem frameworks externos** que precisem de instalação (Bootstrap via CDN é aceito se o cliente já usa; preferir CSS puro quando possível).

### Copy — regras de tom

- Sem clichês: nunca usar "alavancar", "impulsionar", "soluções sob medida", "excelência", "inovação"
- Sem travessões (—), sem frases de enchimento
- Direto ao ponto. Benefício antes de feature.
- Para profissionais liberais (médicos, advogados, psicólogos): tom sóbrio, autoridade, confiança.
- Para varejo/serviços populares: tom próximo, direto, urgência real.
- Headlines: foco em transformação ou dor resolvida, não em descrição do serviço.

### Etapa 4 — Validação

Após gerar o código:

1. Apresentar um resumo estrutural da página: quais blocos foram incluídos, CTA principal, número de seções.
2. Checklist rápido:
   - [ ] Identidade visual do cliente aplicada
   - [ ] Responsivo (mobile/tablet/desktop)
   - [ ] CTA principal presente e funcional
   - [ ] WhatsApp configurado com número correto
   - [ ] Template Name correto no comentário PHP
   - [ ] Header/footer: standalone ou herdando conforme solicitado
3. Perguntar: "Quer ajustar algum bloco antes de receber o arquivo final?"
4. Aplicar correções solicitadas.
5. Só então entregar o arquivo.

### Etapa 5 — Entrega

- Salvar como `landing-[nome-do-cliente].php` ou `[slug-do-cliente]-landing.php`
- Exemplos: `dra-mariana-landing.php`, `clinica-wagner-landing.php`
- Informar ao usuário como instalar no WordPress:
  > "Para instalar: acesse o WordPress do cliente → Aparência → Editor de Arquivos de Tema → cole o arquivo, ou envie via FTP/cPanel na pasta `/wp-content/themes/[nome-do-tema]/`. Depois vá em Páginas → Adicionar nova → escolha o template '[Nome da Landing Page]' nas configurações de página."

---

## Segmentos mais frequentes da Agência

| Segmento | CTA usual | Tom | Observações |
|---|---|---|---|
| Médico/Cirurgião | WhatsApp ou formulário | Sóbrio, empático, técnico sem ser hermético | Nunca prometer resultado clínico. Evitar superlativos ("o melhor", "único") |
| Advogado | WhatsApp ou e-mail | Seguro, técnico, sério | Nunca prometer resultado. Evitar "lutamos pelos seus direitos" |
| Nutricionista | WhatsApp | Motivacional mas realista | Empático, sem julgamento |
| Psicólogo | Formulário ou e-mail | Acolhedor, nunca alarmista | Evitar termos clínicos sem explicação |
| Estética | WhatsApp | Aspiracional, feminino, acolhedor | Foco em autoestima e resultado |
| Farmácia | WhatsApp ou mapa | Prático, acessível, confiável | Público amplo, linguagem simples |
| Escola/Curso | Formulário | Motivacional, acolhedor, orientado a futuro | Falar com os pais (escolas infantis) |

### Fontes recomendadas por perfil

| Perfil | Fonte sugerida |
|---|---|
| Executivo/premium | Cormorant Garamond + Manrope |
| Saúde/clínica | Inter + Playfair Display |
| Moderno/tech | Inter + Space Grotesk |
| Tradicional/sóbrio | Lora + Source Sans 3 |

---

## Biblioteca de Blocos por Nicho (completa)

### Médico / Profissional de Saúde

Blocos recomendados (nesta ordem):
1. Hero — foto do profissional + headline de autoridade + CTA WhatsApp
2. Problema/Dor — o que o paciente sente / por que procurou ajuda
3. Solução — o que o tratamento/consulta oferece (foco em resultado, não em técnica)
4. Sobre o médico — formação, especialização, registros (CRM), tempo de experiência
5. Como funciona — passo a passo simples (ícones + texto curto)
6. Depoimentos — 3 a 5 relatos de pacientes (sem fotos se não houver autorização)
7. FAQ — 4 a 6 perguntas frequentes
8. CTA final — agendamento via WhatsApp ou formulário

Tom: sóbrio, empático, técnico sem ser hermético. Evitar superlativos ("o melhor", "único").
Headline padrão (adaptável): "Você merece cuidado de verdade — não só uma consulta rápida."

### Advogado / Escritório de Advocacia

Blocos recomendados:
1. Hero — nome/logo do escritório + área de atuação + CTA (consulta ou contato)
2. Áreas de atuação — cards com ícone + título + 1 linha descritiva
3. Sobre — quem é o advogado, OAB, tempo de atuação, especializações
4. Por que nos escolher — 3 diferenciais objetivos (sem clichês)
5. Depoimentos ou casos resolvidos (sem detalhes processuais)
6. CTA final — botão WhatsApp ou formulário de contato

Tom: seguro, técnico, sério. Nunca prometer resultado. Evitar "lutamos pelos seus direitos".
Headline padrão: "Seu problema jurídico tem solução. Vamos conversar."

### Estética / Clínica de Beleza

Blocos recomendados:
1. Hero — foto de resultado ou ambiente + headline de desejo + CTA
2. Tratamentos — grid de serviços com foto + nome + breve descrição
3. Antes/Depois — galeria (com autorização dos clientes)
4. Sobre a profissional — formação, registro no conselho, experiência
5. Diferenciais — ambiente, produtos usados, protocolos exclusivos
6. Depoimentos — com foto se possível
7. Localização + horários + CTA WhatsApp

Tom: aspiracional, feminino (na maioria dos casos), acolhedor. Foco em autoestima e resultado.
Headline padrão: "Você já é linda. A gente só revela isso."

### Farmácia / Drogaria

Blocos recomendados:
1. Hero — oferta ou serviço principal (delivery, manipulação, dermocosméticos) + CTA
2. Serviços — entrega, manipulação, teste de glicose, aferição de pressão, etc.
3. Destaques / Promoções — produtos em evidência com preço
4. Sobre a farmácia — tempo no mercado, equipe, localização
5. Horários + Contato + Mapa
6. CTA final — WhatsApp para pedidos ou delivery

Tom: prático, acessível, confiável. Público amplo — linguagem simples.

### Escola / Curso / Ensino

Blocos recomendados:
1. Hero — proposta de valor principal + CTA (matricule-se / fale conosco)
2. Para quem é — perfil do aluno ideal
3. O que vai aprender / Grade curricular resumida
4. Diferenciais — metodologia, infraestrutura, resultados de alunos
5. Depoimentos de alunos ou pais
6. Planos e valores (se aplicável)
7. FAQ
8. CTA final — matrícula ou visita agendada

Tom: motivacional, acolhedor, orientado a futuro. Para escolas infantis: falar com os pais.

### Nutricionista / Psicólogo / Terapeuta

Blocos recomendados:
1. Hero — foto + headline de transformação + CTA consulta
2. Problema/Dor — o que o paciente vive (sem dramatizar)
3. Abordagem — como o profissional trabalha (não listar técnicas, descrever a experiência)
4. Sobre — formação, especializações, tempo de prática
5. Como é a consulta — passo a passo simples
6. Depoimentos
7. CTA — WhatsApp ou agendamento online

Tom: empático, acolhedor, sem julgamento. Evitar termos clínicos sem explicação.

---

## Padrões de CTA (completo)

### WhatsApp (mais usado)

Link base:
```
https://wa.me/55XXXXXXXXXXX?text=Olá,%20vim%20pelo%20site%20e%20gostaria%20de%20mais%20informações
```

Botão fixo mobile (CSS + HTML):
```html
<a href="https://wa.me/55XXXXXXXXXXX?text=..." class="whatsapp-fixo" target="_blank">
  <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/whatsapp.svg" alt="WhatsApp">
  Falar no WhatsApp
</a>
```
```css
.whatsapp-fixo {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background: #25D366;
  color: #fff;
  padding: 12px 20px;
  border-radius: 50px;
  font-weight: 700;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 8px;
  z-index: 9999;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.whatsapp-fixo img { width: 22px; filter: invert(1); }
```

WhatsApp flutuante (variação com SVG):
```html
<a href="https://wa.me/55[NUMERO]?text=[MENSAGEM]"
   class="whatsapp-float" target="_blank" rel="noopener">
  <svg><!-- ícone WhatsApp --></svg>
</a>
```

Textos de CTA por nicho:
- Médico: "Agendar consulta" / "Tirar dúvidas agora"
- Advogado: "Falar com advogado" / "Solicitar consulta"
- Estética: "Agendar horário" / "Ver disponibilidade"
- Farmácia: "Fazer pedido" / "Falar com a farmácia"
- Escola: "Falar sobre matrícula" / "Agendar visita"

### Formulário de Contato

HTML básico (sem PHP — via Formspree ou similar):
```html
<form action="https://formspree.io/f/XXXXXXXX" method="POST" class="form-contato">
  <input type="text" name="nome" placeholder="Seu nome" required>
  <input type="tel" name="telefone" placeholder="Seu WhatsApp" required>
  <input type="email" name="email" placeholder="Seu e-mail">
  <textarea name="mensagem" placeholder="Como posso te ajudar?"></textarea>
  <button type="submit">Enviar mensagem</button>
</form>
```

> Sempre perguntar ao usuário se o cliente tem Formspree, WPForms, ou outro plugin de formulário já instalado. Se tiver plugin, usar shortcode em vez de HTML puro.

### Telefone / Ligação

```html
<a href="tel:+55XXXXXXXXXXX" class="btn-telefone">
  📞 Ligue agora: (XX) XXXXX-XXXX
</a>
```

### Agendamento Online (Calendly / Google Agenda)

```html
<a href="https://calendly.com/USUARIO/30min" target="_blank" class="btn-agendar">
  Agendar consulta gratuita
</a>
```

Alternativa embutida (iframe):
```html
<iframe src="https://calendly.com/USUARIO/30min?embed_type=Inline"
  width="100%" height="630" frameborder="0"></iframe>
```

### Regras gerais de CTA

1. Um CTA principal por seção — não dispersar atenção.
2. Repetir o CTA principal no mínimo 3x na página: hero, meio e rodapé.
3. Botão com cor de contraste — nunca usar a cor de fundo do bloco no botão.
4. Texto do botão = ação + benefício — "Agendar agora" > "Enviar" / "Clique aqui".
5. Urgência real quando houver: vagas limitadas, promoção com prazo — nunca fake.
6. Mobile: botão com mínimo 48px de altura, largura total em telas pequenas.

---

## Regras absolutas de landing page (consolidadas)

- **Nunca** usar identidade visual da Agência Borsato no site do cliente
- **Nunca** pedir todo o conteúdo de uma vez — sempre bloco a bloco
- **Nunca** iniciar o build sem confirmar a questão do header/footer
- **Sempre** entregar arquivo PHP único (HTML + CSS + JS embutidos)
- **Sempre** perguntar antes de assumir número de WhatsApp, cor ou fonte
- Copy: sem travessões (—), sem frases de enchimento ("alavancar", "impulsionar", "soluções sob medida")

---

## Tarefas do dia a dia da agência

1. **Landing pages** — fluxo completo das 5 etapas acima
2. **Posts/conteúdo** — criar via API REST ou wp-admin
3. **Plugins** — atualizar via MainWP ou Softaculous WordPress Manager
4. **SEO** — configurar via Yoast em cada site (meta tags de title e description)

---

## Ferramentas e Conectores Usados

### No Cowork (Claude Desktop)
- WordPress.com MCP
- Gmail MCP
- Google Calendar MCP
- Notion MCP
- Google Drive MCP
- GitHub MCP
- Claude in Chrome (para navegar cPanel, Softaculous, wp-admin, etc.)
- Computer Use (controle do desktop)

### No Claude Code
- GitHub (via CLI e MCP)
- Chrome integration (beta — oficialmente suportado, funciona pelo terminal e VS Code)
- Skills customizadas (landing-page-wp, landing-page-wordpress)

### Skills customizadas existentes (Cowork)

Ficam em `~/.claude/skills/` no Cowork:

1. **landing-page-wp** — workflow completo de landing page em 5 etapas
   - Inclui `references/blocos.md` (biblioteca de blocos por nicho)
   - Inclui `references/cta-patterns.md` (padrões de CTA por objetivo)

2. **landing-page-wordpress** — versão alternativa com mesma estrutura
   - Inclui tabela de segmentos frequentes e fontes recomendadas
   - Regras mais explícitas sobre header/footer

3. **skill-creator** — para criar, modificar e testar skills
4. **docx** — criação/edição de Word
5. **pdf** — criação/edição de PDF
6. **pptx** — criação/edição de PowerPoint
7. **xlsx** — criação/edição de Excel
8. **schedule** — tarefas agendadas

---

## Migração entre MacBooks — Guia Completo

### 3 camadas de arquivos para transferir

**Camada 1 — Projetos e CLAUDE.md (mais importante)**
Arquivos dentro das pastas dos projetos. Se usa GitHub Desktop, provavelmente já estão nos repositórios. Basta clonar os repos no novo Mac.

**Camada 2 — Configurações globais do Claude Code**
- `~/.claude.json` — configurações principais, servidores MCP, preferências
- `~/.claude/settings.json` — permissões e configurações do usuário
- `~/.claude/settings.local.json` — configurações locais
- `~/Library/Application Support/Claude/claude_desktop_config.json` — configuração do Claude Desktop

**Camada 3 — Configurações de projeto**
- `CLAUDE.md` — instruções e contexto (este arquivo)
- `.mcp.json` — servidores MCP específicos do projeto
- `.claude/settings.json` — permissões por projeto

### Caminhos completos para copiar do Mac antigo

```
# Claude Code
~/.claude/                                          → pasta inteira (settings, permissões, histórico)
~/.claude.json                                      → configurações globais + servidores MCP

# Claude Desktop (app)
~/Library/Application Support/Claude/claude_desktop_config.json

# Projetos
(pasta dos repos com CLAUDE.md e .mcp.json — clonar via GitHub Desktop)

# GitHub Desktop + Git + SSH
~/Library/Application Support/GitHub Desktop/
~/.gitconfig
~/.ssh/                                             → chaves SSH (essencial para GitHub funcionar)

# Node/npm (para Claude Code rodar)
~/.nvm/                                             → se usa NVM
~/.npmrc
```

### Passo a passo no novo MacBook

1. Instalar Node.js (via nvm ou instalador direto)
2. `npm install -g @anthropic-ai/claude-code`
3. Copiar os arquivos listados acima para os mesmos caminhos
4. Clonar repos via GitHub Desktop
5. Abrir Claude Code na pasta do projeto — ele lê o CLAUDE.md automaticamente

### Sobre sessões e histórico

- Histórico de conversas NÃO transfere oficialmente entre máquinas
- Existe ferramenta open-source **Claude Sync** que sincroniza `~/.claude` entre dispositivos via armazenamento cloud criptografado (solução da comunidade, não oficial)
- Como usa GitHub Desktop, os CLAUDE.md vão junto nos repos automaticamente
- Histórico de conversas se perde, mas o conhecimento da agência fica preservado neste arquivo

---

## Notas e Decisões Importantes

- Sessões do cPanel expiram ~20-30 min — login manual necessário a cada conversa
- Application Passwords NÃO expiram — API REST funciona sempre após configuração
- Este arquivo é o coração da memória — mantenha atualizado conforme adicionar sites, credenciais ou fluxos
- Nunca commitar senhas reais no GitHub — use este arquivo apenas localmente ou em repos privados
- O Borsato prefere controlar tudo pelo cPanel/Softaculous (por causa do fluxo de criar sites novos e landing pages)
- A API REST é complementar para tarefas de conteúdo
- O Claude Code com Chrome integration é a configuração ideal para a agência

---

*Última atualização: Abril 2026*
*Fonte: transcrições completas das sessões do Cowork + skills customizadas + arquivos de referência*
