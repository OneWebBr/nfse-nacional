<?php

require __DIR__ . '/../vendor/autoload.php';

use Hadder\NfseNacional\Dps;
use Hadder\NfseNacional\LeiauteDps;

// Exemplos estruturais com dados fictícios, sem certificado ou transmissão.
// Uso: php exemples/MakeDpsNovosGruposNt009.php pagamentos|documentos|credito|debito
// Não representam enquadramento fiscal nem autorização pelo governo.
$exemplo = $argv[1] ?? 'pagamentos';
if (!in_array($exemplo, ['pagamentos', 'documentos', 'credito', 'debito'], true)) {
    throw new InvalidArgumentException('Exemplo inválido.');
}
$dados = json_decode(file_get_contents(__DIR__ . '/dados-nt009/' . $exemplo . '.json'), false, 512, JSON_THROW_ON_ERROR);
echo (new Dps($dados, LeiauteDps::NT009))->render();
