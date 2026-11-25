# Changelog — NIBWOZ UI

## 1.2.0 — 23/11/2026
- Botão ocupado (`.is-busy`, `data-nwz-busy-on-submit`): o ícone do botão passa a ser o de atualização (`dashicons-update`) enquanto gira, em vez de girar o ícone original (o "salvo" girando não fazia sentido). Clique bloqueado enquanto ocupado; voltar pelo histórico (bfcache) solta o botão.
- Aviso temporário novo: `Ui::toast( $mensagem, 'success'|'info', $ms = 4000 )` e evento `nwz:toast` para AJAX. Pílula grande no centro da tela (18 px, 700), entra com fade e escala e some com fade de 0,4 s; pausa com o ponteiro em cima; Esc ou clique fecham; `role="status"` anunciado pelo leitor de tela. Substitui o aviso fixo de sucesso no topo. Erros continuam em `Ui::notice()`.
- Token novo `--nwz-shadow-toast`.
- Namespace `NIBWOZ\UI\V1_2_0`.

## 1.1.0 — 22/11/2026
- Cores da marca NIBWOZ no kit: azul `#004fac` (predominante), verde `#3a9544`, escura `#2c3e50`, cinza `#7f8c8d`, clara `#e7e9ee`, laranja `#ffbf24`. Tokens novos `--nwz-dark`, `--nwz-gray`, `--nwz-light`, `--nwz-orange` e variações `-text`, `-strong`, `-tint`, `-line`.
- Todas as cores escritas direto no CSS (fundos de aviso, selos, indicadores, zona de perigo, código) passam a usar tokens derivados das 6 cores.
- Texto principal na escura `#2c3e50`; textos secundários derivados da cinza. Verde, cinza e laranja ganham tons escurecidos para texto (contraste acima de 4,5:1).
- Laranja substitui o âmbar; `--nwz-amber` e `.is-amber` continuam como apelidos. O indicador violeta passa a usar a escura (`.is-dark`; `.is-violet` continua como apelido). `Ui::stat()` aceita `orange` e `dark` e converte `amber` e `violet`.
- Bordas de campo na cinza da marca (3,48:1) e foco azul; interruptor desligado na cinza.
- Fontes: Manrope variável no texto e Changa 800 no nome do produto e no título do Sobre, servidas pela pasta `fonts/` do kit (SIL OFL 1.1), sem Google Fonts remoto.
- Alterações não salvas: pílula laranja (`nwz-orange-tint` com borda `nwz-orange`, texto `nwz-orange-strong`, 7,04:1) com ponto e brilho pulsantes; o botão Salvar ganha anel laranja enquanto houver pendência (`:has()`); `role="status"` no marcador para leitores de tela; no celular (até 560px) pílula e botão empilham na barra fixa. `prefers-reduced-motion` desliga o pulso.
- Wordmark em `#004fac`.
- Correção: o kicker do cabeçalho (branco a 85%) ficava com 4,24:1 sobre o fim do gradiente; agora branco a 92% (4,68:1).
- Namespace `NIBWOZ\UI\V1_1_0`.

## 1.0.0 — 22/11/2026
- Primeira versão, extraída do NWZ Cache 1.0.3 (implementação de referência).
- CSS limitado a `.nwz-ui`, variáveis `--nwz-*`, classes `nwz-*`; ícones de botão na cor do texto mesmo com Elementor e o esquema de cores do WordPress 7.
- JS comum: confirmação, alterações não salvas, botão ocupado.
- `Ui.php` (namespace `NIBWOZ\UI\V1_0_0`): estrutura com barra lateral e salvar único, cabeçalho, aviso, interruptor, número, indicador, selo, lista de definições.
- Página de referência com todos os componentes; axe sem violações (WCAG 2.1 AA) em 1440 px.
