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
- Cada envio é um snapshot completo: o ADIF pode trazer uma ou várias das estações habilitadas: ZW5B, ZW50B, PY5GA e PQ5TA. Para cada estação encontrada, somente o snapshot mais recente alimenta o ranking e os diplomas.
- A estação é lida de STATION_CALLSIGN ou MY_CALL. Exportações do Club Log sem esses campos também são reconhecidas pelo cabeçalho “Log export of INDICATIVO”. WWFF é reconhecido por MY_WWFF_REF, POTA por MY_POTA_REF, satélite por PROP_MODE=SAT ou SAT_NAME, e CW pelo modo do QSO.
- O painel permite enviar a foto do Hall of Fame e abrir as prévias visuais de Participação e Hall of Fame com dados ilustrativos. Até a regra de elegibilidade ser definida, a consulta pública emite apenas Participação.
- Em “Logo 50 anos”, envie a marca oficial em PNG, JPG ou WebP para substituir o pequeno círculo ao lado do título em ambas as versões do certificado. Sem uma logo enviada, o círculo continua visível.
- O ranking público reúne os snapshots vigentes de todas as quatro estações: uma linha por indicativo, bandas distintas, QSOs totais e conquistas. Ao abrir BANDS, mostra os modos e respectivas quantidades em cada banda; SAT é uma coluna adicional e não duplica o TOTAL.
- Em novas importações, o submodo FT4 de ADIFs MFSK é mostrado como FT4. Para ADIFs antigos que aparecem como MFSK, abra a operação no painel e use “Identificar submodos”; a rotina confere o arquivo original antes de atualizar o modo, sem duplicar QSOs.
- Em “ADIFs importados”, cada log tem a opção “Apagar este log”. Se ele for o snapshot vigente de alguma estação, o painel volta ao snapshot anterior disponível para ela. A ação não pode ser desfeita.

Os ADIFs originais são guardados fora de `public_html`. Nunca envie `config.php`, `private-path.php` ou arquivos de `private/storage/` ao GitHub.

## Atualização para snapshots de estações

Em uma instalação que já possui dados, faça backup do banco e execute uma única vez o arquivo sql/migrations/2026-10-03-station-snapshots.sql no phpMyAdmin **antes** de enviar os arquivos atualizados. A migração preserva os dados antigos como histórico da ZW50B até que o primeiro ADIF completo dessa estação seja sincronizado.

## Atualização da tipografia

Envie também a pasta `public/assets/fonts/` ao atualizar `public/assets/site.css` e `public/assets/award.js`. Ela contém as fontes do certificado e suas licenças; sem esses arquivos, o navegador usará fontes de reserva. Os detalhes de origem estão em `public/assets/fonts/README.md`.
