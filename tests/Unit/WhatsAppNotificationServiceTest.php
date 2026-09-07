<?php

namespace Tests\Unit;

use App\Services\WhatsAppNotificationService;
use PHPUnit\Framework\TestCase;

class WhatsAppNotificationServiceTest extends TestCase
{
    public function test_to_chat_id_normalizes_local_indonesian_format()
    {
        $this->assertSame('628123456789@c.us', WhatsAppNotificationService::toChatId('08123456789'));
    }

    public function test_to_chat_id_strips_formatting_punctuation()
    {
        $this->assertSame('628123456789@c.us', WhatsAppNotificationService::toChatId('+62 812-3456-789'));
    }

    public function test_to_chat_id_passes_through_an_already_formed_chat_id()
    {
        $this->assertSame('628123456789@c.us', WhatsAppNotificationService::toChatId('628123456789@c.us'));
        $this->assertSame('123456-789@g.us', WhatsAppNotificationService::toChatId('123456-789@g.us'));
    }

    public function test_to_chat_id_rejects_empty_or_non_numeric_input()
    {
        $this->assertNull(WhatsAppNotificationService::toChatId(''));
        $this->assertNull(WhatsAppNotificationService::toChatId('   '));
        $this->assertNull(WhatsAppNotificationService::toChatId('n/a'));
    }

    public function test_parse_phone_list_splits_and_dedupes()
    {
        $this->assertSame(
            ['628123456789@c.us', '628987654321@c.us'],
            WhatsAppNotificationService::parsePhoneList('08123456789, 0812-3456-789; 628987654321')
        );
    }

    public function test_parse_phone_list_handles_null_and_blank()
    {
        $this->assertSame([], WhatsAppNotificationService::parsePhoneList(null));
        $this->assertSame([], WhatsAppNotificationService::parsePhoneList('   '));
    }
}
