# Referências dos testes estruturais NT 009

- NT 009 v1.01: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf
- Anexo VI v1.04.01: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/anexovi-leiautesrn_rtc_ibscbs-v1-04-01-nt009.xlsx
- Referência consultada em 20/10/2026: aba de leiaute DPS/NFS-e e respectivas regras de negócio.

Os fixtures contêm somente dados fictícios. Os XMLs de regressão foram gerados
pelo commit dfd5e5156f0749c1b39bb10895b742dea0353777, antes da alteração do gerador.

A seleção `LeiauteDps::NT009` define a estrutura final documentada, não uma
etapa intermediária de implantação do autorizador. O consumidor deve confirmar
sua disponibilidade no ambiente fiscal antes de transmitir.

Não há XSD NT 009 incorporado nesta entrega. Os testes são estruturais: não
atestam validação XSD, enquadramento fiscal nem autorização governamental.
Os XSD legados permanecem inalterados e não devem validar o perfil NT009.

O perfil NT009 aceita o contrato novo (ver nt009.json), sem migrar automaticamente
os caminhos legados. Além da emissão regular, suporta os tipos documentados de
crédito/débito, referências, pagamentos vinculados, ajustes por documentos,
estorno de crédito e referências de pagamento antecipado. Grupos repetidos usam
arrays, inclusive quando há somente um registro. Os campos opcionais omitidos
não são inventados pelo gerador. Os valores fiscais são fornecidos pelo consumidor.

Imóveis, bens móveis, condomínios e eventos futuros de vinculação de pagamentos
estão fora do escopo. O tratamento existente de serv/obra permanece independente.

src/Layouts/nt009.json contém somente o mapa de montagem extraído do anexo
(com seu SHA-256): nomes, ordem, grupos e indicação de repetição. Não contém
matriz de modalidades, opções fiscais, tamanhos ou limites de cardinalidade.
Não é um schema XSD.

O fork não verifica enquadramentos, códigos permitidos, formatos fiscais,
obrigatoriedades, escolhas documentais, duplicidades ou combinações entre campos.
Os grupos suportados informados são montados sem filtragem por finalidade, Simples
Nacional ou tipo de operação. Isso permite gerar XML fiscalmente inválido: a
validação correspondente deve ser feita por XSD oficial e pelo autorizador.
A ausência do XSD NT009 nesta entrega não é compensada por um validador próprio.

Permanecem apenas as proteções técnicas: objetos/listas/folhas com tipos adequados,
dados mínimos para compor o identificador da DPS, rejeição de caminhos antigos,
campos desconhecidos nos novos grupos e grupos expressamente fora do escopo.
Em listas, não se aplica mínimo/máximo de registros: somente a forma de array-lista.

cAtvSN, tpNFSeCredito e tpNFSeDebito aceitam inteiros ou strings numéricas de um
ou dois dígitos, normalizados para dois dígitos. Não há consulta de opções válidas,
truncamento de valores maiores ou criação de códigos não fornecidos. O perfil
legado não recebe essa normalização.

A ocorrência fiscal de gIBSCBS depende da tabela cClassTrib (ind_gIBSCBS).
O consumidor deve fornecer ou omitir o grupo conforme essa tabela; o fork não
consulta tabelas externas nem presume enquadramento. Os fixtures verificam a
montagem, não a vigência de benefícios ou a validade fiscal dos códigos utilizados.
