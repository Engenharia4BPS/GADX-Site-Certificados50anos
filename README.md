# Araucária DX — Certificados 50 anos (FTP/cPanel)

Versão para hospedagem própria em **PHP 8.2 + MySQL/MariaDB**, sem Node.js, Cloudflare D1 ou login ChatGPT.

## Estrutura de publicação

| Origem deste repositório | Destino no servidor |
| --- | --- |
| `public/*` | `/home2/dxaraucariadx/public_html/50anos/` |
| `private/*` | `/home2/dxaraucariadx/private/certificados50anos/` |
| `sql/schema.sql` | Importe uma vez pelo phpMyAdmin |

Não envie a pasta raiz inteira para `public_html`: somente o conteúdo de `public/` fica acessível pela web.

## Primeira instalação

1. Crie um banco MySQL e um usuário com privilégios somente nesse banco.
2. Importe `sql/schema.sql` no phpMyAdmin.
3. Crie a pasta gravável `/home2/dxaraucariadx/tmp/ardx50-sessions` (permissão `700`). Copie `private/app/config.example.php` para `private/app/config.php` e preencha as credenciais do banco, `app_key` e `install_key`; mantenha `session_save_path` apontando para essa pasta.
4. Copie `public/private-path.example.php` para `public/private-path.php` e confira o caminho privado.
5. Envie os dois grupos de arquivos por FTPS para os destinos da tabela.
6. Acesse `https://araucariadx.com/50anos/admin/setup.php`, informe a chave de instalação e crie a conta proprietária.
7. Apague ou renomeie `admin/setup.php` depois de criar o primeiro administrador.

## Uso normal

- Público: `https://araucariadx.com/50anos/`
- Organização: `https://araucariadx.com/50anos/admin/`
- Cada envio é incorporado ao histórico acumulado. O ADIF pode ser completo, parcial, sobreposto a arquivos anteriores e trazer uma ou várias das estações habilitadas: ZW5B, ZW50B, PY5GA e PQ5TA.
- Exemplo: enviar `X`, depois `X + Y` e depois somente `Z` produz o conjunto acumulado `X + Y + Z`; a repetição de `X` permanece guardada nos arquivos de origem, mas não aumenta os QSOs válidos.
- A estação é lida de STATION_CALLSIGN ou MY_CALL. Exportações do Club Log sem esses campos também são reconhecidas pelo cabeçalho “Log export of INDICATIVO”. WWFF é reconhecido por MY_WWFF_REF, POTA por MY_POTA_REF, satélite por PROP_MODE=SAT ou SAT_NAME, e CW pelo modo do QSO.
- O painel permite enviar a foto do Hall of Fame e abrir as prévias visuais de Participação e Hall of Fame com dados ilustrativos. Até a regra de elegibilidade ser definida, a consulta pública emite apenas Participação.
- Em “Logo 50 anos”, envie a marca oficial em PNG, JPG ou WebP para substituir o pequeno círculo ao lado do título em ambas as versões do certificado. Sem uma logo enviada, o círculo continua visível.
- O ranking público consolida todos os arquivos incorporados das quatro estações: uma linha por indicativo, bandas distintas, QSOs válidos e conquistas. Cada combinação de estação, banda e modo conta apenas uma vez por participante, mesmo que apareça em vários arquivos. Ao abrir BANDS, a estação e o modo ficam identificados; SAT é uma coluna adicional e não duplica o TOTAL.
- Em novas importações, o submodo FT4 de ADIFs MFSK é mostrado como FT4. Para ADIFs antigos que aparecem como MFSK, abra a operação no painel e use “Identificar submodos”; a rotina confere o arquivo original antes de atualizar o modo, sem duplicar QSOs.
- Em “ADIFs importados”, cada log tem a opção “Apagar este log”. As contribuições exclusivas dele saem do acumulado; contatos que também existam em outros arquivos permanecem. A ação não pode ser desfeita.

Os ADIFs originais são guardados fora de `public_html`. Nunca envie `config.php`, `private-path.php` ou arquivos de `private/storage/` ao GitHub.

## Segurança da publicação

- Envie também `public/.htaccess`: ele força HTTPS e adiciona HSTS, CSP, proteção contra iframe, `nosniff`, política de referência e restrições de permissões do navegador.
- O login limita, por padrão, 5 falhas para a mesma combinação de IP e e-mail e 20 falhas por IP em 15 minutos. Os contadores ficam em uma pasta privada criada dentro de `session_save_path`; nenhuma migração de banco é necessária.
- Os limites podem ser ajustados pela chave opcional `login_rate_limit` mostrada em `private/app/config.example.php`. O `config.php` atual não precisa ser alterado para usar os valores seguros padrão.
- Depois da primeira instalação, remova ou renomeie `public/admin/setup.php` no servidor.

## Atualização para snapshots de estações

Em uma instalação que já possui dados, faça backup do banco e execute uma única vez o arquivo sql/migrations/2026-10-03-station-snapshots.sql no phpMyAdmin **antes** de enviar os arquivos atualizados. A migração preserva os dados antigos como histórico da ZW50B até que o primeiro ADIF completo dessa estação seja sincronizado.

## Atualização para logs acumulados

Depois da migração de snapshots acima, faça outro backup e execute uma única vez `sql/migrations/2026-10-04-accumulated-logs.sql` no phpMyAdmin. Ela parte exatamente dos QSOs que já estavam públicos, sem ressuscitar logs históricos substituídos. A partir daí, cada novo arquivo completo, parcial ou sobreposto é incorporado ao acumulado.

O código detecta automaticamente se essa migração já existe. Antes de executá-la, o site continua usando os snapshots anteriores; depois dela, passa a usar o acumulado. Para evitar que uma importação feita durante a atualização siga a regra antiga, execute a migração e envie os arquivos PHP na mesma janela de manutenção.

## Atualização da tipografia

Envie também a pasta `public/assets/fonts/` ao atualizar `public/assets/site.css` e `public/assets/award.js`. Ela contém as fontes do certificado e suas licenças; sem esses arquivos, o navegador usará fontes de reserva. Os detalhes de origem estão em `public/assets/fonts/README.md`.
