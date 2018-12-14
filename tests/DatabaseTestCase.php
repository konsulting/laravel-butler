<?php

namespace Konsulting\Butler;

use Illuminate\Foundation\Testing\DatabaseTransactions;

abstract class DatabaseTestCase extends TestCase
{
    use DatabaseTransactions;
}
