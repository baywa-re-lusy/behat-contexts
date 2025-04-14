<?php

namespace BayWaReLusy\BehatContext\HalContext;

enum ApiResponseFormat: string
{
    case HAL    = 'hal';
    case JSONLD = 'jsonld';
}
