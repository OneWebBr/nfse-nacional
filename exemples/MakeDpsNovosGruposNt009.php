<?php

require __DIR__ . '/../vendor/autoload.php';

use Hadder\NfseNacional\Dps;
use Hadder\NfseNacional\LeiauteDps;

// Documentos de ajuste de base do release 1. Dados fictícios, sem transmissão.
$dados = json_decode(file_get_contents(__DIR__ . '/dados-nt009/documentos.json'), false, 512, JSON_THROW_ON_ERROR);
echo (new Dps($dados, LeiauteDps::NT009))->render();
