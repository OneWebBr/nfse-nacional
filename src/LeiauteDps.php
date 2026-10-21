<?php

namespace Hadder\NfseNacional;

/** Perfil de geração; não representa o ambiente nem o atributo versao do XML. */
enum LeiauteDps: string
{
    case LEGADO = 'legado';
    /** Release 1 de homologação: XSD PRODREST de 20/10/2026. */
    case NT009 = 'nt009';
}
