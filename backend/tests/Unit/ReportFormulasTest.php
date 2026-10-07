<?php
namespace Tests\Unit;
use App\Services\ReportFormulas;
use PHPUnit\Framework\TestCase;
class ReportFormulasTest extends TestCase
{
    public function test_shared_editor_and_server_formula_cases(): void
    {
        $cases = json_decode(file_get_contents(__DIR__.'/../../../tests/fixtures/report-formulas.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as $case) $this->assertEquals($case['expected'], (new ReportFormulas)->evaluate($case['tests'], $case['formula']), $case['name']);
    }
}
