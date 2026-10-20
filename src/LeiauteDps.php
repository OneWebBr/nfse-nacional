<?php

namespace Hadder\NfseNacional;

/** Perfil de geração; não representa o ambiente nem o atributo versao do XML. */
enum LeiauteDps: string
{
    case LEGADO = 'legado';
    case NT009 = 'nt009';
}
