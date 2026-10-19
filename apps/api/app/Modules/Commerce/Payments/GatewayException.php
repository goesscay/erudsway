<?php

namespace App\Modules\Commerce\Payments;

use RuntimeException;

/** The provider was unreachable or rejected a request. */
class GatewayException extends RuntimeException {}
