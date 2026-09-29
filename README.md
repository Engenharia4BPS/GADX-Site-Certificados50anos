# Araucária DX — 50 anos

Portal público para emissão do diploma comemorativo da Araucária DX.

## O que faz

- Consulta pública por indicativo.
- Certificado A4 pronto para imprimir ou salvar em PDF.
- Figurinha PNG para compartilhamento.
- Importação de logs ADIF na área administrativa.
- Endossos de satélite e WFF, além de unidades de conservação.
- Administração por múltiplos organizadores.

## Operação

1. Entre em `/admin` usando uma conta ChatGPT autorizada.
2. Na primeira entrada, ative a área com a chave inicial configurada no ambiente do site.
3. Importe um arquivo `.adi` ou `.adif`, dê um nome para a operação e marque os endossos aplicáveis.
4. Informe as unidades de conservação no formato `REFERÊNCIA | Nome` — uma por linha.
5. Os participantes já podem consultar o indicativo na página inicial e gerar o diploma.

## Desenvolvimento

```bash
npm install
npm run db:generate
npm run build
```

O projeto usa Cloudflare D1 para os dados persistentes. O segredo `ADMIN_SETUP_KEY` deve ser configurado como variável de ambiente no serviço de hospedagem; nunca o inclua no repositório.

## Estrutura principal

- `app/` — páginas pública, administrativa e APIs.
- `db/` e `drizzle/` — esquema e migrações do banco de dados.
- `lib/adif.ts` — parser de ADIF.
- `components/` — interface de consulta e administração.
