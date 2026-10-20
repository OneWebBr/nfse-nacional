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

class NovosGruposTest extends TestCase
{
    private function dados(): stdClass
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/nt009.json'), false, 512, JSON_THROW_ON_ERROR);
    }

    private function xpath(stdClass $dados): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML((new Dps($dados, LeiauteDps::NT009))->render()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
        return $xpath;
    }

    private function nomes(DOMXPath $xpath, string $caminho): array
    {
        return array_map(fn($node) => $node->localName, iterator_to_array($xpath->query($caminho . '/*')));
    }

    private function pagamento(int $numero): stdClass
    {
        return (object)[
            'nPag' => $numero, 'idTransacao' => 'TRANSACAO-' . $numero,
            'tpMeioPgto' => '17', 'CNPJReceb' => '12ABC34501DE35', 'CNPJBasePSP' => '12345678',
        ];
    }

    private function documento(string $tipo): stdClass
    {
        $identificacoes = [
            'dFeNacional' => (object)['tipoChaveDFe' => 1, 'chaveDFe' => str_repeat('1', 50)],
            'docFiscalOutro' => (object)['cMunDocFiscal' => '4316808', 'nDocFiscal' => '0001', 'xDocFiscal' => 'Documento de teste'],
            'docOutro' => (object)['nDoc' => '0002', 'xDoc' => 'Comprovante de teste'],
        ];
        return (object)[
            'tpAjusteBC' => '7', 'vTotDoc' => '20.00', 'vAjusteAplic' => '10.00',
            $tipo => $identificacoes[$tipo],
        ];
    }

    private function ajuste(string $finalidade, string $tipo): stdClass
    {
        $dados = $this->dados();
        $dps = $dados->infDPS;
        $dps->finNFSe = $finalidade;
        $dps->{$finalidade === '1' ? 'tpNFSeCredito' : 'tpNFSeDebito'} = $tipo;
        unset($dps->prest->regTrib->regEspTrib);
        $comServico = ($finalidade === '2' && in_array($tipo, ['04', '06'], true))
            || ($finalidade === '1' && $tipo === '01');
        if ($comServico) {
            unset($dps->valores->trib);
            if ($finalidade === '1' || $tipo !== '06') {
                unset($dps->valores->vDescCondIncond);
            }
        } else {
            unset($dps->serv, $dps->valores, $dps->indDest, $dps->dest, $dps->toma);
            $dps->IBSCBS = (object)['valores' => (object)['trib' => (object)[
                'CST' => '000', 'cClassTrib' => '000001',
                'gIBSCBS' => (object)['gIBSCBSAjuste' => (object)['vIBS' => '1.00', 'vCBS' => '2.00']],
            ]]];
        }
        return $dados;
    }

    public function test_monta_referencias_e_pagamentos_na_ordem_oficial(): void
    {
        $dados = $this->dados();
        $ibs = $dados->infDPS->IBSCBS;
        $ibs->tpOper = 2;
        $ibs->indZFMALC = 0;
        $ibs->indDoacao = 1;
        $ibs->gRefNFSe = (object)['refNFSe' => [str_repeat('1', 50), str_repeat('2', 50)]];
        $ibs->gPgtoVinc = (object)['pgto' => [$this->pagamento(1), $this->pagamento(2)]];
        $xpath = $this->xpath($dados);
        self::assertSame(['indFinal', 'cIndOp', 'indZFMALC', 'tpOper', 'gRefNFSe', 'tpEnteGov', 'indDoacao', 'valores', 'gPgtoVinc'],
            $this->nomes($xpath, '//n:IBSCBS'));
        self::assertSame(2, $xpath->query('//n:gRefNFSe/n:refNFSe')->length);
        self::assertSame(2, $xpath->query('//n:gPgtoVinc/n:pgto')->length);
        self::assertSame(['nPag', 'idTransacao', 'tpMeioPgto', 'CNPJReceb', 'CNPJBasePSP'], $this->nomes($xpath, '(//n:pgto)[1]'));
        self::assertSame('12ABC34501DE35', $xpath->evaluate('string((//n:pgto/n:CNPJReceb)[1])'));
    }

    public function test_monta_estorno_e_referencias_de_pagamento_antecipado(): void
    {
        $dados = $this->dados();
        $g = $dados->infDPS->IBSCBS->valores->trib->gIBSCBS;
        $g->gEstornoCred = (object)['vIBSEstCred' => '0.10', 'vCBSEstCred' => '0.20'];
        $g->gPagAntecipado = (object)['refNFSe' => [str_repeat('3', 50)]];
        $xpath = $this->xpath($dados);
        self::assertSame(['cCredPres', 'gTribRegular', 'gDif', 'gEstornoCred', 'gPagAntecipado'], $this->nomes($xpath, '//n:gIBSCBS'));
        self::assertSame('0.20', $xpath->evaluate('string(//n:gEstornoCred/n:vCBSEstCred)'));
        self::assertSame(str_repeat('3', 50), $xpath->evaluate('string(//n:gPagAntecipado/n:refNFSe)'));
    }

    public static function identificacoes_documentais(): array
    {
        return [['dFeNacional'], ['docFiscalOutro'], ['docOutro']];
    }

    #[DataProvider('identificacoes_documentais')]
    public function test_monta_documentos_de_ajuste_com_fornecedor(string $tipo): void
    {
        $dados = $this->dados();
        $documento = $this->documento($tipo);
        $documento->fornec = (object)[
            'CNPJ' => '12ABC34501DE35', 'xNome' => 'FORNECEDOR DE TESTE',
            'end' => (object)['endNac' => (object)['cMun' => '4316808', 'CEP' => '96810000']],
            'xLgr' => 'Rua de teste', 'nro' => '100', 'xBairro' => 'Centro',
        ];
        $dados->infDPS->valores->vAjusteBC = (object)[
            'documentos' => (object)['docAjusteBC' => [$documento]], 'vAjusteBCIBSCBSComExt' => '-1.00',
        ];
        $xpath = $this->xpath($dados);
        self::assertSame(['documentos', 'vAjusteBCIBSCBSComExt'], $this->nomes($xpath, '//n:vAjusteBC'));
        self::assertSame(['tpAjusteBC', 'vTotDoc', 'vAjusteAplic', $tipo, 'fornec'], $this->nomes($xpath, '//n:docAjusteBC'));
        self::assertSame('12ABC34501DE35', $xpath->evaluate('string(//n:fornec/n:CNPJ)'));
    }

    public static function modalidades(): array
    {
        return [['2', '01'], ['2', '02'], ['2', '03'], ['2', '04'], ['2', '05'], ['2', '06'], ['1', '01'], ['1', '05']];
    }

    #[DataProvider('modalidades')]
    public function test_monta_tipos_de_credito_e_debito_informados(string $finalidade, string $tipo): void
    {
        $dados = $this->ajuste($finalidade, $tipo);
        $xpath = $this->xpath($dados);
        $campo = $finalidade === '1' ? 'tpNFSeCredito' : 'tpNFSeDebito';
        self::assertSame($tipo, $xpath->evaluate('string(/n:DPS/n:infDPS/n:' . $campo . ')'));
        self::assertSame($finalidade, $xpath->evaluate('string(/n:DPS/n:infDPS/n:finNFSe)'));
        self::assertSame(isset($dados->infDPS->serv) ? 1 : 0, $xpath->query('/n:DPS/n:infDPS/n:serv')->length);
        self::assertSame(0, $xpath->query('/n:DPS/n:infDPS/n:valores/n:trib')->length);
        if (!isset($dados->infDPS->serv)) {
            self::assertSame('2.00', $xpath->evaluate('string(//n:gIBSCBS/n:gIBSCBSAjuste/n:vCBS)'));
        }
    }

    public function test_nao_descarta_grupo_por_modalidade_fiscal(): void
    {
        $dados = $this->ajuste('2', '01');
        $dados->infDPS->serv = $this->dados()->infDPS->serv;
        $xpath = $this->xpath($dados);
        self::assertSame(1, $xpath->query('/n:DPS/n:infDPS/n:serv')->length);
    }

    public static function grupos_excluidos(): array
    {
        return [['imovel'], ['bensMoveis'], ['condominios']];
    }

    #[DataProvider('grupos_excluidos')]
    public function test_mantem_bloqueio_dos_grupos_fora_do_escopo(string $grupo): void
    {
        $dados = $this->dados();
        $dados->infDPS->IBSCBS->{$grupo} = new stdClass();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($grupo);
        (new Dps($dados, LeiauteDps::NT009))->render();
    }

    public function test_delega_escolha_fiscal_documental_ao_autorizador(): void
    {
        $dados = $this->dados();
        $documento = $this->documento('docOutro');
        $documento->dFeNacional = $this->documento('dFeNacional')->dFeNacional;
        $dados->infDPS->valores->vAjusteBC = (object)['documentos' => (object)['docAjusteBC' => [$documento]]];
        $xpath = $this->xpath($dados);
        self::assertSame(1, $xpath->query('//n:docAjusteBC/n:docOutro')->length);
        self::assertSame(1, $xpath->query('//n:docAjusteBC/n:dFeNacional')->length);
    }

    public function test_nao_valida_duplicidade_e_codigos_fiscais_dos_pagamentos(): void
    {
        $dados = $this->dados();
        $pagamento = $this->pagamento(1);
        $pagamento->tpMeioPgto = '99';
        $pagamento->idTransacao = 'X';
        $dados->infDPS->IBSCBS->gPgtoVinc = (object)['pgto' => [$pagamento, $pagamento]];
        $xpath = $this->xpath($dados);
        self::assertSame(2, $xpath->query('//n:pgto')->length);
        self::assertSame('99', $xpath->evaluate('string((//n:pgto/n:tpMeioPgto)[1])'));
    }

    public static function quantidades_de_registros(): array
    {
        return [[0], [100]];
    }

    #[DataProvider('quantidades_de_registros')]
    public function test_nao_aplica_cardinalidade_fiscal_as_listas(int $quantidade): void
    {
        $dados = $this->dados();
        $dados->infDPS->IBSCBS->tpOper = 2;
        $dados->infDPS->IBSCBS->gRefNFSe = (object)['refNFSe' => array_fill(0, $quantidade, 'CHAVE-FICTICIA')];
        $xpath = $this->xpath($dados);
        self::assertSame($quantidade, $xpath->query('//n:gRefNFSe/n:refNFSe')->length);
    }

    public function test_nao_exige_referencia_por_tipo_de_operacao(): void
    {
        $dados = $this->dados();
        $dados->infDPS->IBSCBS->tpOper = 3;
        $xpath = $this->xpath($dados);
        self::assertSame(0, $xpath->query('//n:gRefNFSe')->length);
    }

    public function test_preserva_o_grupo_de_obra_existente(): void
    {
        $dados = $this->dados();
        $dados->infDPS->serv->obra = (object)['cObra' => '12345'];
        $xpath = $this->xpath($dados);
        self::assertSame('12345', $xpath->evaluate('string(//n:serv/n:obra/n:cObra)'));
    }
    public static function estruturas_invalidas(): array
    {
        return [
            ['pgto', 'nao_e_lista'],
            ['objeto', ['nao_e_objeto']],
            ['folha', (object)['nao_e_valor' => 'teste']],
            ['campo', 'campo_desconhecido'],
        ];
    }

    #[DataProvider('estruturas_invalidas')]
    public function test_preserva_protecoes_tecnicas_do_montador(string $caso, $valor): void
    {
        $dados = $this->dados();
        $pgto = $this->pagamento(1);
        $dados->infDPS->IBSCBS->gPgtoVinc = (object)['pgto' => [$pgto]];
        if ($caso === 'pgto') {
            $dados->infDPS->IBSCBS->gPgtoVinc->pgto = $valor;
        } elseif ($caso === 'objeto') {
            $dados->infDPS->IBSCBS = $valor;
        } elseif ($caso === 'folha') {
            $pgto->idTransacao = $valor;
        } else {
            $pgto->{$valor} = 'teste';
        }
        $this->expectException(InvalidArgumentException::class);
        (new Dps($dados, LeiauteDps::NT009))->render();
    }

}
