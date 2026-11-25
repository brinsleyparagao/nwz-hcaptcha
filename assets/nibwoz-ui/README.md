# NIBWOZ UI 1.2.0

Kit visual único dos plugins WordPress da NIBWOZ: estrutura das telas, barra lateral, cabeçalho, cartões, botões, campos, interruptores, indicadores, avisos e estados. Estética Fluent adaptada à marca NIBWOZ: as 6 cores da marca e as fontes Manrope (texto) e Changa (marca). Extraído do NWZ Cache 1.0.3, que é a implementação de referência.

**Regra principal:** o kit é a fonte única. Nenhum plugin edita a cópia que carrega. Mudou o visual? Muda o kit, sobe a versão do kit e cada plugin copia a pasta de novo numa versão própria.

## Arquivos

| Arquivo | O que é |
|---|---|
| `nibwoz-ui.css` | Todo o visual, limitado a `.nwz-ui`. Variáveis `--nwz-*`, classes `nwz-*`. |
| `nibwoz-ui.js` | Comportamentos comuns: confirmação, alterações não salvas, botão ocupado. Sem dependências. |
| `Ui.php` | Marcação padrão em PHP: estrutura, cabeçalho, aviso fixo, aviso temporário (toast), interruptor, número, indicador, selo, lista de definições. Namespace com a versão: `NIBWOZ\UI\V1_2_0`. |
| `fonts/` | Manrope variável (200–800) e Changa 800, em woff2, subconjuntos latin e latin-ext, com as licenças SIL OFL 1.1. Servidas pelo próprio plugin. |
| `nibwoz-wordmark.svg` | Marca NIBWOZ, fonte Changa (800) convertida em contornos, cor `#004fac` (azul da marca). Não depende de fonte carregada. |
| `reference/nibwoz-ui-referencia.html` | Página com todos os componentes, autossuficiente. É o que se aprova visualmente. |
| `CHANGELOG.md` | Histórico do kit. |

## Como um plugin usa o kit

1. Copiar a pasta inteira para `assets/nibwoz-ui/` do plugin, sem alterar nada, incluindo `fonts/` (a pasta `reference/` pode ficar de fora do pacote final).
2. Carregar o PHP: `require_once PLUGIN_DIR . 'assets/nibwoz-ui/Ui.php';` e `use NIBWOZ\UI\V1_2_0\Ui;`.
3. Na tela do plugin (só nela), em `admin_enqueue_scripts`:
   ```php
   $ui = Ui::enqueue( 'nwzc', PLUGIN_URL . 'assets/nibwoz-ui/' );
   wp_enqueue_style( 'nwzc-admin', PLUGIN_URL . 'assets/admin.css', array( $ui ), PLUGIN_VERSION );
   ```
   O handle leva o prefixo do plugin, então dois plugins nunca disputam o mesmo arquivo.
4. Renderizar com `Ui::shell_open()` … conteúdo … `Ui::shell_close()`.
5. CSS próprio do plugin fica no `admin.css` dele, com o prefixo dele (`.nwzc-*`), por cima do kit. Só o que for exclusivo do plugin.
6. Textos: o kit não tem textos. Tudo chega ao `Ui` já traduzido com o text domain do plugin.

### Estrutura de uma tela

```php
Ui::shell_open( array(
	'title'        => __( 'NWZ Cache', 'nibwoz-cache' ),
	'product'      => 'NWZ Cache',
	'version'      => sprintf( __( 'Versão %s', 'nibwoz-cache' ), NWZC_VERSION ),
	'wordmark_url' => NWZC_URL . 'assets/nibwoz-ui/nibwoz-wordmark.svg',
	'nav_label'    => __( 'Seções do NWZ Cache', 'nibwoz-cache' ),
	'items'        => array( array( __( 'Início', 'nibwoz-cache' ), $url, 'dashicons-admin-home', true ), /* … */ ),
	'save'         => array( 'form' => 'nwzc-settings', 'label' => __( 'Salvar configurações', 'nibwoz-cache' ), 'dirty' => __( 'Alterações não salvas', 'nibwoz-cache' ) ),
	'leave'        => __( 'Há alterações não salvas nesta tela.', 'nibwoz-cache' ),
) );
Ui::page_head( __( 'Início', 'nibwoz-cache' ), __( 'Visão geral', 'nibwoz-cache' ), __( 'Uma frase sobre a tela.', 'nibwoz-cache' ) );
// cartões, formulário com id="nwzc-settings" e data-nwz-settings …
Ui::shell_close();
```

`save` só nas telas com formulário de configurações. O botão fica na barra lateral, ligado ao formulário pelo atributo `form`; no celular ele vira uma barra fixa no rodapé.

### Confirmação de salvamento (toast)

Sucesso não usa aviso fixo no topo: usa `Ui::toast()`, uma pílula grande no centro da tela que some com fade depois de 4 s (pausa com o ponteiro em cima; Esc ou clique fecham). Erros e avisos que exigem leitura continuam em `Ui::notice()`, fixos.

```php
$errors = get_settings_errors( 'nwzc_settings' );
if ( isset( $_GET['settings-updated'] ) && ! $errors ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- só exibição.
	Ui::toast( __( 'Settings saved.', 'nibwoz-cache' ) );
} elseif ( $errors ) {
	Ui::notice( 'error', wp_list_pluck( $errors, 'message' ) );
}
```

- Chame dentro de `Ui::shell_open()` … `Ui::shell_close()` (o JS só age dentro de `.nwz-ui`). Sem JavaScript o toast fica oculto.
- Recarregar a página não repete o toast: o WordPress remove `settings-updated` (e `updated`, `saved`, `message`…) da URL com `wp_admin_canonical_url()`. Parâmetro próprio (redirecionamento de `admin-post.php`) precisa entrar no filtro `removable_query_args`.
- Depois de uma ação por AJAX, dispare pelo JS do plugin: `document.querySelector('.nwz-ui').dispatchEvent(new CustomEvent('nwz:toast', { detail: { message: 'Cache limpo.', tone: 'success' } }))`. A mensagem vem traduzida do plugin (`wp_localize_script` ou `wp.i18n`).
- Tons: `success` (verde `nwz-green-strong`, texto branco 7,96:1) e `info` (azul `nwz-blue`, 7,74:1).

## Padrão das telas (todos os plugins)

- **Seções:** Início (ícone `dashicons-admin-home`), as seções próprias do plugin, Diagnóstico (`dashicons-search`) e Sobre (`dashicons-info-outline`), nesta ordem.
- **Início:** cabeçalho, grade de 4 indicadores (`nwz-stat-grid`), cartão de recursos ativos (`nwz-chips`) e atalhos.
- **Telas de configuração:** cabeçalho, cartões com interruptores e campos; um único formulário por tela; salvar só pela barra lateral.
- **Diagnóstico:** cartões com `nwz-dl` e selos `nwz-badge`; ações sensíveis no fim, em `nwz-danger` (confirmação digitada) ou `nwz-card-critical` (decisões que apagam dados).
- **Sobre:** `nwz-about-hero` com a marca grande, versão do plugin e versão do kit.

## Componentes

| Componente | Marcação |
|---|---|
| Estrutura | `.wrap.nwz-ui > .nwz-layout > aside.nwz-sidebar + main.nwz-main` |
| Marca | `.nwz-brand > img.nwz-wordmark + .nwz-product + .nwz-version` |
| Navegação | `nav > a.nwz-nav-link(.is-current, aria-current="page") > .dashicons + span` |
| Salvar | `.nwz-sidebar-save > .nwz-dirty[data-nwz-dirty][role=status][hidden] + button.button-primary[form]` · o marcador é uma pílula laranja pulsante e o botão ganha anel laranja enquanto houver alterações |
| Cabeçalho | `header.nwz-page-head > div(p.nwz-kicker, h2, p) + .nwz-head-actions` |
| Cartão | `section.nwz-card > h3` · cabeçalho com ação: `.nwz-card-head` · grade: `.nwz-grid-2`, `.nwz-span-all` |
| Indicadores | `.nwz-stat-grid > .nwz-stat(.is-blue/.is-green/.is-orange/.is-dark) > span + strong + small` (`.is-amber` e `.is-violet` da 1.0.0 continuam funcionando) |
| Botões | `.button` (secundário, azul), `.button.button-primary`, `.button.nwz-button-danger`, `.is-busy` (o ícone vira `dashicons-update` e gira; clique bloqueado), `[disabled]`; ícone `span.dashicons` antes do texto |
| Interruptor | `label.nwz-toggle > input[hidden=0] + input[type=checkbox][role=switch] + .nwz-toggle-control + .nwz-toggle-text(strong, small)` |
| Campo | `.nwz-field > label + (input/select/textarea ou .nwz-input-unit) + small` · grades `.nwz-fields-2`, `.nwz-fields-3` |
| Campo com botão | `.nwz-inline-field > label + div(input + .button)` |
| Avisos | `.nwz-notice.is-success/.is-error/.is-warning/.is-info > .dashicons + div > p` · processo em andamento: `.nwz-running-note` com `.nwz-live` |
| Aviso temporário | `.nwz-toast.is-success/.is-info[role=status][data-nwz-toast][data-nwz-toast-ms][hidden] > .dashicons + span` (via `Ui::toast()` ou evento `nwz:toast`) |
| Estado e progresso | `.nwz-state`, `.nwz-pill.is-ok/.is-stale/.is-error`, `.nwz-meter > .nwz-meter-label + .nwz-track[role=progressbar] > span`, `.nwz-counters` |
| Listas | `.nwz-dl`, `.nwz-chips > li.is-on/.is-off`, `.nwz-steps > li.is-ok/.is-pending`, `.nwz-list`, `.nwz-actions` |
| Detalhes | `details.nwz-details > summary`, `pre.nwz-code`, `.nwz-live-region(.is-ok/.is-error)` |
| Áreas sensíveis | `.nwz-card.nwz-danger` com `.nwz-danger-form`, `.nwz-card.nwz-card-critical` |
| Sobre | `.nwz-about-hero > img.nwz-wordmark.is-large + div(h2, p)` |

Atributos do JS: `data-nwz-settings` (formulário vigiado), `data-nwz-dirty` (marcador), `data-nwz-leave` (na raiz, texto do aviso ao sair), `data-nwz-confirm="…"` (confirmação), `data-nwz-busy-on-submit` (botão fica ocupado ao enviar: ícone de atualização girando), `data-nwz-toast` (aviso temporário); evento `nwz:toast` na raiz.

## Fundamentos

### Cores da marca

| Token | Valor | Uso |
|---|---|---|
| `--nwz-blue` | `#004fac` | Cor predominante: ação principal, navegação atual, foco, marca |
| `--nwz-green` | `#3a9544` | Interruptor ligado, progresso (não usar como texto: 3,78:1) |
| `--nwz-dark` | `#2c3e50` | Texto principal (`--nwz-text`), títulos, bloco de código |
| `--nwz-gray` | `#7f8c8d` | Borda de campo, interruptor desligado, ícone pendente (não usar como texto: 3,48:1) |
| `--nwz-light` | `#e7e9ee` | Divisórias (`--nwz-stroke-2`), trilho de progresso, base dos fundos internos |
| `--nwz-orange` | `#ffbf24` | Pendência, alterações não salvas, borda da zona de perigo (só fundo ou borda: 1,65:1) |

### Derivadas (contraste conferido)

| Token | Valor | Origem | Contraste |
|---|---|---|---|
| `--nwz-blue-hover` | `#003f8a` | azul + 20% de preto | 10,11 no branco |
| `--nwz-blue-soft` / `-line` / `-line-soft` | `#eef4fc` / `#bfd3ea` / `#d1dff0` | azul clareado | fundos e bordas |
| `--nwz-blue-head-end` | `#126dc4` | fim do gradiente do cabeçalho | 5,24 com texto branco |
| `--nwz-green-text` | `#307b38` | verde escurecido (HSL) | 5,23 no branco |
| `--nwz-green-strong` / `-tint` / `-line` | `#245c2a` / `#ebf4ec` / `#c4dfc7` | avisos e selos de sucesso | 7,08 no fundo |
| `--nwz-orange-text` | `#926700` | laranja escurecida (HSL) | 5,05 no branco; fundo do botão da zona de perigo |
| `--nwz-orange-strong` / `-hover` / `-tint` / `-line` | `#6e4e00` / `#735100` / `#fff5de` / `#ffdc87` | avisos de atenção, zona de perigo | 7,04 no fundo |
| `--nwz-red` / `-strong` / `-tint` / `-line` | `#b32d2e` / `#9b2728` / `#faf2f2` / `#e8c0c0` | erro (cor de função, fora da marca) | 6,30 no branco |
| `--nwz-text-2` / `--nwz-text-3` | `#4c5455` / `#646f70` | cinza escurecida | 7,76 / 5,19 no branco |
| `--nwz-stroke` | `#d7dbdf` | clara + 15% da cinza | bordas de cartão |
| `--nwz-surface` / `-2` | `#fff` / `#f5f6f8` | branco / clara + 60% de branco | cartões / áreas internas |

`--nwz-amber` continua existindo como apelido de `--nwz-orange`, para CSS de plugin feito na 1.0.0.

### Tipografia

| Token | Valor | Uso |
|---|---|---|
| `--nwz-font` | `"NIBWOZ Manrope"`, depois Segoe UI e fontes do sistema | Todo o texto. Corpo 400, títulos, rótulos e botões 600, kicker 700 |
| `--nwz-font-brand` | `"NIBWOZ Changa"` 800 | Só a marca: nome do produto na barra lateral e título do bloco Sobre. O wordmark é SVG em contornos |
| `--nwz-font-mono` | Consolas, Monaco, Cascadia Mono | Código |

As fontes vêm do Google Fonts, mas o kit as serve da própria pasta `fonts/`, sem chamada ao Google: o painel não depende de serviço externo e nenhum IP de usuário vai para terceiros (LGPD). Os nomes `NIBWOZ Manrope` e `NIBWOZ Changa` evitam conflito com uma Manrope carregada por tema ou Elementor. `font-display: swap` e `unicode-range`: o subconjunto latin-ext só é baixado se a página tiver um caractere dele.

### Forma

| Token | Valor | Uso |
|---|---|---|
| `--nwz-radius-ctl` / `--nwz-radius` / `--nwz-radius-lg` | 6 / 8 / 12 px | Controles / indicadores / cartões |
| Cabeçalho | gradiente `--nwz-blue` → `--nwz-blue-head-end`, 135° | Só no `nwz-page-head` |

## Regras que não mudam

- Tudo dentro de `.nwz-ui`. O kit não estiliza nada fora das telas dos plugins, e o módulo Fluent Design do NWZ WP Control não altera as telas dos plugins.
- Ícone de botão herda a cor do texto. A regra usa `.wp-core-ui .wrap.nwz-ui .button .dashicons` para vencer o Elementor (`[class*="branch-7"].wp-core-ui .button .dashicons`) e o esquema de cores do WordPress 7.
- CSS e JS só pelo enqueue, só nas telas do plugin. Sem estilo ou script inline.
- Contraste mínimo 4,5:1 para texto e 3:1 para bordas de campo e controles (conferido com axe na página de referência). Verde, cinza e laranja da marca nunca são cor de texto; para texto existem `-text` e `-strong`.
- Nenhuma cor escrita direto no CSS fora do bloco de tokens: tudo deriva das 6 cores da marca (e do vermelho de erro).
- `prefers-reduced-motion` desliga animações.
- Requisito: WordPress 6.4 ou mais novo (estratégia `defer` em `wp_enqueue_script`), PHP 8.0 ou mais novo para o `Ui.php`.

## Atualizar o kit

1. Alterar aqui, subir a versão (`VERSION` em `Ui.php`, cabeçalhos do CSS e do JS, `CHANGELOG.md`) e trocar o namespace para a nova versão (por exemplo `V1_3_0`).
2. Regenerar a página de referência e conferir: axe sem violações, cor dos ícones igual à do texto, 1440 px e 390 px.
3. Publicar o kit nas referências do projeto.
4. Em cada plugin, numa versão nova do plugin: copiar a pasta, trocar o `use` do namespace e testar no brinsley.com.br com o Elementor ativo.

Os plugins não se atualizam sozinhos quando o kit muda: cada um acompanha na sua próxima versão.

## Checklist de homologação visual (por plugin)

- Todas as telas em 1440, 1024, 782 e 390 px.
- Elementor ativo; esquemas de cores Padrão e Moderno.
- Ícones de todos os botões na cor do texto (inclusive desativados).
- Aviso de alterações não salvas aparece e some; salvar pela barra lateral funciona em todas as telas com formulário.
- axe sem violações; navegação por teclado com foco visível.
- Nenhum CSS ou JS do kit carregado fora das telas do plugin.
