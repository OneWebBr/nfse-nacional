<?php

require __DIR__ . '/../vendor/autoload.php';

use Hadder\NfseNacional\Dps;
use Hadder\NfseNacional\LeiauteDps;

// Dados fictícios. Geração local, sem assinatura nem transmissão.
// O perfil não seleciona o ambiente: tpAmb continua sendo informado na DPS.
// Confirme a disponibilidade do leiaute no autorizador antes de transmitir.
$dados = json_decode(<<<'JSON'
{
    "infDPS": {
        "tpAmb": 2,
        "dhEmi": "2026-10-20T10:00:00-03:00",
        "verAplic": "TesteNT009",
        "serie": "2",
        "nDPS": "101",
        "dCompet": "2026-10-20",
        "tpEmit": 1,
        "cLocEmi": "4316808",
        "prest": {
            "CNPJ": "12345678000195",
            "regTrib": {
                "opSimpNac": 1,
                "regEspTrib": 0
            }
        },
        "toma": {
            "CPF": "12345678909",
            "xNome": "TOMADOR DE TESTE"
        },
        "serv": {
            "locPrest": {
                "cLocPrestacao": "4316808"
            },
            "cServ": {
                "cTribNac": "010301",
                "cNBS": "115090000",
                "cIntContrib": "TESTE",
                "xDescServ": "Serviço de teste e validação"
            }
        },
        "valores": {
            "vServPrest": {
                "vServ": "100.00"
            },
            "vDescCondIncond": {
                "vDescIncond": "5.00",
                "vDescCond": "1.00"
            },
            "trib": {
                "tribMun": {
                    "tribISSQN": 1,
                    "tpRetISSQN": 1
                },
                "tribFed": {
                    "piscofins": {
                        "CST": "01",
                        "vBCPisCofins": "95.00",
                        "pAliqPis": "0.65",
                        "pAliqCofins": "3.00",
                        "vPis": "0.62",
                        "vCofins": "2.85",
                        "tpRetPisCofins": 2
                    },
                    "vRetCP": "0.00",
                    "vRetIRRF": "6.50",
                    "vRetCSLL": "0.00"
                },
                "totTrib": {
                    "pTotTrib": {
                        "pTotTribFed": "13.45",
                        "pTotTribEst": "17.00",
                        "pTotTribMun": "2.00"
                    }
                }
            }
        },
        "finNFSe": 0,
        "indDest": 0
    }
}
JSON, false, 512, JSON_THROW_ON_ERROR);

$dps = new Dps($dados, LeiauteDps::NT009);
echo $dps->render();
