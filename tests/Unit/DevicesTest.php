<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psydox\WPStats\Psydox_WP_Stats_Devices;

final class DevicesTest extends TestCase
{
    public function testParsesMobileAndroidDevice(): void
    {
        $parser = new Psydox_WP_Stats_Devices();
        $ua = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';

        $result = $parser->parse($ua);

        $this->assertSame('Chrome', $result['browser']);
        $this->assertSame('Android', $result['operating_system']);
        $this->assertSame('mobile', $result['device_type']);
        $this->assertSame('Google', $result['device_brand']);
    }

    public function testParsesBotDeviceType(): void
    {
        $parser = new Psydox_WP_Stats_Devices();
        $result = $parser->parse('Mozilla/5.0 (compatible; Bingbot/2.0; +http://www.bing.com/bingbot.htm)');

        $this->assertSame('bot', $result['device_type']);
        $this->assertSame('Bingbot', $result['browser']);
    }

    public function testParsesSamsungBrowserAndModel(): void
    {
        $parser = new Psydox_WP_Stats_Devices();
        $ua = 'Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/120.0.0.0 Mobile Safari/537.36';

        $result = $parser->parse($ua);

        $this->assertSame('Samsung Internet', $result['browser']);
        $this->assertSame('Samsung', $result['device_brand']);
        $this->assertSame('SM-S918B', $result['device_model']);
    }

    public function testParsesEdgeOnWindowsVersionMapping(): void
    {
        $parser = new Psydox_WP_Stats_Devices();
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 Edg/125.0.2535.51';

        $result = $parser->parse($ua);

        $this->assertSame('Edge', $result['browser']);
        $this->assertSame('Windows', $result['operating_system']);
        $this->assertSame('10', $result['os_version']);
    }

    public function testParsesTabletAndroidWithoutMobileToken(): void
    {
        $parser = new Psydox_WP_Stats_Devices();
        $ua = 'Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

        $result = $parser->parse($ua);

        $this->assertSame('tablet', $result['device_type']);
        $this->assertSame('Samsung', $result['device_brand']);
    }
}
