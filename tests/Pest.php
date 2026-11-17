<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// Feature tests run every migration on a fresh in-memory database, so a broken migration fails the suite.
uses(RefreshDatabase::class)->in('Feature');
