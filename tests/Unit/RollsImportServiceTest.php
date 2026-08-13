<?php

namespace Tests\Unit;

use App\Services\RollsImportService;
use PHPUnit\Framework\TestCase;

class RollsImportServiceTest extends TestCase
{
    public function test_parse_rows_maps_template_header_columns()
    {
        $rows = [
            ['Roll No.', 'Bale No.', 'Lot ID', 'Color', 'Quantity (YD)'],
            ['VR-1', 'B-1', 'LOT-A', 'Navy', '75.50'],
            ['VR-2', '', 'LOT-B', '', 80],
            ['', '', '', '', 'not-a-number'], // skipped
        ];

        $data = RollsImportService::parseRows($rows);

        $this->assertCount(2, $data);
        $this->assertSame('VR-1', $data[0]['vendor_roll_no']);
        $this->assertSame('B-1', $data[0]['bale_no']);
        $this->assertSame('LOT-A', $data[0]['internal_id']);
        $this->assertSame('Navy', $data[0]['color']);
        // "Quantity (YD)" is recognised as the YD metric column
        $this->assertSame(75.50, $data[0]['length_yd']);
        $this->assertSame(80.0, $data[1]['length_yd']);
    }

    public function test_parse_rows_captures_all_metric_columns()
    {
        $rows = [
            ['Roll No.', 'Bale No.', 'Lot ID', 'Color', 'Length (YD)', 'Length (M)', 'Weight (KG)'],
            ['VR-1', 'B-1', 'LOT-A', 'Navy', '75.50', '69.04', '12.30'],
            ['VR-2', '', 'LOT-B', '', '', '50.00', ''], // one metric is enough
        ];

        $data = RollsImportService::parseRows($rows);

        $this->assertCount(2, $data);
        $this->assertSame(75.50, $data[0]['length_yd']);
        $this->assertSame(69.04, $data[0]['length_m']);
        $this->assertSame(12.30, $data[0]['weight']);
        $this->assertSame(50.00, $data[1]['length_m']);
        $this->assertArrayNotHasKey('length_yd', $data[1]);
    }

    public function test_parse_rows_maps_reordered_template_columns()
    {
        $rows = [
            ['Color', 'Lot ID', 'Quantity (M)', 'Roll No.'],
            ['Red', 'LOT-9', '68,5', 'VR-9'], // comma decimal
        ];

        $data = RollsImportService::parseRows($rows);

        $this->assertCount(1, $data);
        $this->assertSame('VR-9', $data[0]['vendor_roll_no']);
        $this->assertSame('LOT-9', $data[0]['internal_id']);
        $this->assertSame('Red', $data[0]['color']);
        $this->assertSame(68.5, $data[0]['length_m']);
    }

    public function test_parse_rows_falls_back_to_legacy_positional_format()
    {
        $rows = [
            ['LOT-1', '100.00'],
            ['LOT-2', 55],
            ['header-ish', 'n/a'], // skipped
        ];

        $data = RollsImportService::parseRows($rows);

        $this->assertCount(2, $data);
        $this->assertSame('LOT-1', $data[0]['internal_id']);
        $this->assertSame(100.0, $data[0]['quantity']);
        $this->assertSame('', $data[0]['vendor_roll_no']);
    }

    public function test_parse_pdf_text_picks_decimal_quantity_and_ids()
    {
        $text = implode("\n", [
            'PACKING LIST',
            'Roll No   Lot        Qty (YD)',
            'VR-001    LOT-123    75.50',
            'VR-002    LOT-124    80,25',
            'Total                155.75',
            'Page 1 of 1',
        ]);

        $data = RollsImportService::parsePdfText($text);

        $this->assertCount(2, $data);
        $this->assertSame('VR-001', $data[0]['vendor_roll_no']);
        $this->assertSame('LOT-123', $data[0]['internal_id']);
        $this->assertSame(75.50, $data[0]['quantity']);
        $this->assertSame(80.25, $data[1]['quantity']);
    }

    public function test_parse_pdf_text_prefers_decimal_over_trailing_integer()
    {
        // Quantity is not the last number on the line — the integer lot code is.
        $data = RollsImportService::parsePdfText("LOT-5 62.75 12345\n");

        $this->assertCount(1, $data);
        $this->assertSame(62.75, $data[0]['quantity']);
        $this->assertSame('LOT-5', $data[0]['internal_id']);
    }

    public function test_parse_pdf_text_detects_yd_m_pair_and_weight()
    {
        // 69.04 / 75.50 = 0.9144 → recognised as a YD + M pair; the extra
        // decimal is the roll weight.
        $data = RollsImportService::parsePdfText('VR-1 LOT-1 75.50 69.04 12.30');

        $this->assertCount(1, $data);
        $this->assertSame(75.50, $data[0]['length_yd']);
        $this->assertSame(69.04, $data[0]['length_m']);
        $this->assertSame(12.30, $data[0]['weight']);
        $this->assertArrayNotHasKey('quantity', $data[0]);
        $this->assertSame('VR-1', $data[0]['vendor_roll_no']);
        $this->assertSame('LOT-1', $data[0]['internal_id']);
    }

    public function test_parse_pdf_text_single_id_stays_lot_id()
    {
        $data = RollsImportService::parsePdfText('LOT-77 90.00');

        $this->assertCount(1, $data);
        $this->assertSame('', $data[0]['vendor_roll_no']);
        $this->assertSame('LOT-77', $data[0]['internal_id']);
    }

    public function test_to_number_handles_separator_styles()
    {
        $this->assertSame(1234.56, RollsImportService::toNumber('1,234.56'));
        $this->assertSame(1234.56, RollsImportService::toNumber('1.234,56'));
        $this->assertSame(75.5, RollsImportService::toNumber('75,5'));
        $this->assertSame(80.0, RollsImportService::toNumber('80'));
        $this->assertNull(RollsImportService::toNumber('LOT-1'));
        $this->assertNull(RollsImportService::toNumber(''));
    }

    public function test_to_number_strips_invisible_characters_from_pasted_data()
    {
        // Non-breaking space (thousands separator in some Excel locales),
        // zero-width space, and a BOM — all commonly slip in when a vendor
        // copy-pastes quantities from a PDF or web page. Left unstripped,
        // these fail the digit regex and silently reject the row.
        $this->assertSame(121.0, RollsImportService::toNumber("121.00\u{00A0}"));
        $this->assertSame(1234.56, RollsImportService::toNumber("1\u{00A0}234.56"));
        $this->assertSame(121.0, RollsImportService::toNumber("121\u{200B}.00"));
        $this->assertSame(121.0, RollsImportService::toNumber("\u{FEFF}121.00"));
    }
}
