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

    private function documento(stdClass $dados): DOMDocument
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML((new Dps($dados, LeiauteDps::NT009))->render()));
        return $dom;
    }

    private function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
        return $xpath;
    }

    private function validar(DOMDocument $dom, bool $legado = false): array
    {
        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $schema = __DIR__ . '/../storage/schemes/' . ($legado ? '' : 'nt009-release1-20261020/') . 'DPS_v1.01.xsd';
            $valido = $dom->schemaValidate($schema);
            $erros = array_map(fn($erro) => trim($erro->message), libxml_get_errors());
            return [$valido, implode("\n", $erros)];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }

    public function test_preserva_xml_legado_sem_selecionar_perfil(): void
    {
        $esperado = file_get_contents(__DIR__ . '/fixtures/legado.xml');
        self::assertSame($esperado, (new Dps($this->dados('legado')))->render());
        self::assertSame($esperado, (new Dps($this->dados('legado'), LeiauteDps::LEGADO))->render());
    }

    public function test_valida_legado_sem_destinatario_especifico_no_xsd_anterior(): void
    {
        $dados = $this->dados('legado');
        unset($dados->infDPS->IBSCBS->dest);
        $dom = new DOMDocument();
        $dom->loadXML((new Dps($dados))->render());
        [$valido, $erros] = $this->validar($dom, true);
        self::assertTrue($valido, $erros);
    }

    public function test_monta_release_1_com_sequencia_e_xsd_oficiais(): void
    {
        $dom = $this->documento($this->dados());
        [$valido, $erros] = $this->validar($dom);
        self::assertTrue($valido, $erros);
        $xpath = $this->xpath($dom);
        $nomes = fn(string $path) => array_map(fn($node) => $node->localName, iterator_to_array($xpath->query($path . '/*')));
        self::assertSame(['tpAmb', 'dhEmi', 'verAplic', 'serie', 'nDPS', 'dCompet', 'tpEmit', 'finNFSe',
            'cLocEmi', 'prest', 'toma', 'serv', 'valores', 'IBSCBS'], $nomes('/n:DPS/n:infDPS'));
        self::assertSame(['cTribNac', 'cNBS', 'cIntContrib', 'xDescServ'], $nomes('//n:cServ'));
        self::assertSame(['CST', 'cClassTrib', 'gIBSCBS'], $nomes('//n:IBSCBS/n:valores/n:trib'));
        self::assertSame('1', $xpath->evaluate('string(//n:IBSCBS/n:indDest)'));
        self::assertSame(1, $xpath->query('//n:IBSCBS/n:dest')->length);
        self::assertSame(0, $xpath->query('/n:DPS/n:infDPS/n:dest | /n:DPS/n:infDPS/n:indDest | //n:IBSCBS/n:finNFSe | //n:gIBSCBS/n:CST')->length);
    }

    public function test_perfil_nao_e_escolhido_pelo_ambiente(): void
    {
        foreach ([1, 2] as $ambiente) {
            $dados = $this->dados();
            $dados->infDPS->tpAmb = $ambiente;
            $dom = $this->documento($dados);
            self::assertSame((string)$ambiente, $this->xpath($dom)->evaluate('string(//n:tpAmb)'));
            self::assertSame(1, $this->xpath($dom)->query('/n:DPS/n:infDPS/n:finNFSe')->length);
            $legado = $this->dados('legado');
            $legado->infDPS->tpAmb = $ambiente;
            $xml = (new Dps($legado))->render();
            $dom->loadXML($xml);
            self::assertSame(0, $this->xpath($dom)->query('/n:DPS/n:infDPS/n:finNFSe')->length);
        }
    }

    public function test_emite_sem_ibs_cbs_com_finalidade_na_raiz(): void
    {
        $dados = $this->dados();
        unset($dados->infDPS->IBSCBS);
        $dom = $this->documento($dados);
        [$valido, $erros] = $this->validar($dom);
        self::assertTrue($valido, $erros);
        self::assertSame('0', $this->xpath($dom)->evaluate('string(/n:DPS/n:infDPS/n:finNFSe)'));
        self::assertSame(0, $this->xpath($dom)->query('//n:IBSCBS')->length);
    }

    public function test_nbs_opcional_e_classificacao_sem_detalhamento(): void
    {
        $dados = $this->dados();
        unset($dados->infDPS->serv->cServ->cNBS, $dados->infDPS->IBSCBS->valores->trib->gIBSCBS);
        $dados->infDPS->IBSCBS->indDest = 0;
        unset($dados->infDPS->IBSCBS->dest);
        $dom = $this->documento($dados);
        [$valido, $erros] = $this->validar($dom);
        self::assertTrue($valido, $erros);
        self::assertSame(0, $this->xpath($dom)->query('//n:cNBS | //n:gIBSCBS')->length);
    }

    public function test_reproduz_e1235_sem_finalidade_e_delega_validacao_ao_xsd(): void
    {
        $dados = $this->dados();
        unset($dados->infDPS->finNFSe);
        $dom = $this->documento($dados);
        [$valido, $erros] = $this->validar($dom);
        self::assertFalse($valido);
        self::assertStringContainsString('cLocEmi', $erros);
        self::assertStringContainsString('finNFSe', $erros);
        $dados->infDPS->finNFSe = 0;
        [$valido, $erros] = $this->validar($this->documento($dados));
        self::assertTrue($valido, $erros);
    }

    public function test_xsd_restringe_finalidade_a_nota_regular_sem_validador_php(): void
    {
        $dados = $this->dados();
        $dados->infDPS->finNFSe = 1;
        $dom = $this->documento($dados);
        self::assertSame('1', $this->xpath($dom)->evaluate('string(//n:finNFSe)'));
        [$valido, $erros] = $this->validar($dom);
        self::assertFalse($valido);
        self::assertStringContainsString('finNFSe', $erros);
    }

    public function test_mantem_simples_comercio_exterior_e_evento_do_release(): void
    {
        $dados = $this->dados();
        $dados->infDPS->prest->regTrib->opSimpNac = 3;
        $dados->infDPS->prest->regTrib->regApTribSN = 1;
        $dados->infDPS->serv->comExt = (object)[
            'mdPrestacao' => 1, 'vincPrest' => 0, 'tpMoeda' => '840', 'vServMoeda' => '100.00',
            'mecAFComexP' => '01', 'mecAFComexT' => '01', 'movTempBens' => 1,
            'nDI' => '123456', 'nRE' => '987654', 'mdic' => 0,
        ];
        $dados->infDPS->serv->atvEvento = (object)[
            'xNome' => 'Evento de teste', 'dtIni' => '2026-10-20', 'dtFim' => '2026-10-21', 'idAtvEvt' => 'EVENTO-1',
        ];
        $dom = $this->documento($dados);
        [$valido, $erros] = $this->validar($dom);
        self::assertTrue($valido, $erros);
        $xpath = $this->xpath($dom);
        self::assertSame('123456', $xpath->evaluate('string(//n:comExt/n:nDI)'));
        self::assertSame('987654', $xpath->evaluate('string(//n:comExt/n:nRE)'));
        self::assertSame(0, $xpath->query('//n:regApIBSCBSSN | //n:cAtvSN | //n:nDUIMP | //n:nDUE | //n:atvEvento/n:cMun')->length);
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
        self::assertStringContainsString('<nDPS>102</nDPS>', $dps->render());
    }

    public function test_mantem_substituicao_no_novo_leiaute(): void
    {
        $dados = $this->dados();
        $dados->infDPS->cMotivoEmisTI = 1;
        $dados->infDPS->chNFSeRej = str_repeat('2', 50);
        $dados->infDPS->subst = (object)['chSubstda' => str_repeat('1', 50), 'cMotivo' => '99', 'xMotivo' => 'Correção de dados da nota original'];
        $dom = $this->documento($dados);
        [$valido, $erros] = $this->validar($dom);
        self::assertTrue($valido, $erros);
        $xpath = $this->xpath($dom);
        $nomes = array_map(fn($n) => $n->localName, iterator_to_array($xpath->query('/n:DPS/n:infDPS/*')));
        self::assertSame(['tpEmit', 'cMotivoEmisTI', 'finNFSe', 'chNFSeRej', 'cLocEmi', 'subst', 'prest'], array_slice($nomes, 6, 7));
    }

    public function test_preserva_evento_de_cancelamento_nos_dois_perfis(): void
    {
        foreach (LeiauteDps::cases() as $perfil) {
            $dps = new Dps($this->dados('evento'), $perfil);
            self::assertSame(file_get_contents(__DIR__ . '/fixtures/evento.xml'), $dps->renderEvento());
            self::assertSame('PRE' . str_repeat('1', 50) . '101101', $dps->getEventoId());
        }
    }

    public function test_preserva_cnpj_alfanumerico_e_identificador(): void
    {
        $dados = $this->dados();
        $dados->infDPS->prest->CNPJ = '12ABC34501DE35';
        $dps = new Dps($dados, LeiauteDps::NT009);
        self::assertStringContainsString('<CNPJ>12ABC34501DE35</CNPJ>', $dps->render());
        self::assertSame('DPS43168082' . '12ABC34501DE35' . '00002000000000000101', $dps->getDpsId());
    }

    public function test_exige_dados_tecnicos_para_compor_identificador(): void
    {
        $dados = $this->dados();
        unset($dados->infDPS->nDPS);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('identificador');
        (new Dps($dados, LeiauteDps::NT009))->render();
    }

    public static function campos_antecipados(): array
    {
        return [
            ['infDPS', 'tpNFSeCredito'], ['infDPS', 'tpNFSeDebito'], ['infDPS', 'indDest'], ['infDPS', 'dest'],
            ['prest', 'regApIBSCBSSN'], ['serv', 'cAtvSN'], ['comExt', 'nDUIMP'], ['comExt', 'nDUE'],
            ['IBSCBS', 'gPgtoVinc'], ['IBSCBS', 'gEstornoCred'],
        ];
    }

    #[DataProvider('campos_antecipados')]
    public function test_nao_aceita_antecipacoes_ausentes_no_release(string $pai, string $campo): void
    {
        $dados = $this->dados();
        $destino = match ($pai) {
            'prest' => $dados->infDPS->prest->regTrib,
            'serv' => $dados->infDPS->serv->cServ,
            'comExt' => $dados->infDPS->serv->comExt = new stdClass(),
            'IBSCBS' => $dados->infDPS->IBSCBS,
            default => $dados->infDPS,
        };
        $destino->{$campo} = 1;
        $this->expectException(InvalidArgumentException::class);
        (new Dps($dados, LeiauteDps::NT009))->render();
    }
}
