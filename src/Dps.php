<?php

namespace Hadder\NfseNacional;

/**
 * Class for RPS construction and validation of data
 *
 */

use DOMException;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;


class Dps implements DpsInterface
{
    /**
     * @var stdClass
     */
    public $std;
    /**
     * @var DOMNode
     */
    protected $dps;
    /**
     * @var DOMNode
     */
    protected $evento;
    /**
     * @var string
     */
    protected $jsonschema;
    /**
     * @var Dom
     */
    protected $dom;
    private string $dpsId;
    private string $preId;
    private LeiauteDps $leiaute;
    private ?array $estruturaNt009 = null;

    /**
     * Constructor
     * @param stdClass|null $std
     * @throws DOMException
     */
    public function __construct(?stdClass $std = null, LeiauteDps $leiaute = LeiauteDps::LEGADO)
    {
        $this->leiaute = $leiaute;
        $this->init($std);
        $this->dom = new Dom('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;
    }

    /**
     *
     * @param stdClass|null $dps
     */
    private function init(?stdClass $dps = null)
    {
        if (!empty($dps)) {
            $this->std = $this->leiaute === LeiauteDps::NT009
                ? $this->normalizarNt009($dps)
                : $this->propertiesToLower($dps);
            if (empty($this->std->version)) {
                $this->std->version = '1.01';
            }
            //$ver = str_replace('.', '_', $this->std->version);
            //$this->jsonschema = realpath("../storage/jsonSchemes/v$ver/rps.schema");
            //$this->validInputData();
        }
    }

    public function render(?stdClass $std = null)
    {
        if ($this->dom->hasChildNodes()) {
            $this->dom = new Dom('1.0', 'UTF-8');
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = false;
        }

        $this->init($std);
        if ($this->leiaute === LeiauteDps::NT009) {
            $this->validarNt009();
        }
        $this->dps = $this->dom->createElement('DPS');
        $this->dps->setAttribute('versao', $this->std->version);
        $this->dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');

        $infdps_inner = $this->dom->createElement('infDPS');
        $infdps_inner->setAttribute('Id', $this->generateId());

        $this->dom->addChild(
            $infdps_inner,
            'tpAmb',
            $this->std->infdps->tpamb,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'dhEmi',
            $this->std->infdps->dhemi,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'verAplic',
            $this->std->infdps->veraplic,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'serie',
            $this->std->infdps->serie,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'nDPS',
            $this->std->infdps->ndps,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'dCompet',
            $this->std->infdps->dcompet,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'tpEmit',
            $this->std->infdps->tpemit,
            true
        );
        if (isset($this->std->infdps->cmotivoemisti)) {
            $this->dom->addChild(
                $infdps_inner,
                'cMotivoEmisTI',
                $this->std->infdps->cmotivoemisti
            );
        }
        if ($this->leiaute === LeiauteDps::NT009) {
            $this->adicionarCampos($infdps_inner, $this->std->infdps, ['finNFSe']);
        }
        if (isset($this->std->infdps->chnfserej)) {
            $this->dom->addChild(
                $infdps_inner,
                'chNFSeRej',
                $this->std->infdps->chnfserej
            );
        }
        $this->dom->addChild(
            $infdps_inner,
            'cLocEmi',
            $this->std->infdps->clocemi,
            true
        );

        if (isset($this->std->infdps->subst)) {
            $subst_inner = $this->dom->createElement('subst');
            $infdps_inner->appendChild($subst_inner);
            $this->dom->addChild(
                $subst_inner,
                'chSubstda',
                $this->std->infdps->subst->chsubstda,
                true
            );
            $this->dom->addChild(
                $subst_inner,
                'cMotivo',
                $this->std->infdps->subst->cmotivo,
                true
            );
            if (isset($this->std->infdps->subst->xmotivo)) {
                $this->dom->addChild(
                    $subst_inner,
                    'xMotivo',
                    $this->std->infdps->subst->xmotivo,
                    true
                );
            }
        }

        if (isset($this->std->infdps->prest)) {
            $prest_inner = $this->dom->createElement('prest');
            $infdps_inner->appendChild($prest_inner);
            if (isset($this->std->infdps->prest->cnpj)) {
                $this->dom->addChild(
                    $prest_inner,
                    'CNPJ',
                    $this->std->infdps->prest->cnpj,
                    true
                );
            }
            if (isset($this->std->infdps->prest->cpf)) {
                $this->dom->addChild(
                    $prest_inner,
                    'CPF',
                    $this->std->infdps->prest->cpf,
                    true
                );
            }
            if (isset($this->std->infdps->prest->nif)) {
                $this->dom->addChild(
                    $prest_inner,
                    'NIF',
                    $this->std->infdps->prest->nif,
                    true
                );
            }
            if (isset($this->std->infdps->prest->cnaonif)) {
                $this->dom->addChild(
                    $prest_inner,
                    'cNaoNIF',
                    $this->std->infdps->prest->cnaonif,
                    true
                );
            }
            if (isset($this->std->infdps->prest->caepf)) {
                $this->dom->addChild(
                    $prest_inner,
                    'CAEPF',
                    $this->std->infdps->prest->caepf,
                    true
                );
            }
            if (isset($this->std->infdps->prest->im)) {
                $this->dom->addChild(
                    $prest_inner,
                    'IM',
                    $this->std->infdps->prest->im,
                    true
                );
            }
            if (isset($this->std->infdps->prest->xnome)) {
                $this->dom->addChild(
                    $prest_inner,
                    'xNome',
                    $this->std->infdps->prest->xnome,
                    true
                );
            }
            if (isset($this->std->infdps->prest->end)) {
                $end_inner = $this->dom->createElement('end');
                $prest_inner->appendChild($end_inner);
                if (isset($this->std->infdps->prest->end->endnac)) {
                    $endnac_inner = $this->dom->createElement('endNac');
                    $end_inner->appendChild($endnac_inner);
                    $this->dom->addChild(
                        $endnac_inner,
                        'cMun',
                        $this->std->infdps->prest->end->endnac->cmun,
                        true
                    );
                    $this->dom->addChild(
                        $endnac_inner,
                        'CEP',
                        $this->std->infdps->prest->end->endnac->cep,
                        true
                    );
                } elseif (isset($this->std->infdps->prest->end->endext)) {
                    $endext_inner = $this->dom->createElement('endExt');
                    $end_inner->appendChild($endext_inner);
                    $this->dom->addChild(
                        $endext_inner,
                        'cPais',
                        $this->std->infdps->prest->end->endext->cpais,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'cEndPost',
                        $this->std->infdps->prest->end->endext->cendpost,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xCidade',
                        $this->std->infdps->prest->end->endext->xcidade,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xEstProvReg',
                        $this->std->infdps->prest->end->endext->xestprovreg,
                        true
                    );
                }

                //                dd($this->std->infdps->prest->end);
                $this->dom->addChild(
                    $end_inner,
                    'xLgr',
                    $this->std->infdps->prest->end->xlgr,
                    true
                );
                $this->dom->addChild(
                    $end_inner,
                    'nro',
                    $this->std->infdps->prest->end->nro,
                    true
                );
                if (isset($this->std->infdps->prest->end->xcpl)) {
                    $this->dom->addChild(
                        $end_inner,
                        'xCpl',
                        $this->std->infdps->prest->end->xcpl
                    );
                }
                $this->dom->addChild(
                    $end_inner,
                    'xBairro',
                    $this->std->infdps->prest->end->xbairro,
                    true
                );
            }
            if (isset($this->std->infdps->prest->fone)) {
                $this->dom->addChild(
                    $prest_inner,
                    'fone',
                    $this->std->infdps->prest->fone
                );
            }
            if (isset($this->std->infdps->prest->email)) {
                $this->dom->addChild(
                    $prest_inner,
                    'email',
                    $this->std->infdps->prest->email
                );
            }

            $regtrib_inner = $this->dom->createElement('regTrib');
            $prest_inner->appendChild($regtrib_inner);
            $this->dom->addChild(
                $regtrib_inner,
                'opSimpNac',
                $this->std->infdps->prest->regtrib->opsimpnac,
                true
            );
            if (isset($this->std->infdps->prest->regtrib->regaptribsn)) {
                $this->dom->addChild(
                    $regtrib_inner,
                    'regApTribSN',
                    $this->std->infdps->prest->regtrib->regaptribsn
                );
            }
            if ($this->leiaute === LeiauteDps::LEGADO || isset($this->std->infdps->prest->regtrib->regesptrib)) {
                $this->dom->addChild(
                    $regtrib_inner,
                    'regEspTrib',
                    $this->std->infdps->prest->regtrib->regesptrib,
                    true
                );
            }

        }
        if (isset($this->std->infdps->toma)) {
            $toma_inner = $this->dom->createElement('toma');
            $infdps_inner->appendChild($toma_inner);
            if (isset($this->std->infdps->toma->cnpj)) {
                $this->dom->addChild(
                    $toma_inner,
                    'CNPJ',
                    $this->std->infdps->toma->cnpj,
                    true
                );
            }
            if (isset($this->std->infdps->toma->cpf)) {
                $this->dom->addChild(
                    $toma_inner,
                    'CPF',
                    $this->std->infdps->toma->cpf,
                    true
                );
            }
            if (isset($this->std->infdps->toma->nif)) {
                $this->dom->addChild(
                    $toma_inner,
                    'NIF',
                    $this->std->infdps->toma->nif,
                    true
                );
            }
            if (isset($this->std->infdps->toma->cnaonif)) {
                $this->dom->addChild(
                    $toma_inner,
                    'cNaoNIF',
                    $this->std->infdps->toma->cnaonif,
                    true
                );
            }
            if (isset($this->std->infdps->toma->caepf)) {
                $this->dom->addChild(
                    $toma_inner,
                    'CAEPF',
                    $this->std->infdps->toma->caepf,
                    true
                );
            }
            if (isset($this->std->infdps->toma->im)) {
                $this->dom->addChild(
                    $toma_inner,
                    'IM',
                    $this->std->infdps->toma->im,
                    true
                );
            }
            $this->dom->addChild(
                $toma_inner,
                'xNome',
                $this->std->infdps->toma->xnome,
                true
            );
            if (isset($this->std->infdps->toma->end)) {
                $end_inner = $this->dom->createElement('end');
                $toma_inner->appendChild($end_inner);
                if (isset($this->std->infdps->toma->end->endnac)) {
                    $endnac_inner = $this->dom->createElement('endNac');
                    $end_inner->appendChild($endnac_inner);
                    $this->dom->addChild(
                        $endnac_inner,
                        'cMun',
                        $this->std->infdps->toma->end->endnac->cmun,
                        true
                    );
                    $this->dom->addChild(
                        $endnac_inner,
                        'CEP',
                        $this->std->infdps->toma->end->endnac->cep,
                        true
                    );
                } elseif (isset($this->std->infdps->toma->end->endext)) {
                    $endext_inner = $this->dom->createElement('endExt');
                    $end_inner->appendChild($endext_inner);
                    $this->dom->addChild(
                        $endext_inner,
                        'cPais',
                        $this->std->infdps->toma->end->endext->cpais,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'cEndPost',
                        $this->std->infdps->toma->end->endext->cendpost,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xCidade',
                        $this->std->infdps->toma->end->endext->xcidade,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xEstProvReg',
                        $this->std->infdps->toma->end->endext->xestprovreg,
                        true
                    );
                }
                $this->dom->addChild(
                    $end_inner,
                    'xLgr',
                    $this->std->infdps->toma->end->xlgr,
                    true
                );
                $this->dom->addChild(
                    $end_inner,
                    'nro',
                    $this->std->infdps->toma->end->nro,
                    true
                );
                if (isset($this->std->infdps->toma->end->xcpl)) {
                    $this->dom->addChild(
                        $end_inner,
                        'xCpl',
                        $this->std->infdps->toma->end->xcpl,
                        false
                    );
                }
                $this->dom->addChild(
                    $end_inner,
                    'xBairro',
                    $this->std->infdps->toma->end->xbairro,
                    true
                );
            }
            if (isset($this->std->infdps->toma->fone)) {
                $this->dom->addChild(
                    $toma_inner,
                    'fone',
                    $this->std->infdps->toma->fone
                );
            }
            if (isset($this->std->infdps->toma->email)) {
                $this->dom->addChild(
                    $toma_inner,
                    'email',
                    $this->std->infdps->toma->email
                );
            }
        }

        //TODO Fazer grupo interm
        //if (isset($this->std->interm)) {
        //    $interm_inner = $this->dom->createElement('interm');
        //    $infdps_inner->appendChild($interm_inner);
        //}

        if ($this->leiaute === LeiauteDps::LEGADO || isset($this->std->infdps->serv)) {
            $this->montarServico($infdps_inner);
        }

        if ($this->leiaute === LeiauteDps::LEGADO || isset($this->std->infdps->valores)) {
            $this->montarValores($infdps_inner);
        }

        if ($this->leiaute === LeiauteDps::NT009) {
            if (isset($this->std->infdps->ibscbs)) {
                $this->montarGrupoNt009($infdps_inner, $this->std->infdps->ibscbs, 'IBSCBS');
            }
        } else {
            $this->montarIbsCbsLegado($infdps_inner);
        }

        $dps = $this->dom->createElement('DPS');
        $dps->setAttribute('versao', $this->std->version);
        $dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $this->dps->appendChild($infdps_inner);
        $this->dom->appendChild($this->dps);
        /*        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->dom->saveXML());*/
        return $this->dom->saveXML();
    }

    private function montarServico(DOMElement $infdps_inner): void
    {
        $serv_inner = $this->dom->createElement('serv');
        $infdps_inner->appendChild($serv_inner);

        $locprest_inner = $this->dom->createElement('locPrest');
        $serv_inner->appendChild($locprest_inner);
        $this->dom->addChild(
            $locprest_inner,
            'cLocPrestacao',
            $this->std->infdps->serv->locprest->clocprestacao,
            true
        );
        if (isset($this->std->infdps->serv->locprest->cpaisprestacao)) {
            $this->dom->addChild(
                $locprest_inner,
                'cPaisPrestacao',
                $this->std->infdps->serv->locprest->cpaisprestacao,
                true
            );
        }

        $cserv_inner = $this->dom->createElement('cServ');
        $serv_inner->appendChild($cserv_inner);

        $this->dom->addChild(
            $cserv_inner,
            'cTribNac',
            $this->std->infdps->serv->cserv->ctribnac,
            true
        );
        if (isset($this->std->infdps->serv->cserv->ctribmun)) {
            $this->dom->addChild(
                $cserv_inner,
                'cTribMun',
                $this->std->infdps->serv->cserv->ctribmun,
                true
            );
        }
        if ($this->leiaute === LeiauteDps::LEGADO) {
            $this->dom->addChild(
                $cserv_inner,
                'xDescServ',
                $this->std->infdps->serv->cserv->xdescserv,
                true
            );
        }
        if (isset($this->std->infdps->serv->cserv->cnbs)) {
            $this->dom->addChild(
                $cserv_inner,
                'cNBS',
                $this->std->infdps->serv->cserv->cnbs,
                true
            );
        }
        if (isset($this->std->infdps->serv->cserv->cintcontrib)) {
            $this->dom->addChild(
                $cserv_inner,
                'cIntContrib',
                $this->std->infdps->serv->cserv->cintcontrib,
                true
            );
        }

        if ($this->leiaute === LeiauteDps::NT009) {
            $this->dom->addChild(
                $cserv_inner,
                'xDescServ',
                $this->std->infdps->serv->cserv->xdescserv,
                true
            );
        }

        //grupo comExt
        if (isset($this->std->infdps->serv->comext)) {
            $comext_inner = $this->dom->createElement('comExt');
            $serv_inner->appendChild($comext_inner);

            if ($this->leiaute === LeiauteDps::NT009) {
                $this->montarComercioExteriorNt009($comext_inner, $this->std->infdps->serv->comext);
            } else {
                $this->dom->addChild(
                    $comext_inner,
                    'mdPrestacao',
                    $this->std->infdps->serv->comext->mdprestacao
                );

                $this->dom->addChild(
                    $comext_inner,
                    'vincPrest',
                    $this->std->infdps->serv->comext->vincprest
                );

                $this->dom->addChild(
                    $comext_inner,
                    'tpMoeda',
                    $this->std->infdps->serv->comext->tpmoeda
                );

                $this->dom->addChild(
                    $comext_inner,
                    'vServMoeda',
                    $this->std->infdps->serv->comext->vservmoeda
                );

                $this->dom->addChild(
                    $comext_inner,
                    'mecAFComexP',
                    $this->std->infdps->serv->comext->mecafcomexp
                );

                $this->dom->addChild(
                    $comext_inner,
                    'mecAFComexT',
                    $this->std->infdps->serv->comext->mecafcomext
                );

                $this->dom->addChild(
                    $comext_inner,
                    'movTempBens',
                    $this->std->infdps->serv->comext->movtempbens
                );

                if (isset($this->std->infdps->serv->comext->ndi)) {
                    $this->dom->addChild(
                        $comext_inner,
                        'nDI',
                        $this->std->infdps->serv->comext->ndi
                    );
                }

                if (isset($this->std->infdps->serv->comext->nre)) {
                    $this->dom->addChild(
                        $comext_inner,
                        'nRE',
                        $this->std->infdps->serv->comext->nre
                    );
                }


                $this->dom->addChild(
                    $comext_inner,
                    'mdic',
                    $this->std->infdps->serv->comext->mdic
                );
            }
        }

        // Monta grupo obra
        if (isset($this->std->infdps->serv->obra)) {
            $obra_inner = $this->dom->createElement('obra');
            $serv_inner->appendChild($obra_inner);

            // inscImobFisc (opcional)
            if (isset($this->std->infdps->serv->obra->inscimobfisc)) {
                $this->dom->addChild(
                    $obra_inner,
                    'inscImobFisc',
                    $this->std->infdps->serv->obra->inscimobfisc,
                    true
                );
            }

            // cObra (normalmente obrigatório quando obra existir)
            if (isset($this->std->infdps->serv->obra->cobra)) {
                $this->dom->addChild(
                    $obra_inner,
                    'cObra',
                    $this->std->infdps->serv->obra->cobra,
                    true
                );
            }

            // cCIB (validação do layout pode rejeitar se inválido)
            if (isset($this->std->infdps->serv->obra->ccib)) {
                $this->dom->addChild(
                    $obra_inner,
                    'cCIB',
                    $this->std->infdps->serv->obra->ccib,
                    true
                );
            }

            // end (endereço nacional)
            if (isset($this->std->infdps->serv->obra->end)) {
                $end_obra_inner = $this->dom->createElement('end');
                $obra_inner->appendChild($end_obra_inner);

                if (isset($this->std->infdps->serv->obra->end->cep)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'CEP',
                        $this->std->infdps->serv->obra->end->cep,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->obra->end->xlgr)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'xLgr',
                        $this->std->infdps->serv->obra->end->xlgr,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->obra->end->nro)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'nro',
                        $this->std->infdps->serv->obra->end->nro,
                        true
                    );
                }

                // xCpl (opcional)
                if (isset($this->std->infdps->serv->obra->end->xcpl)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'xCpl',
                        $this->std->infdps->serv->obra->end->xcpl,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->obra->end->xbairro)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'xBairro',
                        $this->std->infdps->serv->obra->end->xbairro,
                        true
                    );
                }
            }
        }

        //TODO Fazer grupo lsadppu
        if (isset($this->std->infdps->serv->atvevento)) {
            $atvEvento_inner = $this->dom->createElement('atvEvento');
            $serv_inner->appendChild($atvEvento_inner);

            // Campo: xNome
            if (isset($this->std->infdps->serv->atvevento->xnome)) {
                $this->dom->addChild(
                    $atvEvento_inner,
                    'xNome',
                    $this->std->infdps->serv->atvevento->xnome,
                    true
                );
            }

            // Campo: dtIni
            if (isset($this->std->infdps->serv->atvevento->dtini)) {
                $this->dom->addChild(
                    $atvEvento_inner,
                    'dtIni',
                    $this->std->infdps->serv->atvevento->dtini,
                    true
                );
            }

            // Campo: dtFim
            if (isset($this->std->infdps->serv->atvevento->dtfim)) {
                $this->dom->addChild(
                    $atvEvento_inner,
                    'dtFim',
                    $this->std->infdps->serv->atvevento->dtfim,
                    true
                );
            }

            if ($this->leiaute === LeiauteDps::NT009) {
                $this->adicionarCampos($atvEvento_inner, $this->std->infdps->serv->atvevento, ['idAtvEvt']);
            }

            // Endereço do evento
            if (isset($this->std->infdps->serv->atvevento->end)) {
                $end_evento_inner = $this->dom->createElement('end');
                $atvEvento_inner->appendChild($end_evento_inner);

                if (isset($this->std->infdps->serv->atvevento->end->cep)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'CEP',
                        $this->std->infdps->serv->atvevento->end->cep,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->atvevento->end->xlgr)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'xLgr',
                        $this->std->infdps->serv->atvevento->end->xlgr,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->atvevento->end->nro)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'nro',
                        $this->std->infdps->serv->atvevento->end->nro,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->atvevento->end->xbairro)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'xBairro',
                        $this->std->infdps->serv->atvevento->end->xbairro,
                        true
                    );
                }
            }
        }
        //TODO Fazer grupo explRod


        // Grupo de informações complementares disponível para todos os serviços prestados
        if (isset($this->std->infdps->serv->infocompl->iddoctec)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'idDocTec',
                $this->std->infdps->serv->infocompl->iddoctec,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->docref)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'docRef',
                $this->std->infdps->serv->infocompl->docref,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->xped)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'xPed',
                $this->std->infdps->serv->infocompl->xped,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->gitemped->xitemped)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $gItemPed_inner = $this->dom->createElement('gItemPed');
            $infocompl_inner->appendChild($gItemPed_inner);
            $this->dom->addChild(
                $gItemPed_inner,
                'xItemPed',
                $this->std->infdps->serv->infocompl->gitemped->xitemped,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->xinfcomp)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'xInfComp',
                $this->std->infdps->serv->infocompl->xinfcomp,
                true
            );
        }

    }

    private function montarValores(DOMElement $infdps_inner): void
    {
        $valores_inner = $this->dom->createElement('valores');
        $infdps_inner->appendChild($valores_inner);
        $vservprest_inner = $this->dom->createElement('vServPrest');
        $valores_inner->appendChild($vservprest_inner);

        if (isset($this->std->infdps->valores->vservprest->vreceb)) {
            $this->dom->addChild(
                $vservprest_inner,
                'vReceb',
                $this->std->infdps->valores->vservprest->vreceb
            );
        }
        $this->dom->addChild(
            $vservprest_inner,
            'vServ',
            $this->std->infdps->valores->vservprest->vserv,
            true
        );

        //TODO Fazer grupo vDescCondIncond
		// Grupo vDescCondIncond (dentro de <valores>)
		$vDescIncond = $this->std->infdps->valores->vdesccondincond->vdescincond ?? null;
		$vDescCond   = $this->std->infdps->valores->vdesccondincond->vdesccond   ?? null;

		// regra: considera vazio se null, string vazia, ou "0.00" (ajuste se quiser manter 0.00)
		$temDescIncond = ($vDescIncond !== null && $vDescIncond !== '' && $vDescIncond !== '0.00');
		$temDescCond   = ($vDescCond   !== null && $vDescCond   !== '' && $vDescCond   !== '0.00');

		if ($temDescIncond || $temDescCond) {
			$descontos_inner = $this->dom->createElement('vDescCondIncond');
			$valores_inner->appendChild($descontos_inner);

			$this->dom->addChild($descontos_inner, 'vDescIncond', $vDescIncond, false);
			$this->dom->addChild($descontos_inner, 'vDescCond',   $vDescCond,   false);
		}


        if ($this->leiaute === LeiauteDps::NT009) {
            $this->montarAjusteBaseNt009($valores_inner);
        }

        //TODO Fazer grupo vDedRed

        if ($this->leiaute === LeiauteDps::LEGADO || isset($this->std->infdps->valores->trib)) {
            $trib_inner = $this->dom->createElement('trib');
            $valores_inner->appendChild($trib_inner);

            $tribmun_inner = $this->dom->createElement('tribMun');
            $trib_inner->appendChild($tribmun_inner);

            $this->dom->addChild(
                $tribmun_inner,
                'tribISSQN',
                $this->std->infdps->valores->trib->tribmun->tribissqn,
                true
            );

            if (isset($this->std->infdps->valores->trib->tribmun->tribissqn) && $this->std->infdps->valores->trib->tribmun->tribissqn ==
                    2 && isset($this->std->infdps->valores->trib->tribmun->tpimunidade)) {
                $this->dom->addChild(
                    $tribmun_inner,
                    'tpImunidade',
                    $this->std->infdps->valores->trib->tribmun->tpimunidade,
                    true
                );
            }

            if(isset($this->std->infdps->valores->trib->tribmun->tribissqn) && $this->std->infdps->valores->trib->tribmun->tribissqn == 3){
                $this->dom->addChild(
                    $tribmun_inner,
                    'cPaisResult',
                    $this->std->infdps->valores->trib->tribmun->cpaisresult,
                    true
                );
            }

            if (isset($this->std->infdps->valores->trib->tribmun->tpretissqn)) {
                $this->dom->addChild(
                    $tribmun_inner,
                    'tpRetISSQN',
                    $this->std->infdps->valores->trib->tribmun->tpretissqn,
                    true
                );
            }

            if (isset($this->std->infdps->valores->trib->tribmun->paliq)) {
                $this->dom->addChild(
                    $tribmun_inner,
                    'pAliq',
                    $this->std->infdps->valores->trib->tribmun->paliq,
                    true
                );
            }


            if (isset($this->std->infdps->valores->trib->tribfed)) {
                $tribfed_inner = $this->dom->createElement('tribFed');
                $trib_inner->appendChild($tribfed_inner);
                if (isset($this->std->infdps->valores->trib->tribfed->piscofins)) {
                    $piscofins_inner = $this->dom->createElement('piscofins');
                    $tribfed_inner->appendChild($piscofins_inner);

                    $this->dom->addChild(
                        $piscofins_inner,
                        'CST',
                        $this->std->infdps->valores->trib->tribfed->piscofins->cst,
                        true
                    );
                    if (isset($this->std->infdps->valores->trib->tribfed->piscofins->vbcpiscofins)) {
                        $this->dom->addChild(
                            $piscofins_inner,
                            'vBCPisCofins',
                            $this->std->infdps->valores->trib->tribfed->piscofins->vbcpiscofins
                        );
                    }
                    if (isset($this->std->infdps->valores->trib->tribfed->piscofins->paliqpis)) {
                        $this->dom->addChild(
                            $piscofins_inner,
                            'pAliqPis',
                            $this->std->infdps->valores->trib->tribfed->piscofins->paliqpis
                        );
                    }
                    if (isset($this->std->infdps->valores->trib->tribfed->piscofins->paliqcofins)) {
                        $this->dom->addChild(
                            $piscofins_inner,
                            'pAliqCofins',
                            $this->std->infdps->valores->trib->tribfed->piscofins->paliqcofins
                        );
                    }
                    if (isset($this->std->infdps->valores->trib->tribfed->piscofins->vpis)) {
                        $this->dom->addChild(
                            $piscofins_inner,
                            'vPis',
                            $this->std->infdps->valores->trib->tribfed->piscofins->vpis
                        );
                    }
                    if (isset($this->std->infdps->valores->trib->tribfed->piscofins->vcofins)) {
                        $this->dom->addChild(
                            $piscofins_inner,
                            'vCofins',
                            $this->std->infdps->valores->trib->tribfed->piscofins->vcofins
                        );
                    }
                    if (isset($this->std->infdps->valores->trib->tribfed->piscofins->tpretpiscofins)) {
                        $this->dom->addChild(
                            $piscofins_inner,
                            'tpRetPisCofins',
                            $this->std->infdps->valores->trib->tribfed->piscofins->tpretpiscofins
                        );
                    }
                }
                if (isset($this->std->infdps->valores->trib->tribfed->vretcp)) {
                    $this->dom->addChild(
                        $tribfed_inner,
                        'vRetCP',
                        $this->std->infdps->valores->trib->tribfed->vretcp
                    );
                }
                if (isset($this->std->infdps->valores->trib->tribfed->vretirrf)) {
                    $this->dom->addChild(
                        $tribfed_inner,
                        'vRetIRRF',
                        $this->std->infdps->valores->trib->tribfed->vretirrf
                    );
                }
                if (isset($this->std->infdps->valores->trib->tribfed->vretcsll)) {
                    $this->dom->addChild(
                        $tribfed_inner,
                        'vRetCSLL',
                        $this->std->infdps->valores->trib->tribfed->vretcsll
                    );
                }
            }

            $tottrib_inner = $this->dom->createElement('totTrib');
            $trib_inner->appendChild($tottrib_inner);

            if (isset($this->std->infdps->valores->trib->tottrib->vtottrib)) {
                $vtottrib_inner = $this->dom->createElement('vTotTrib');
                $tottrib_inner->appendChild($vtottrib_inner);
                if (isset($this->std->infdps->valores->trib->tottrib->vtottrib->vtottribfed)) {
                    $this->dom->addChild(
                        $vtottrib_inner,
                        'vTotTribFed',
                        $this->std->infdps->valores->trib->tottrib->vtottrib->vtottribfed
                    );
                }
                if (isset($this->std->infdps->valores->trib->tottrib->vtottrib->vtottribest)) {
                    $this->dom->addChild(
                        $vtottrib_inner,
                        'vTotTribEst',
                        $this->std->infdps->valores->trib->tottrib->vtottrib->vtottribest
                    );
                }
                if (isset($this->std->infdps->valores->trib->tottrib->vtottrib->vtottribmun)) {
                    $this->dom->addChild(
                        $vtottrib_inner,
                        'vTotTribMun',
                        $this->std->infdps->valores->trib->tottrib->vtottrib->vtottribmun
                    );
                }
            }
            if (isset($this->std->infdps->valores->trib->tottrib->ptottrib)) {
                $ptottrib_inner = $this->dom->createElement('pTotTrib');
                $tottrib_inner->appendChild($ptottrib_inner);

                if (isset($this->std->infdps->valores->trib->tottrib->ptottrib->ptottribfed)) {
                    $this->dom->addChild(
                        $ptottrib_inner,
                        'pTotTribFed',
                        $this->std->infdps->valores->trib->tottrib->ptottrib->ptottribfed
                    );
                }
                if (isset($this->std->infdps->valores->trib->tottrib->ptottrib->ptottribest)) {
                    $this->dom->addChild(
                        $ptottrib_inner,
                        'pTotTribEst',
                        $this->std->infdps->valores->trib->tottrib->ptottrib->ptottribest
                    );
                }
                if (isset($this->std->infdps->valores->trib->tottrib->ptottrib->ptottribmun)) {
                    $this->dom->addChild(
                        $ptottrib_inner,
                        'pTotTribMun',
                        $this->std->infdps->valores->trib->tottrib->ptottrib->ptottribmun
                    );
                }
            }

            if (isset($this->std->infdps->valores->trib->tottrib->indtottrib)) {
                $this->dom->addChild(
                    $tottrib_inner,
                    'indTotTrib',
                    $this->std->infdps->valores->trib->tottrib->indtottrib
                );
            }
            if (isset($this->std->infdps->valores->trib->tottrib->ptottribsn)) {
                $this->dom->addChild(
                    $tottrib_inner,
                    'pTotTribSN',
                    $this->std->infdps->valores->trib->tottrib->ptottribsn
                );
            }
        }
    }

    private function montarIbsCbsLegado(DOMElement $infdps_inner): void
    {
        //Grupos de IBS/CBS
        if (isset($this->std->infdps->ibscbs)) {
            $ibscbs_inner = $this->dom->createElement('IBSCBS');
            $infdps_inner->appendChild($ibscbs_inner);

            $this->dom->addChild(
                $ibscbs_inner,
                'finNFSe',
                $this->std->infdps->ibscbs->finnfse,
                true
            );

            $this->dom->addChild(
                $ibscbs_inner,
                'indFinal',
                $this->std->infdps->ibscbs->indfinal,
                true
            );
            $this->dom->addChild(
                $ibscbs_inner,
                'cIndOp',
                $this->std->infdps->ibscbs->cindop,
                true
            );
            if (isset($this->std->infdps->ibscbs->tpoper)) {
                $this->dom->addChild(
                    $ibscbs_inner,
                    'tpOper',
                    $this->std->infdps->ibscbs->tpoper
                );
            }
            // Grupos novos são tratados apenas no perfil NT009.
            if (isset($this->std->infdps->ibscbs->tpentegov)) {
                $this->dom->addChild(
                    $ibscbs_inner,
                    'tpEnteGov',
                    $this->std->infdps->ibscbs->tpentegov
                );
            }
            $this->montarDestinatario($ibscbs_inner, $this->std->infdps->ibscbs);
            // TODO: Implementar grupo imovel em IBSCBS.

            if (isset($this->std->infdps->ibscbs->valores)) {
                $ibscbs_valores_inner = $this->dom->createElement('valores');
                $ibscbs_inner->appendChild($ibscbs_valores_inner);

                if (isset($this->std->infdps->ibscbs->valores->greerepres)) {
                    // TODO: Implementar grupo gReeRepRes em IBSCBS.
                }

                $ibscbs_valores_trib_inner = $this->dom->createElement('trib');
                $ibscbs_valores_inner->appendChild($ibscbs_valores_trib_inner);

                if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs)) {
                    $ibscbs_valores_trib_gibscbs = $this->std->infdps->ibscbs->valores->trib->gibscbs;
                    $ibscbs_valores_trib_gibscbs_inner = $this->dom->createElement('gIBSCBS');
                    $ibscbs_valores_trib_inner->appendChild($ibscbs_valores_trib_gibscbs_inner);

                    if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->cst)) {
                        $this->dom->addChild(
                            $ibscbs_valores_trib_gibscbs_inner,
                            'CST',
                            $ibscbs_valores_trib_gibscbs->cst,
                            true
                        );
                    }
                    if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->cclasstrib)) {
                        $this->dom->addChild(
                            $ibscbs_valores_trib_gibscbs_inner,
                            'cClassTrib',
                            $ibscbs_valores_trib_gibscbs->cclasstrib,
                            true
                        );
                    }

                    if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->ccredpres)) {
                        $this->dom->addChild(
                            $ibscbs_valores_trib_gibscbs_inner,
                            'cCredPres',
                            $ibscbs_valores_trib_gibscbs->ccredpres
                        );
                    }

                    if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->gtribregular)) {
                        $ibscbs_valores_trib_gtribregular_inner = $this->dom->createElement('gTribRegular');
                        $ibscbs_valores_trib_gibscbs_inner->appendChild($ibscbs_valores_trib_gtribregular_inner);
                        if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->gtribregular->cstreg)) {
                            $this->dom->addChild(
                                $ibscbs_valores_trib_gtribregular_inner,
                                'CSTReg',
                                $ibscbs_valores_trib_gibscbs->gtribregular->cstreg,
                                true
                            );
                        }
                        if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->gtribregular->cclasstribreg)) {
                            $this->dom->addChild(
                                $ibscbs_valores_trib_gtribregular_inner,
                                'cClassTribReg',
                                $ibscbs_valores_trib_gibscbs->gtribregular->cclasstribreg,
                                true
                            );
                        }
                    }

                    if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->gdif)) {
                        $ibscbs_valores_trib_gdif_inner = $this->dom->createElement('gDif');
                        $ibscbs_valores_trib_gibscbs_inner->appendChild($ibscbs_valores_trib_gdif_inner);
                        if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->gdif->pdifuf)) {
                            $this->dom->addChild(
                                $ibscbs_valores_trib_gdif_inner,
                                'pDifUF',
                                $ibscbs_valores_trib_gibscbs->gdif->pdifuf,
                                true
                            );
                        }
                        if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->gdif->pdifmun)) {
                            $this->dom->addChild(
                                $ibscbs_valores_trib_gdif_inner,
                                'pDifMun',
                                $ibscbs_valores_trib_gibscbs->gdif->pdifmun,
                                true
                            );
                        }
                        if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->gdif->pdifcbs)) {
                            $this->dom->addChild(
                                $ibscbs_valores_trib_gdif_inner,
                                'pDifCBS',
                                $ibscbs_valores_trib_gibscbs->gdif->pdifcbs,
                                true
                            );
                        }
                    }
                }
            }
        }

    }

    /** Mantém a posição e o contrato de entrada específicos de cada perfil. */
    private function montarDestinatario(DOMElement $pai, stdClass $dados): void
    {
        if ($this->leiaute === LeiauteDps::NT009) {
            $this->adicionarCampos($pai, $dados, ['indDest']);
        } else {
            $this->dom->addChild(
                $pai,
                'indDest',
                $dados->inddest,
                true
            );
        }
        if (isset($dados->dest)) {
            $ibscbs_dest_inner = $this->dom->createElement('dest');
            $pai->appendChild($ibscbs_dest_inner);
            if (isset($dados->dest->cnpj)) {
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'CNPJ',
                    $dados->dest->cnpj,
                    true
                );
            }
            if (isset($dados->dest->cpf)) {
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'CPF',
                    $dados->dest->cpf,
                    true
                );
            }
            if (isset($dados->dest->nif)) {
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'NIF',
                    $dados->dest->nif,
                    true
                );
            }
            if (isset($dados->dest->cnaonif)) {
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'cNaoNIF',
                    $dados->dest->cnaonif,
                    true
                );
            }
            $this->dom->addChild(
                $ibscbs_dest_inner,
                'xNome',
                $dados->dest->xnome,
                true
            );
            if ($this->leiaute === LeiauteDps::LEGADO) {
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'fone',
                    $dados->dest->fone
                );
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'email',
                    $dados->dest->email
                );
            }

            if (isset($dados->dest->end)) {
                $ibscbs_dest_end_inner = $this->dom->createElement('end');
                $ibscbs_dest_inner->appendChild($ibscbs_dest_end_inner);

                if (isset($dados->dest->end->endnac)) {
                    $ibscbs_endnac_inner = $this->dom->createElement('endNac');
                    $ibscbs_dest_end_inner->appendChild($ibscbs_endnac_inner);
                    $this->dom->addChild(
                        $ibscbs_endnac_inner,
                        'cMun',
                        $dados->dest->end->endnac->cmun,
                        true
                    );
                    $this->dom->addChild(
                        $ibscbs_endnac_inner,
                        'CEP',
                        $dados->dest->end->endnac->cep,
                        true
                    );
                } elseif (isset($dados->dest->end->endext)) {
                    $ibscbs_endext_inner = $this->dom->createElement('endExt');
                    $ibscbs_dest_end_inner->appendChild($ibscbs_endext_inner);
                    $this->dom->addChild(
                        $ibscbs_endext_inner,
                        'cPais',
                        $dados->dest->end->endext->cpais,
                        true
                    );
                    $this->dom->addChild(
                        $ibscbs_endext_inner,
                        'cEndPost',
                        $dados->dest->end->endext->cendpost,
                        true
                    );
                    $this->dom->addChild(
                        $ibscbs_endext_inner,
                        'xCidade',
                        $dados->dest->end->endext->xcidade,
                        true
                    );
                    $this->dom->addChild(
                        $ibscbs_endext_inner,
                        'xEstProvReg',
                        $dados->dest->end->endext->xestprovreg,
                        true
                    );
                }
                $this->dom->addChild(
                    $ibscbs_dest_end_inner,
                    'xLgr',
                    $dados->dest->end->xlgr,
                    true
                );
                $this->dom->addChild(
                    $ibscbs_dest_end_inner,
                    'nro',
                    $dados->dest->end->nro,
                    true
                );
                if (isset($dados->dest->end->xcpl)) {
                    $this->dom->addChild(
                        $ibscbs_dest_end_inner,
                        'xCpl',
                        $dados->dest->end->xcpl
                    );
                }
                $this->dom->addChild(
                    $ibscbs_dest_end_inner,
                    'xBairro',
                    $dados->dest->end->xbairro,
                    true
                );
            }
            if (isset($dados->dest->fone)) {
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'fone',
                    $dados->dest->fone
                );
            }
            if (isset($dados->dest->email)) {
                $this->dom->addChild(
                    $ibscbs_dest_inner,
                    'email',
                    $dados->dest->email
                );
            }
        }
    }

    /** Adiciona os campos na sequência do perfil selecionado. */
    private function adicionarCampos(DOMElement $pai, stdClass $dados, array $campos, array $obrigatorios = []): void
    {
        foreach ($campos as $campo) {
            $propriedade = strtolower($campo);
            $obrigatorio = in_array($campo, $obrigatorios, true);
            if ($obrigatorio || isset($dados->{$propriedade})) {
                $this->dom->addChild($pai, $campo, $dados->{$propriedade} ?? null, $obrigatorio);
            }
        }
    }

    private function montarComercioExteriorNt009(DOMElement $pai, stdClass $dados): void
    {
        $this->adicionarCampos($pai, $dados, [
            'mdPrestacao', 'vincPrest', 'tpMoeda', 'vServMoeda',
            'mecAFComexP', 'mecAFComexT', 'movTempBens', 'nDI', 'nRE', 'mdic',
        ]);
    }

    private function montarAjusteBaseNt009(DOMElement $pai): void
    {
        $dados = $this->std->infdps->valores->vajustebc ?? null;
        if ($dados === null) {
            return;
        }
        $this->montarGrupoNt009($pai, $dados, 'valores/vAjusteBC');
    }

    /** Proteções do contrato de montagem; regras fiscais ficam a cargo do autorizador. */
    private function validarNt009(): void
    {
        $dps = $this->std->infdps ?? null;
        if (!$dps instanceof stdClass) {
            throw new InvalidArgumentException('NT009: infDPS deve ser um objeto.');
        }
        $this->validarTiposNt009($dps);
        foreach (['clocemi', 'serie', 'ndps'] as $campo) {
            $this->exigirIdentificadorNt009($dps->{$campo} ?? null, 'infDPS.' . $campo);
        }
        $inscricao = isset($dps->prest->cnpj) ? $dps->prest->cnpj : ($dps->prest->cpf ?? null);
        $this->exigirIdentificadorNt009($inscricao, 'infDPS.prest.CNPJ/CPF');

        $ibs = $dps->ibscbs ?? null;
        if (isset($ibs->finnfse)) {
            throw new InvalidArgumentException('NT009: finNFSe deve ser informado em infDPS, não em IBSCBS.');
        }
        foreach (['tpnfsecredito', 'tpnfsedebito', 'inddest', 'dest'] as $campo) {
            if (isset($dps->{$campo})) {
                throw new InvalidArgumentException('NT009 release 1: campo não suportado em infDPS: ' . $campo);
            }
        }
        foreach ([
            [$dps->prest->regtrib ?? null, 'prest/regTrib'],
            [$dps->serv->cserv ?? null, 'serv/cServ'],
            [$dps->serv->comext ?? null, 'serv/comExt'],
            [$dps->serv->atvevento ?? null, 'serv/atvEvento'],
        ] as [$grupo, $caminho]) {
            if ($grupo !== null) {
                $this->validarGrupoNt009($grupo, $caminho);
            }
        }
        if (isset($ibs->valores->trib->gibscbs->cst) || isset($ibs->valores->trib->gibscbs->cclasstrib)) {
            throw new InvalidArgumentException('NT009: CST e cClassTrib devem estar diretamente em IBSCBS.valores.trib.');
        }
        if (isset($dps->valores->vdedred) || isset($ibs->valores->greerepres)) {
            throw new InvalidArgumentException('NT009: use valores.vAjusteBC, não vDedRed/gReeRepRes.');
        }
        foreach (['condominios', 'imovel', 'bensMoveis', 'gLocBensMoveis'] as $grupo) {
            $propriedade = strtolower($grupo);
            if (isset($ibs->{$propriedade})) {
                throw new InvalidArgumentException('NT009: grupo ' . $grupo . ' ainda não suportado pelo fork.');
            }
        }
        if (isset($dps->valores->vajustebc)) {
            $this->validarGrupoNt009($dps->valores->vajustebc, 'valores/vAjusteBC');
        }
        if ($ibs !== null) {
            $this->validarGrupoNt009($ibs, 'IBSCBS');
        }
    }

    /** Verifica apenas objetos e valores usados pelo montador compartilhado com o legado. */
    private function validarTiposNt009(stdClass $dados, string $caminho = 'infDPS'): void
    {
        $grupos = [
            'prest', 'toma', 'dest', 'subst', 'serv', 'valores', 'end', 'endnac', 'endext',
            'regtrib', 'locprest', 'cserv', 'comext', 'obra', 'atvevento', 'infocompl',
            'gitemped', 'vservprest', 'vdesccondincond', 'trib', 'tribmun', 'tribfed',
            'piscofins', 'tottrib', 'ptottrib', 'vtottrib', 'ibscbs', 'vajustebc',
        ];
        foreach (get_object_vars($dados) as $nome => $valor) {
            if ($valor === null) {
                continue;
            }
            $local = $caminho . '.' . $nome;
            if (in_array($nome, $grupos, true)) {
                if (!$valor instanceof stdClass) {
                    throw new InvalidArgumentException('NT009: ' . $local . ' deve ser um objeto.');
                }
                // Os novos grupos têm seu próprio contrato, incluindo listas.
                if (!in_array($nome, ['ibscbs', 'vajustebc'], true)) {
                    $this->validarTiposNt009($valor, $local);
                }
            } elseif (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
                throw new InvalidArgumentException('NT009: ' . $local . ' deve ser string ou número.');
            }
        }
    }

    private function exigirIdentificadorNt009($valor, string $campo): void
    {
        if ($valor === null || (string)$valor === '') {
            throw new InvalidArgumentException('NT009: dado necessário para compor o identificador da DPS: ' . $campo);
        }
    }

    private function normalizarNt009($dados)
    {
        if ($dados instanceof stdClass) {
            $objeto = new stdClass();
            foreach (get_object_vars($dados) as $nome => $valor) {
                $objeto->{strtolower($nome)} = $this->normalizarNt009($valor);
            }
            return $objeto;
        }
        if (is_array($dados)) {
            return array_map(fn($valor) => $this->normalizarNt009($valor), $dados);
        }
        return $dados;
    }

    /** Metadados de montagem extraídos do XSD PRODREST de 20/10/2026. */
    private function estruturaNt009(): array
    {
        if ($this->estruturaNt009 === null) {
            $this->estruturaNt009 = json_decode(
                file_get_contents(__DIR__ . '/Layouts/nt009.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }
        return $this->estruturaNt009;
    }

    /** Monta apenas campos explicitamente relacionados nos metadados, na ordem oficial. */
    private function montarGrupoNt009(DOMElement $pai, stdClass $dados, string $caminho): void
    {
        $campos = $this->estruturaNt009()['campos'];
        $definicao = $campos[$caminho];
        $grupo = $this->dom->createElement($definicao['nome']);
        $pai->appendChild($grupo);
        foreach ($definicao['filhos'] as $filho) {
            $campo = $campos[$filho];
            $nome = $campo['nome'];
            $propriedade = strtolower($nome);
            if (!isset($dados->{$propriedade})) {
                continue;
            }
            $valor = $dados->{$propriedade};
            foreach ($campo['repetido'] ? $valor : [$valor] as $item) {
                if ($campo['grupo']) {
                    $this->montarGrupoNt009($grupo, $item, $filho);
                } else {
                    $elemento = $this->dom->createElement($nome);
                    $elemento->appendChild($this->dom->createTextNode((string)$item));
                    $grupo->appendChild($elemento);
                }
            }
        }
    }

    private function validarGrupoNt009(stdClass $dados, string $caminho): void
    {
        $campos = $this->estruturaNt009()['campos'];
        $permitidos = [];
        foreach ($campos[$caminho]['filhos'] as $filho) {
            $definicao = $campos[$filho];
            $propriedade = strtolower($definicao['nome']);
            $permitidos[] = $propriedade;
            if (!isset($dados->{$propriedade})) {
                continue;
            }
            $valor = $dados->{$propriedade};
            if ($definicao['repetido'] && (!is_array($valor) || !array_is_list($valor))) {
                throw new InvalidArgumentException('NT009: ' . $filho . ' deve ser uma lista.');
            }
            foreach ($definicao['repetido'] ? $valor : [$valor] as $item) {
                if ($definicao['grupo']) {
                    if (!$item instanceof stdClass) {
                        throw new InvalidArgumentException('NT009: ' . $filho . ' deve ser um objeto.');
                    }
                    $this->validarGrupoNt009($item, $filho);
                } elseif (!is_string($item) && !is_int($item) && !is_float($item)) {
                    throw new InvalidArgumentException('NT009: ' . $filho . ' deve ser string ou número.');
                }
            }
        }
        foreach (array_keys(get_object_vars($dados)) as $nome) {
            if (!in_array($nome, $permitidos, true)) {
                throw new InvalidArgumentException('NT009: campo não suportado em ' . $caminho . ': ' . $nome);
            }
        }
    }

    public function renderEvento(?stdClass $std = null)
    {
        if ($this->dom->hasChildNodes()) {
            $this->dom = new Dom('1.0', 'UTF-8');
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = false;
        }

        $this->init($std);
        $this->evento = $this->dom->createElement('pedRegEvento');
        $this->evento->setAttribute('versao', $this->std->version);
        $this->evento->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');

        $infpedreg_inner = $this->dom->createElement('infPedReg');
        $infpedreg_inner->setAttribute('Id', $this->generatePre());

        $this->dom->addChild(
            $infpedreg_inner,
            'tpAmb',
            $this->std->infpedreg->tpamb,
            true
        );
        $this->dom->addChild(
            $infpedreg_inner,
            'verAplic',
            $this->std->infpedreg->veraplic,
            true
        );
        $this->dom->addChild(
            $infpedreg_inner,
            'dhEvento',
            $this->std->infpedreg->dhevento,
            true
        );
        if (isset($this->std->infpedreg->cnpjautor)) {
            $this->dom->addChild(
                $infpedreg_inner,
                'CNPJAutor',
                $this->std->infpedreg->cnpjautor,
                true
            );
        }
        if (isset($this->std->infpedreg->cpfautor)) {
            $this->dom->addChild(
                $infpedreg_inner,
                'CPFAutor',
                $this->std->infpedreg->cpfautor,
                true
            );
        }
        $this->dom->addChild(
            $infpedreg_inner,
            'chNFSe',
            $this->std->infpedreg->chnfse,
            true
        );


        if (isset($this->std->infpedreg->e101101)) {
            $e101101_inner = $this->dom->createElement('e101101');
            $infpedreg_inner->appendChild($e101101_inner);
            $this->dom->addChild(
                $e101101_inner,
                'xDesc',
                $this->std->infpedreg->e101101->xdesc,
                true
            );
            $this->dom->addChild(
                $e101101_inner,
                'cMotivo',
                $this->std->infpedreg->e101101->cmotivo,
                true
            );
            $this->dom->addChild(
                $e101101_inner,
                'xMotivo',
                $this->std->infpedreg->e101101->xmotivo,
                true
            );
        }

        $dps = $this->dom->createElement('DPS');
        $dps->setAttribute('versao', $this->std->version);
        $dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $this->evento->appendChild($infpedreg_inner);
        $this->dom->appendChild($this->evento);
        /*        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->dom->saveXML());*/
        return $this->dom->saveXML();
    }

    public function setFormatOutput(bool $formatOutput)
    {
        $this->dom->formatOutput = $formatOutput;
    }

    public function setStd(stdClass $std)
    {
        $this->init($std);
    }

    /**
     * Mudar todas proprioedades da stdClass para minúsculas
     * @param stdClass $data
     * @return stdClass
     */
    public static function propertiesToLower(stdClass $data)
    {
        $properties = get_object_vars($data);
        $clone = new stdClass();
        foreach ($properties as $key => $value) {
            if ($value instanceof stdClass) {
                $value = self::propertiesToLower($value);
            }
            $newkey = strtolower($key);
            $clone->{$newkey} = $value;
        }
        return $clone;
    }

    //    /**
    //     * Validation json data from json Schema
    //     * @param stdClass $data
    //     * @return boolean
    //     * @throws \RuntimeException
    //     */
    //    protected function validInputData()
    //    {
    //        if (!is_file($this->jsonschema)) {
    //            return true;
    //        }
    //        $validator = new JsonValid();
    //        $validator->check($this->std, (object)['$ref' => 'file://' . $this->jsonschema]);
    //        if (!$validator->isValid()) {
    //            $msg = "";
    //            foreach ($validator->getErrors() as $error) {
    //                $msg .= sprintf("[%s] %s\n", $error['property'], $error['message']);
    //            }
    //            throw new InvalidArgumentException($msg);
    //        }
    //        return true;
    //    }

    public function getDpsId()
    {
        return $this->dpsId;
    }

    public function getEventoId()
    {
        return $this->preId;
    }

    private function generateId()
    {
        $string = 'DPS';
        $string .= substr($this->std->infdps->clocemi, 0, 7); //Cód.Mun. (7) +
        $string .= isset($this->std->infdps->prest->cnpj) ? 2 : 1; //Tipo de Inscrição Federal (1) +
        if (isset($this->std->infdps->prest->cnpj)) {
            $inscricao = $this->std->infdps->prest->cnpj;
        } else {
            $inscricao = $this->std->infdps->prest->cpf;
        }
        $string .= str_pad((string)$inscricao, 14, '0', STR_PAD_LEFT); //Inscrição Federal (14 - CPF completar com 000 à esquerda) +
        $string .= str_pad((string)$this->std->infdps->serie, 5, '0', STR_PAD_LEFT); //Série DPS (5) +
        $string .= str_pad((string)$this->std->infdps->ndps, 15, '0', STR_PAD_LEFT); //Série DPS (5) +
        $this->dpsId = $string;
        return $string;
    }

    private function generatePre()
    {
        $string = 'PRE';
        $string .= $this->std->infpedreg->chnfse; //Chave de acesso da NFS-e (50) +
        $string .= $this->codigoEvento(); //Código do evento (6)
        $this->preId = $string;
        return $string;
    }

    private function codigoEvento()
    {
        $codigo = '000000';
        switch (true) {
            case isset($this->std->infpedreg->e101101):
                $codigo = '101101';
                break;
            case isset($this->std->infpedreg->e105102):
                $codigo = '105102';
                break;
        }

        return $codigo;
    }

}
