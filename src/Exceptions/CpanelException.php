<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Exceptions;

use RuntimeException;

/**
 * Base exception for every error raised by the cPanel and WHM integration.
 */
class CpanelException extends RuntimeException {}
