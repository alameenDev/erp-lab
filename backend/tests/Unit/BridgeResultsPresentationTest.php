<?php

namespace Tests\Unit;

use App\Services\BridgeCbcService;
use PHPUnit\Framework\TestCase;

class BridgeResultsPresentationTest extends TestCase
{
    public function test_highlighting_uses_device_range_and_preserves_values(): void
    {
        $cases = [
            ['11.10', '3.50-9.50', true], ['17.3', '10 to 14.6', true],
            ['9.50', '3.50-9.50', false], ['3.49', '3.50-9.50', false],
            ['7', '3.50-9.50', false], ['369', '125–350', true],
            ['0', '-5--1', true], ['1.1e2', '0-1e2', true],
            ['10', '<10', true], ['10', '<=10', false], ['11', '≤10', true],
            ['11', '', false], ['11', 'Normal', false], ['11', '20-10', false],
            ['>10', '0-9', false], ['---', '0-9', false], ['1e999', '0-9', false],
        ];
        foreach ($cases as [$value, $range, $high]) {
            $escape = fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
            $html = '<table><tbody><tr><td>WBC</td><td>'.$escape($value).'</td><td>10*3/uL</td><td>'.$escape($range).'</td></tr></tbody></table>';
            $content = ['bridge_cbc' => true, 'html' => $html, 'review_required' => true];
            $out = BridgeCbcService::displayContent($content);
            $this->assertSame($high, str_contains($out['html'], 'data-cbc-high="1"'), $value.' / '.$range);
            $this->assertStringContainsString($escape($value), $out['html']);
            $this->assertStringContainsString('<td>'.$escape($range).'</td>', $out['html']);
            $this->assertSame($out, BridgeCbcService::displayContent($out));
            $this->assertTrue($out['review_required']);
            $content['bridge_cbc'] = false;
            $this->assertSame($content, BridgeCbcService::displayContent($content));
        }
    }

    public function test_new_snapshot_and_saved_snapshot_have_the_same_highlighting(): void
    {
        $service = new BridgeCbcService;
        $table = new \ReflectionMethod($service, 'table');
        $html = $table->invoke($service, [
            ['name' => 'PLT', 'value' => '369', 'unit' => '10*3/uL', 'reference_range' => '125-350'],
            ['name' => 'RBC', 'value' => '4.79', 'unit' => '10*6/uL', 'reference_range' => '3.80-5.10'],
        ]);
        $this->assertSame(1, substr_count($html, 'data-cbc-high="1"'));
        $this->assertStringContainsString('color:#dc2626 !important', $html);
        $this->assertSame($html, BridgeCbcService::displayContent(['bridge_cbc' => true, 'html' => $html])['html']);
    }
}
