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
3. Copie `private/app/config.example.php` para `private/app/config.php` e preencha as credenciais do banco, `app_key` e `install_key`.
4. Copie `public/private-path.example.php` para `public/private-path.php` e confira o caminho privado.
5. Envie os dois grupos de arquivos por FTPS para os destinos da tabela.
6. Acesse `https://araucariadx.com/50anos/admin/setup.php`, informe a chave de instalação e crie a conta proprietária.
7. Apague ou renomeie `admin/setup.php` depois de criar o primeiro administrador.

## Uso normal

- Público: `https://araucariadx.com/50anos/`
- Organização: `https://araucariadx.com/50anos/admin/`
- Importe um ADIF por operação e marque WFF, satélite, unidades de conservação e endossos adicionais.

Os ADIFs originais são guardados fora de `public_html`. Nunca envie `config.php`, `private-path.php` ou arquivos de `private/storage/` ao GitHub.
