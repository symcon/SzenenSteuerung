<?php
declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class SzenenSteuerungValidationTest extends TestCaseSymconValidation
{
    public function testValidateSzenenSteuerung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateSzenenSteuerungModule(): void
    {
        $this->validateModule(__DIR__ . '/../SzenenSteuerung');
    }
}