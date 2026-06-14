<?php

namespace JohnGuoy\LaravelX402\Exceptions;

use RuntimeException;

class X402Exception extends RuntimeException {}

class UnsupportedAssetException extends X402Exception {}

class FacilitatorException extends X402Exception {}

class InvalidPaymentPayloadException extends X402Exception {}

class MissingWalletAddressException extends X402Exception {}

class MissingAssetAddressException extends X402Exception {}
