# Referências dos testes NT009 — release 1

- Comunicado oficial de 20/10/2026: https://www.gov.br/nfse/pt-br/noticias/cgnfs-e-informa-disponibilizadas-no-ambiente-de-testes-o-release-1-das-evolucoes-da-nota-tecnica-009
- Documentação de homologação: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita
- XSD oficial: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/esquemas_prod_restrita_20261020.zip
- Anexo I: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/anexo_i-sefin_adn-dps_nfse-snnfse_prodrest-v1-01-20261020.xlsx

LeiauteDps::NT009 representa somente o release 1 disponível em homologação.
Não representa a implementação completa da NT009 v1.01. A escolha é explícita,
independente de tpAmb e de datas. O padrão continua sendo LEGADO.

O pacote oficial está em storage/schemes/nt009-release1-20261020 sem alterações.
Os schemas anteriores permanecem nos caminhos originais. O mapa src/Layouts/nt009.json
contém apenas nomes, sequência, grupos e repetições extraídos do XSD, com seu hash;
não implementa validações fiscais. A validação XSD é executada nos testes, não
adicionada ao fluxo de transmissão.

Os fixtures são fictícios; validação XSD não comprova enquadramento fiscal,
assinatura nem autorização governamental. Os snapshots legados foram gerados no
commit dfd5e5156f0749c1b39bb10895b742dea0353777 e continuam inalterados.

finNFSe fica na raiz de infDPS; indDest e dest permanecem em IBSCBS. Os campos
CST e cClassTrib são filhos de IBSCBS/valores/trib. Não há suporte neste release
a crédito/débito, pagamentos vinculados, novos campos ME/EPP ou estornos.
Os novos fatos geradores 99.02.01, 99.03.01 a 99.03.05 e 99.04.01 são
serializados sem tabela fiscal própria no gerador. O perfil suporta imovel
(cMun, gLocacao, lista gUnidImob e lista gAjusteBCLocImoveis) e a lista bensMoveis,
na ordem e forma do XSD oficial. Condomínios continuam fora deste release.
Os exemplos em exemples/dados-nt009 cobrem esses três grupos de operações;
os dados e códigos tributários são fictícios para validação estrutural.
Não há cálculo automático de copropriedade, descontos ou bases: os valores
são fornecidos pelo consumidor. Não foi realizada transmissão ao governo.
As proteções locais se limitam à estrutura de montagem e aos dados do identificador;
formatos, escolhas, obrigatoriedades e regras fiscais cabem ao schema/autorizador.

O snapshot legado com destinatário específico preserva uma limitação anterior:
o montador legado posiciona telefone/e-mail antes do endereço e os repete depois.
Esse snapshot é usado para regressão byte a byte, não como exemplo XSD válido.
O teste XSD do legado usa a variante sem destinatário específico; o perfil novo
valida também o destinatário com endereço. A correção do legado está fora desta entrega.

Referências dos novos fatos geradores: item 2.15 da NT009 e item 2.1.3 da NT005.
A implantação de 20/10/2026 está registrada em:
https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/atualizacoes-e-implantacoes
O comunicado anuncia implantação em produção em 01/12/2026, com as revisões
1.01 e posteriores passando antes por produção restrita. Isso não declara
cobertura integral dessas revisões pelo fork. Notas de ajuste e eventos RTC
continuam sujeitos a cronograma próprio a divulgar. Não há ativação por data.
