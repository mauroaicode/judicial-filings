<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Application architecture rules (imports, namespaces, suffixes, etc.).
 * Keeps Laravel bootstrapped for Pest arch plugin without opening a DB connection.
 */
abstract class ArchitectureTestCase extends BaseTestCase {}
