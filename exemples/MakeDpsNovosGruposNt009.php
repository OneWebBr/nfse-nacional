<?php

require __DIR__ . '/../vendor/autoload.php';

use Hadder\NfseNacional\Dps;
use Hadder\NfseNacional\LeiauteDps;

// Release 1 de produção restrita de 20/10/2026. Dados fictícios, sem transmissão.
// Uso: php exemples/MakeDpsNovosGruposNt009.php documentos|bens-imateriais|imoveis|bens-moveis
// Os exemplos validam a estrutura XSD, não o enquadramento ou autorização fiscal.
$exemplo = $argv[1] ?? 'documentos';
if (!in_array($exemplo, ['documentos', 'bens-imateriais', 'imoveis', 'bens-moveis'], true)) {
    throw new InvalidArgumentException('Exemplo inválido.');
}
$dados = json_decode(file_get_contents(__DIR__ . '/dados-nt009/' . $exemplo . '.json'), false, 512, JSON_THROW_ON_ERROR);
echo (new Dps($dados, LeiauteDps::NT009))->render();
