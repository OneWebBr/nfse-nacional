<?php

namespace Hadder\NfseNacional\Tests;

use DOMDocument;
use DOMXPath;
use Hadder\NfseNacional\Dps;
use Hadder\NfseNacional\LeiauteDps;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class DpsTest extends TestCase
{
    private function dados(string $fixture = 'nt009'): stdClass
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.json'), false, 512, JSON_THROW_ON_ERROR);
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
        return $xpath;
    }

    private function valor(DOMXPath $xpath, string $path): string
    {
        return $xpath->evaluate('string(/n:DPS/n:infDPS/' . $path . ')');
    }

    private function sequencia(DOMXPath $xpath, string $path): array
    {
        $nomes = [];
        foreach ($xpath->query('/n:DPS/n:infDPS' . $path . '/*') as $node) {
            $nomes[] = $node->localName;
        }
        return $nomes;
    }

    public function test_preserva_xml_legado_sem_selecionar_perfil(): void
    {
        $esperado = file_get_contents(__DIR__ . '/fixtures/legado.xml');
        self::assertSame($esperado, (new Dps($this->dados('legado')))->render());
        self::assertSame($esperado, (new Dps($this->dados('legado'), LeiauteDps::LEGADO))->render());
    }

    public function test_monta_nt009_com_sequencia_oficial_e_sem_caminhos_legados(): void
    {
        $xpath = $this->xpath((new Dps($this->dados(), LeiauteDps::NT009))->render());
        self::assertSame(['tpAmb', 'dhEmi', 'verAplic', 'serie', 'nDPS', 'dCompet', 'tpEmit', 'finNFSe',
            'cLocEmi', 'prest', 'toma', 'indDest', 'dest', 'serv', 'valores', 'IBSCBS'], $this->sequencia($xpath, ''));
        self::assertSame(['cTribNac', 'cNBS', 'cIntContrib', 'xDescServ'], $this->sequencia($xpath, '/n:serv/n:cServ'));
        self::assertSame(['CST', 'cClassTrib', 'gIBSCBS'], $this->sequencia($xpath, '/n:IBSCBS/n:valores/n:trib'));
        self::assertSame(['cCredPres', 'gTribRegular', 'gDif'], $this->sequencia($xpath, '/n:IBSCBS/n:valores/n:trib/n:gIBSCBS'));
        self::assertSame(['CNPJ', 'xNome', 'end', 'fone', 'email'], $this->sequencia($xpath, '/n:dest'));
        self::assertSame('0', $this->valor($xpath, 'n:finNFSe'));
        self::assertSame(0, $xpath->query('//n:IBSCBS/n:finNFSe | //n:IBSCBS/n:indDest | //n:IBSCBS/n:dest | //n:gIBSCBS/n:CST | //n:gIBSCBS/n:cClassTrib')->length);
        self::assertSame('1.01', $xpath->evaluate('string(/n:DPS/@versao)'));
    }

    public function test_perfil_nao_e_escolhido_pelo_ambiente(): void
    {
        foreach ([1, 2] as $ambiente) {
            $novo = $this->dados();
            $novo->infDPS->tpAmb = $ambiente;
            $xpath = $this->xpath((new Dps($novo, LeiauteDps::NT009))->render());
            self::assertSame((string)$ambiente, $this->valor($xpath, 'n:tpAmb'));
            self::assertSame('0', $this->valor($xpath, 'n:finNFSe'));

            $legado = $this->dados('legado');
            $legado->infDPS->tpAmb = $ambiente;
            $xpath = $this->xpath((new Dps($legado))->render());
            self::assertSame((string)$ambiente, $this->valor($xpath, 'n:tpAmb'));
            self::assertSame('0', $this->valor($xpath, 'n:IBSCBS/n:finNFSe'));
            self::assertSame(0, $xpath->query('/n:DPS/n:infDPS/n:finNFSe')->length);
        }
    }

    public function test_emite_sem_ibs_cbs_com_finalidade_e_destinatario_na_raiz(): void
    {
        $dados = $this->dados();
        unset($dados->infDPS->IBSCBS, $dados->infDPS->dest);
        $dados->infDPS->indDest = 0;
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame('0', $this->valor($xpath, 'n:finNFSe'));
        self::assertSame('0', $this->valor($xpath, 'n:indDest'));
        self::assertSame(0, $xpath->query('//n:dest | //n:IBSCBS')->length);
    }

    public function test_monta_cst_e_classificacao_sem_detalhamento_gibscbs(): void
    {
        $dados = $this->dados();
        $trib = $dados->infDPS->IBSCBS->valores->trib;
        unset($trib->gIBSCBS);
        $trib->CST = '410';
        $trib->cClassTrib = '410001';
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame(['CST', 'cClassTrib'], $this->sequencia($xpath, '/n:IBSCBS/n:valores/n:trib'));
        self::assertSame('410', $this->valor($xpath, 'n:IBSCBS/n:valores/n:trib/n:CST'));
    }

    public static function opcoes_simples(): array
    {
        return [[3, '1', '07'], [4, '2', '09']];
    }

    #[DataProvider('opcoes_simples')]
    public function test_inclui_campos_do_simples_nacional(int $opcao, string $regime, string $atividade): void
    {
        $dados = $this->dados();
        $dados->infDPS->prest->regTrib->opSimpNac = $opcao;
        $dados->infDPS->prest->regTrib->regApTribSN = 1;
        $dados->infDPS->prest->regTrib->regApIBSCBSSN = $regime;
        $dados->infDPS->serv->cServ->cAtvSN = $atividade;
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame(['opSimpNac', 'regApTribSN', 'regApIBSCBSSN', 'regEspTrib'], $this->sequencia($xpath, '/n:prest/n:regTrib'));
        self::assertSame(['cTribNac', 'cNBS', 'cAtvSN', 'cIntContrib', 'xDescServ'], $this->sequencia($xpath, '/n:serv/n:cServ'));
        self::assertSame($atividade, $this->valor($xpath, 'n:serv/n:cServ/n:cAtvSN'));
        self::assertSame($regime, $this->valor($xpath, 'n:prest/n:regTrib/n:regApIBSCBSSN'));
    }

    public static function campos_obrigatorios(): array
    {
        return [['finNFSe'], ['indDest'], ['regApIBSCBSSN'], ['cAtvSN'], ['CST'], ['cClassTrib']];
    }

    #[DataProvider('campos_obrigatorios')]
    public function test_delega_obrigatoriedade_fiscal_ao_autorizador(string $campo): void
    {
        $dados = $this->dados();
        if (in_array($campo, ['regApIBSCBSSN', 'cAtvSN'], true)) {
            $dados->infDPS->prest->regTrib->opSimpNac = 3;
            $dados->infDPS->prest->regTrib->regApIBSCBSSN = 1;
            $dados->infDPS->serv->cServ->cAtvSN = '07';
            $pai = $campo === 'cAtvSN' ? $dados->infDPS->serv->cServ : $dados->infDPS->prest->regTrib;
        } elseif (in_array($campo, ['CST', 'cClassTrib'], true)) {
            $pai = $dados->infDPS->IBSCBS->valores->trib;
        } else {
            $pai = $dados->infDPS;
        }
        unset($pai->{$campo});
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        $caminho = in_array($campo, ['CST', 'cClassTrib'], true)
            ? '//n:IBSCBS/n:valores/n:trib/n:' . $campo
            : '//n:' . $campo;
        self::assertSame(0, $xpath->query($caminho)->length);
    }

    public static function caminhos_legados(): array
    {
        return [['finNFSe'], ['indDest'], ['dest'], ['CST'], ['cClassTrib'], ['vDedRed'], ['nDI'], ['nRE']];
    }

    #[DataProvider('caminhos_legados')]
    public function test_recusa_payload_com_caminhos_antigos(string $campo): void
    {
        $dados = $this->dados();
        if (in_array($campo, ['finNFSe', 'indDest', 'dest'], true)) {
            $dados->infDPS->IBSCBS->{$campo} = 0;
        } elseif (in_array($campo, ['CST', 'cClassTrib'], true)) {
            $dados->infDPS->IBSCBS->valores->trib->gIBSCBS->{$campo} = '000';
        } elseif ($campo === 'vDedRed') {
            $dados->infDPS->valores->vDedRed = new stdClass();
        } else {
            $dados->infDPS->serv->comExt = (object)[$campo => '123'];
        }
        $this->expectException(InvalidArgumentException::class);
        (new Dps($dados, LeiauteDps::NT009))->render();
    }

    public function test_nao_valida_relacao_fiscal_do_destinatario(): void
    {
        $dados = $this->dados();
        $dados->infDPS->indDest = 0;
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame('0', $this->valor($xpath, 'n:indDest'));
        self::assertSame(1, $xpath->query('/n:DPS/n:infDPS/n:dest')->length);
    }

    public static function ajustes(): array
    {
        return [['pAjusteBCISSQN', '10.00'], ['vAjusteBCISSQN', '10.00'], ['vAjusteBCIBSCBSComExt', '-5.00']];
    }

    #[DataProvider('ajustes')]
    public function test_monta_ajuste_de_base_sem_alterar_descontos_e_retencoes(string $campo, string $valor): void
    {
        $dados = $this->dados();
        $dados->infDPS->valores->vAjusteBC = (object)[$campo => $valor];
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame(['vServPrest', 'vDescCondIncond', 'vAjusteBC', 'trib'], $this->sequencia($xpath, '/n:valores'));
        self::assertSame($valor, $this->valor($xpath, 'n:valores/n:vAjusteBC/n:' . $campo));
        self::assertSame('5.00', $this->valor($xpath, 'n:valores/n:vDescCondIncond/n:vDescIncond'));
        self::assertSame('6.50', $this->valor($xpath, 'n:valores/n:trib/n:tribFed/n:vRetIRRF'));
        self::assertSame('0.62', $this->valor($xpath, 'n:valores/n:trib/n:tribFed/n:piscofins/n:vPis'));
    }

    public function test_adapta_comercio_exterior_e_municipio_do_evento(): void
    {
        $dados = $this->dados();
        $dados->infDPS->serv->comExt = (object)[
            'mdPrestacao' => 5, 'vincPrest' => 0, 'tpMoeda' => '840', 'nFatura' => '123',
            'vFatura' => '100.00', 'vServMoeda' => '100.00', 'mecAFComexP' => '01',
            'mecAFComexT' => '01', 'movTempBens' => 1, 'nDUIMP' => '123456789012345',
            'nDUE' => '12345678901234', 'mdic' => 0, 'nContCambio' => '456',
        ];
        $dados->infDPS->serv->atvEvento = (object)[
            'xNome' => 'Evento de teste', 'dtIni' => '2026-10-20', 'dtFim' => '2026-10-21',
            'cMun' => '4316808', 'idAtvEvt' => 'EVENTO-1',
        ];
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame(['mdPrestacao', 'vincPrest', 'tpMoeda', 'nFatura', 'vFatura', 'vServMoeda',
            'mecAFComexP', 'mecAFComexT', 'movTempBens', 'nDUIMP', 'nDUE', 'mdic', 'nContCambio'],
            $this->sequencia($xpath, '/n:serv/n:comExt'));
        self::assertSame(['xNome', 'dtIni', 'dtFim', 'cMun', 'idAtvEvt'], $this->sequencia($xpath, '/n:serv/n:atvEvento'));
        self::assertSame(0, $xpath->query('//n:nDI | //n:nRE')->length);
    }

    public static function inscricoes(): array
    {
        return [['12345678000195'], ['12ABC34501DE35']];
    }

    #[DataProvider('inscricoes')]
    public function test_preserva_cnpj_no_xml_e_no_identificador(string $cnpj): void
    {
        $dados = $this->dados();
        $dados->infDPS->prest->CNPJ = $cnpj;
        $dps = new Dps($dados, LeiauteDps::NT009);
        $xpath = $this->xpath($dps->render());
        self::assertSame($cnpj, $this->valor($xpath, 'n:prest/n:CNPJ'));
        self::assertSame('DPS43168082' . $cnpj . '00002000000000000101', $dps->getDpsId());
    }

    public function test_preserva_perfil_ao_substituir_dados_e_renderizar_novamente(): void
    {
        $dps = new Dps(null, LeiauteDps::NT009);
        $dps->setStd($this->dados());
        $primeiro = $dps->render();
        self::assertSame($primeiro, $dps->render());
        self::assertSame($primeiro, $dps->render($this->dados()));
        $dados = $this->dados();
        $dados->infDPS->nDPS = '102';
        $dps->setStd($dados);
        $xpath = $this->xpath($dps->render());
        self::assertSame('102', $this->valor($xpath, 'n:nDPS'));
        self::assertSame(1, $xpath->query('/n:DPS/n:infDPS/n:finNFSe')->length);
    }

    public function test_mantem_substituicao_no_novo_leiaute(): void
    {
        $dados = $this->dados();
        $dados->infDPS->cMotivoEmisTI = 1;
        $dados->infDPS->chNFSeRej = str_repeat('2', 50);
        $dados->infDPS->subst = (object)['chSubstda' => str_repeat('1', 50), 'cMotivo' => '99', 'xMotivo' => 'Outros'];
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        $nomes = $this->sequencia($xpath, '');
        self::assertSame(['tpEmit', 'cMotivoEmisTI', 'finNFSe', 'chNFSeRej', 'cLocEmi', 'subst', 'prest'], array_slice($nomes, 6, 7));
        self::assertSame(str_repeat('1', 50), $this->valor($xpath, 'n:subst/n:chSubstda'));
    }

    public function test_preserva_evento_de_cancelamento_nos_dois_perfis(): void
    {
        foreach (LeiauteDps::cases() as $perfil) {
            $dps = new Dps($this->dados('evento'), $perfil);
            self::assertSame(file_get_contents(__DIR__ . '/fixtures/evento.xml'), $dps->renderEvento());
            self::assertSame('PRE' . str_repeat('1', 50) . '101101', $dps->getEventoId());
        }
    }

    public function test_nao_valida_lista_fiscal_de_finalidades(): void
    {
        $dados = $this->dados();
        $dados->infDPS->finNFSe = 7;
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame('7', $this->valor($xpath, 'n:finNFSe'));
    }

    public function test_nao_exige_registros_ausentes_em_grupo_documental(): void
    {
        $dados = $this->dados();
        $dados->infDPS->valores->vAjusteBC = (object)['documentos' => new stdClass()];
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame(1, $xpath->query('//n:vAjusteBC/n:documentos')->length);
    }
    public static function codigos_de_dois_digitos(): array
    {
        return [[7, '07'], ['7', '07'], ['07', '07'], [99, '99'], [123, '123']];
    }

    #[DataProvider('codigos_de_dois_digitos')]
    public function test_normaliza_codigos_sem_tabela_fiscal_ou_truncamento($entrada, string $esperado): void
    {
        $dados = $this->dados();
        $dados->infDPS->serv->cServ->cAtvSN = $entrada;
        $dados->infDPS->tpNFSeCredito = $entrada;
        $dados->infDPS->tpNFSeDebito = $entrada;
        // opSimpNac=1 e finNFSe=0 não impedem que dados incompatíveis cheguem ao autorizador.
        $xpath = $this->xpath((new Dps($dados, LeiauteDps::NT009))->render());
        self::assertSame($esperado, $this->valor($xpath, 'n:serv/n:cServ/n:cAtvSN'));
        self::assertSame($esperado, $this->valor($xpath, 'n:tpNFSeCredito'));
        self::assertSame($esperado, $this->valor($xpath, 'n:tpNFSeDebito'));
    }

    public function test_exige_dados_tecnicos_para_compor_identificador(): void
    {
        $dados = $this->dados();
        unset($dados->infDPS->nDPS);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('identificador');
        (new Dps($dados, LeiauteDps::NT009))->render();
    }

}
