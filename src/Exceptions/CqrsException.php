<?php

namespace TheCorps\LaravelCqrs\Exceptions;

use RuntimeException;

/**
 * Base class for every exception thrown by the CQRS infrastructure itself.
 *
 * It extends RuntimeException so that existing code catching RuntimeException
 * keeps working.
 */
abstract class CqrsException extends RuntimeException {}
