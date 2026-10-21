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

    private function validar(stdClass $dados): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML((new Dps($dados, LeiauteDps::NT009))->render()));
        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $valido = $dom->schemaValidate(__DIR__ . '/../storage/schemes/nt009-release1-20261020/DPS_v1.01.xsd');
            self::assertTrue($valido, implode("\n", array_map(fn($e) => trim($e->message), libxml_get_errors())));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
        return $xpath;
    }

    public function test_monta_referencias_na_posicao_do_release(): void
    {
        $dados = $this->dados();
        $dados->infDPS->IBSCBS->gRefNFSe = (object)['refNFSe' => [str_repeat('1', 50), str_repeat('2', 50)]];
        $xpath = $this->validar($dados);
        self::assertSame(2, $xpath->query('//n:IBSCBS/n:gRefNFSe/n:refNFSe')->length);
    }

    public static function identificacoes_documentais(): array
    {
        return [['dFeNacional'], ['docFiscalOutro'], ['docOutro']];
    }

    #[DataProvider('identificacoes_documentais')]
    public function test_monta_documentos_de_ajuste_com_fornecedor(string $tipo): void
    {
        $dados = $this->dados();
        $identificacao = match ($tipo) {
            'dFeNacional' => (object)['tipoChaveDFe' => 1, 'chaveDFe' => str_repeat('1', 50)],
            'docFiscalOutro' => (object)['cMunDocFiscal' => '4316808', 'nDocFiscal' => '0001', 'xDocFiscal' => 'Documento de teste'],
            'docOutro' => (object)['nDoc' => '0002', 'xDoc' => 'Comprovante de teste'],
        };
        $documento = (object)[
            'tpAjusteBC' => '7', 'vTotDoc' => '20.00', 'vAjusteAplic' => '10.00',
            $tipo => $identificacao,
            'fornec' => (object)[
                'CNPJ' => '12345678000195', 'xNome' => 'FORNECEDOR DE TESTE',
                'end' => (object)[
                    'endNac' => (object)['cMun' => '4316808', 'CEP' => '96810000'],
                    'xLgr' => 'Rua Teste', 'nro' => '100', 'xBairro' => 'Centro',
                ],
            ],
        ];
        $dados->infDPS->valores->vAjusteBC = (object)['documentos' => (object)['docAjusteBC' => [$documento]]];
        $xpath = $this->validar($dados);
        self::assertSame('10.00', $xpath->evaluate('string(//n:docAjusteBC/n:vAjusteAplic)'));
        self::assertSame('12345678000195', $xpath->evaluate('string(//n:docAjusteBC/n:fornec/n:CNPJ)'));
        self::assertSame(0, $xpath->query('//n:vDedRed | //n:gReeRepRes')->length);
    }

    public static function ajustes(): array
    {
        return [['pAjusteBCISSQN'], ['vAjusteBCISSQN']];
    }

    #[DataProvider('ajustes')]
    public function test_monta_ajuste_de_base_sem_alterar_descontos_e_retencoes(string $campo): void
    {
        $dados = $this->dados();
        $dados->infDPS->valores->vAjusteBC = (object)[$campo => '10.00'];
        $xpath = $this->validar($dados);
        self::assertSame('10.00', $xpath->evaluate('string(//n:vAjusteBC/n:' . $campo . ')'));
        self::assertSame('5.00', $xpath->evaluate('string(//n:vDescIncond)'));
        self::assertSame('6.50', $xpath->evaluate('string(//n:vRetIRRF)'));
        self::assertSame('0.62', $xpath->evaluate('string(//n:vPis)'));
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
        (new Dps($dados, LeiauteDps::NT009))->render();
    }

    public function test_preserva_o_grupo_de_obra_existente(): void
    {
        $dados = $this->dados();
        $dados->infDPS->serv->obra = (object)['cObra' => '12345'];
        self::assertSame('12345', $this->validar($dados)->evaluate('string(//n:serv/n:obra/n:cObra)'));
    }

    public function test_recusa_estrutura_invalida_de_lista_documental(): void
    {
        $dados = $this->dados();
        $dados->infDPS->valores->vAjusteBC = (object)['documentos' => (object)['docAjusteBC' => new stdClass()]];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lista');
        (new Dps($dados, LeiauteDps::NT009))->render();
    }
}
