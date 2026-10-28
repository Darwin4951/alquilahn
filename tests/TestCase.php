<?php
namespace Tests;
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
}
