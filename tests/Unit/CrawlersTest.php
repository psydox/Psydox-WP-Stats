<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psydox\WPStats\Psydox_WP_Stats_Crawlers;

final class CrawlersTest extends TestCase
{
    public function testDetectsKnownCrawler(): void
    {
        $detector = new Psydox_WP_Stats_Crawlers();
        $result = $detector->detect('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $this->assertTrue($result['is_crawler']);
        $this->assertSame('Googlebot', $result['crawler_name']);
        $this->assertSame('Search Engine', $result['crawler_category']);
        $this->assertSame('low', $result['crawler_risk']);
    }

    public function testMarksRegularBrowserAsHuman(): void
    {
        $detector = new Psydox_WP_Stats_Crawlers();
        $result = $detector->detect('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0.0.0 Safari/537.36');

        $this->assertFalse($result['is_crawler']);
        $this->assertNull($result['crawler_name']);
    }

    public function testDenylistPatternMarksCrawlerAsDenied(): void
    {
        $detector = new Psydox_WP_Stats_Crawlers();
        $result = $detector->detect(
            'Mozilla/5.0 (compatible; FriendlyBot/1.0; +https://example.org)',
            array(
                'bot_denylist' => "friendlybot\notherbot",
            )
        );

        $this->assertTrue($result['is_crawler']);
        $this->assertTrue($result['is_denied']);
        $this->assertSame('Denied Bot', $result['crawler_name']);
        $this->assertSame('custom_deny', $result['classification_source']);
    }

    public function testAllowlistOverridesDenylist(): void
    {
        $detector = new Psydox_WP_Stats_Crawlers();
        $result = $detector->detect(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            array(
                'bot_allowlist' => 'googlebot',
                'bot_denylist' => 'googlebot',
            )
        );

        $this->assertTrue($result['is_crawler']);
        $this->assertTrue($result['is_allowed']);
        $this->assertFalse($result['is_denied']);
        $this->assertSame('Googlebot', $result['crawler_name']);
    }
}
