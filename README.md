# Amigo Secreto — tema WordPress

Sistema de sorteio de amigo secreto com grupos, convites por e-mail e listas de presentes com links.

**Stack:** WordPress · Parcel · TailwindCSS v4 · Swup.js · React 19

## Instalação

```bash
npm install
npm run build   # gera dist/js/app.js e dist/css/app.css
npm run dev     # recompila a cada alteração (watch)
npm run icons   # regera os ícones em assets/icons/ (usa o Chrome/Edge instalado)
```

No WordPress:

1. Ative o tema em **Aparência → Temas**.
2. Em **Configurações → Links permanentes**, use qualquer estrutura que não seja "Simples".
3. (Opcional) Em **Configurações → Geral**, marque **Qualquer pessoa pode se registrar** para liberar
   `/cadastro/`, onde novas contas entram como **organizador**.
4. Configure o envio de e-mails (ex.: plugin SMTP). Convites, confirmação de cadastro e avisos de
   sorteio usam `wp_mail()`.
5. (Opcional) Configure o login social em **Configurações → Login social** (veja abaixo).

## Contas e acesso

- **Cadastro** (`/cadastro/`): só nome e e-mail são obrigatórios. A conta é criada e a pessoa recebe um
  link para confirmar o e-mail e criar a senha — sem confirmação, a conta não é usável. Foto e data de
  nascimento são opcionais.
- **Convite**: o organizador informa nome e e-mail; a conta `participante` é criada e o convite traz o
  link para criar a senha. Se a pessoa tentar se cadastrar com esse e-mail antes de ativar, o link é
  reenviado.
- **Google / Facebook**: entram direto. Se o e-mail já tiver conta (ex.: participante convidado), o
  acesso é vinculado a ela. Contas novas só são criadas se o cadastro estiver liberado.
- **Foto e aniversário**: quando o provedor fornece, são salvos no primeiro acesso (a foto é copiada para
  a biblioteca de mídia, pois as URLs dos provedores expiram). Nunca sobrescrevem o que a pessoa já
  definiu. Os outros participantes veem só dia e mês do aniversário.
- Quem entrou só por Google/Facebook pode criar uma senha em `/perfil/` sem informar senha atual, e
  conectar/desconectar contas sociais por lá.

### Configurar Google

O Google usa o botão oficial **Sign in with Google** (Google Identity Services): o Google entrega um
token assinado ao JavaScript da página, que o envia à API do tema (`POST /wp-json/amigosecreto/v1/auth/google`);
o servidor valida o token com o Google (`tokeninfo`: app, emissor, validade, e-mail verificado e um nonce
de uso único guardado em cookie).

1. Em [Google Cloud Console → Credenciais](https://console.cloud.google.com/apis/credentials), use (ou crie)
   um **ID do cliente OAuth** do tipo "Aplicativo da Web".
2. Em **Origens JavaScript autorizadas**, adicione a origem do site, ex.: `https://rafabytes.com`
   (aparece em Configurações → Login social). URIs de redirecionamento não são necessárias.
3. Copie o **Client ID** para Configurações → Login social. O Client secret não é usado.

> **Por que não o redirecionamento OAuth?** O retorno do Google sempre traz
> `iss=https://accounts.google.com` (na URL ou via POST), e o ModSecurity da hospedagem bloqueia parâmetros
> com URL de outro domínio (erro 406 "Not Acceptable"). Com o botão oficial, o token chega pelo JavaScript
> e não há URL externa na requisição. O Google não fornece data de nascimento nesse modo.

### Configurar Facebook

1. Em [Meta for Developers](https://developers.facebook.com/apps/), crie um app com o caso de uso
   **Autenticar e solicitar dados de usuários com o Login do Facebook**.
2. Em Login do Facebook → Configurações, adicione `https://SEU-SITE/auth/facebook/callback/` em
   "URIs de redirecionamento do OAuth válidos". O Facebook exige HTTPS.
3. Copie App ID e App secret para Configurações → Login social.
4. Data de nascimento (opcional): a permissão `user_birthday` exige **App Review** para funcionar com
   pessoas que não são da equipe do app. Para publicar o app, a Meta também pede URL de política de
   privacidade e de exclusão de dados.

As credenciais também podem vir do `wp-config.php` (têm prioridade sobre a tela):

```php
define( 'AS_GOOGLE_CLIENT_ID', '...' );
define( 'AS_FACEBOOK_APP_ID', '...' );
define( 'AS_FACEBOOK_APP_SECRET', '...' );
```

A versão da Graph API usada é `v23.0`; para trocar, use o filtro `as_facebook_graph_version`.

## Ícones

Gerados por `npm run icons` a partir do desenho em [scripts/icons.js](scripts/icons.js) (presente
branco sobre o roxo da marca). Para mudar o desenho, edite o script e rode de novo.

| Arquivo | Uso |
|---|---|
| `favicon.svg`, `favicon-32.png` | aba do navegador (versão simplificada para tamanhos pequenos) |
| `apple-touch-icon.png` | atalho na tela inicial do iPhone (180×180) |
| `icon-192.png`, `icon-512.png`, `icon-maskable-512.png` | Android / "instalar app" (`/manifest.webmanifest`) |
| `facebook-app-icon-1024.png` | ícone do app no Meta for Developers (1024×1024) |
| `google-logo-120.png` | logo da tela de consentimento OAuth do Google (120×120) |
| `og-image.png` | imagem ao compartilhar links (1200×630) |

O tema usa o "Ícone do site" nativo do WordPress: enquanto nenhum ícone for definido em Aparência →
Personalizar → Identidade do site, valem os ícones do tema (site, login, admin e `/favicon.ico`).

## Papéis

| Papel          | Pode                                                                                       |
|----------------|--------------------------------------------------------------------------------------------|
| `administrator` | Tudo, inclusive wp-admin. Vê resultados do sorteio no metabox do grupo.                   |
| `organizador`   | Criar grupos, convidar/remover participantes, sortear/desfazer. Pode participar do próprio sorteio e ter lista. |
| `participante`  | Criado automaticamente quando o organizador cadastra o e-mail. Mantém a própria lista e vê as listas de quem está nos mesmos grupos. |

Para promover alguém a organizador, edite o usuário no wp-admin. Só administradores acessam o
wp-admin e veem a barra de administração.

## Rotas

| URL                 | Conteúdo                                                   |
|---------------------|------------------------------------------------------------|
| `/`                 | Landing (logado → redireciona para `/painel/`)             |
| `/entrar/`          | Login por e-mail e senha, Google ou Facebook               |
| `/cadastro/`        | Cadastro de organizador (se o registro estiver liberado)   |
| `/auth/facebook/`   | Início/retorno do login com Facebook (Google usa o botão oficial) |
| `/painel/`          | Meus grupos, atalho para a lista, criar grupo              |
| `/grupos/{id}/`     | Grupo: participantes, sorteio, "quem eu tirei"             |
| `/minha-lista/`     | Editor da minha lista de presentes                         |
| `/lista/{user_id}/` | Lista de outra pessoa (só quem compartilha grupo)          |
| `/perfil/`          | Foto, nome, e-mail, aniversário, senha e contas sociais    |

As rotas são regras de rewrite do tema (`inc/router.php`) — não é preciso criar páginas.

## Como funciona o sorteio

- Mínimo de 3 participantes. A ordem é embaralhada com `random_int()` (Fisher–Yates) e cada pessoa
  tira a seguinte, formando um único ciclo: ninguém tira a si mesmo e todos dão e recebem um presente.
- Cada participante recebe um e-mail com o link do grupo. O nome sorteado **não** vai no e-mail;
  para incluir, use `add_filter( 'as_email_reveal_match', '__return_true' );`.
- Na página do grupo, cada um clica em "Descobrir quem eu tirei". O organizador vê apenas quantos
  já abriram o resultado, não os pares.
- Depois do sorteio os participantes ficam travados; "Desfazer sorteio" libera alterações.

## Estrutura

```
functions.php          carrega inc/*
inc/
  setup.php            suportes, enqueue dos assets, bloqueio do wp-admin
  roles.php            papéis organizador/participante
  post-types.php       CPTs as_grupo e as_presente (não públicos)
  groups.php           participantes, permissões, sorteio, payload do grupo
  wishlist.php         itens da lista de presentes
  profile.php          foto, aniversário, criação de contas, senha pendente
  emails.php           convite, confirmação de cadastro e aviso de sorteio
  router.php           rotas amigáveis + títulos + 404 de acesso
  auth.php             login/cadastro no front e visual do wp-login.php
  social-login.php     OAuth Google/Facebook (sem plugins)
  social-settings.php  Configurações → Login social
  rest-api.php         API REST amigosecreto/v1
  admin.php            colunas e metabox no wp-admin
  icons.php            ícones SVG da interface (também enviados ao JS) e botão "mostrar senha"
  site-icons.php       favicon, ícones de app, manifest e meta tags de compartilhamento
  legal.php            links de privacidade/exclusão de dados e shortcode [amigosecreto_email]
templates/             um template por rota
template-parts/        partes reutilizáveis (card de grupo)
assets/icons/          ícones gerados por scripts/icons.js (veja "Ícones")
scripts/icons.js       gerador dos ícones
scripts/parcel.js      roda o Parcel com pasta temporária no mesmo drive (veja Observações)
src/
  css/app.css          Tailwind v4 (@theme com as cores do tema)
  js/app.js            Swup + montagem das ilhas React
  js/islands/          componentes montados via as_island() no PHP
  js/components/       UI compartilhada (modal, ícones, formulário de grupo)
  js/lib/api.js        cliente da API REST (cookie + nonce)
```

### Ilhas React + Swup

O PHP renderiza as páginas normalmente. Onde há interatividade, o template chama
`as_island( 'NomeDoComponente', $props )`, que gera `<div data-island data-props>`. O `app.js` monta
esses componentes ao carregar e a cada navegação do Swup (`page:view`), e desmonta antes da troca de
conteúdo (`content:replace`). Links com `data-no-swup` (logout, wp-admin, links externos, formulários
de login) fazem navegação normal.

## API REST (`/wp-json/amigosecreto/v1`)

| Método           | Rota                                  | Quem                       |
|------------------|---------------------------------------|----------------------------|
| GET / POST       | `/groups`                             | logado / organizador       |
| GET / POST / DELETE | `/groups/{id}`                     | membro / gestor / gestor   |
| POST             | `/groups/{id}/participants`           | gestor                     |
| DELETE           | `/groups/{id}/participants/{user}`    | gestor                     |
| POST / DELETE    | `/groups/{id}/draw`                   | gestor                     |
| POST             | `/groups/{id}/reveal`                 | participante do grupo      |
| GET / POST       | `/wishlist`                           | logado                     |
| POST / DELETE    | `/wishlist/{id}`                      | dono do item               |
| POST             | `/wishlist/order`                     | logado                     |
| GET              | `/users/{id}/wishlist`                | quem compartilha grupo     |
| POST             | `/me`                                 | logado                     |
| POST / DELETE    | `/me/avatar` (multipart, campo `avatar`) | logado                  |
| DELETE           | `/me/social/{google|facebook}`       | logado                     |
| GET              | `/auth/google/nonce`                  | público (Google ativo)     |
| POST             | `/auth/google` (`credential`, `redirectTo?`, `link?`) | público; `link` exige login |

"Gestor" = autor do grupo ou administrador.

## Deploy (cPanel)

O repositório contém só o tema. O deploy usa o **Git™ Version Control** do cPanel e o arquivo
[.cpanel.yml](.cpanel.yml), que copia o tema para
`~/public_html/amigosecreto/wp-content/themes/amigosecreto-theme/` sem os arquivos de desenvolvimento.

1. **Antes de commitar**, rode `npm run build`: o `dist/` vai no repositório porque o servidor não tem Node.
2. `git push` para o GitHub.
3. No cPanel: Git™ Version Control → Gerenciar → **Pull or Deploy** → **Update from Remote** e depois
   **Deploy HEAD Commit**.

O repositório é público: nunca commite credenciais (use Configurações → Login social ou o `wp-config.php`).

## Observações

- Os scripts `npm run dev/build` passam por `scripts/parcel.js`, que aponta a pasta temporária para
  `.parcel-tmp/`. No Windows, com o projeto num drive diferente do `%TEMP%` (ex.: B: e C:), o Parcel
  não consegue mover os arquivos gerados e o `dist/` deixa de ser atualizado — sem mostrar erro.
- `npm run build` roda sem cache, para o build de produção nunca reaproveitar um estado antigo.
- A fonte Outfit vem do Google Fonts (`header.php`).
