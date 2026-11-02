<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Unit;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;

/**
 * @see AppDetailsParser
 * @see \ScriptFUSION\Porter\Provider\Steam\Scrape\SystemRequirementsParser
 */
final class SystemRequirementsTest extends TestCase
{
    public function testNoRequirements(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(''));

        self::assertArrayHasKey('system_requirements', $app);
        self::assertNull($app['system_requirements']);
    }

    /**
     * Tests that Windows, macOS and Linux blocks are parsed separately and returned under their own
     * platform keys.
     */
    public function testAllPlatforms(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> Windows 10<br></li>
            <li><strong>Processor:</strong> Intel Core i5<br></li>
            <li><strong>Memory:</strong> 8 GB RAM<br></li>
            <li><strong>Graphics:</strong> NVIDIA GeForce GTX 1060<br></li>
            </ul>
            </ul></div>
            </div>
            <div class="game_area_sys_req sysreq_content" data-os="mac">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> Mac OSX 10.10+<br></li>
            <li><strong>Processor:</strong> Intel Core i3<br></li>
            <li><strong>Memory:</strong> 4 GB RAM<br></li>
            <li><strong>Graphics:</strong> NVIDIA GeForce 8600M GT<br></li>
            </ul>
            </ul></div>
            </div>
            <div class="game_area_sys_req sysreq_content" data-os="linux">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> Ubuntu 12.04 LTS<br></li>
            <li><strong>Processor:</strong> Intel Core i3-3220<br></li>
            <li><strong>Memory:</strong> 4 GB RAM<br></li>
            <li><strong>Graphics:</strong> NVIDIA GeForce GTX 660<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements'];
        $platforms = $requirements['platforms'];

        self::assertSame(['windows', 'mac', 'linux'], array_keys($platforms));
        self::assertSame(['platforms'], array_keys($requirements));

        self::assertSame('Mac OSX 10.10+', $platforms['mac']['minimum']['os']['raw']);
        self::assertSame([], $platforms['mac']['minimum']['os']['windows']);
        self::assertSame(['Intel Core i3'], $platforms['mac']['minimum']['processor']['models']);
        self::assertSame(4096, $platforms['mac']['minimum']['memory']['mb']);
        self::assertSame(['NVIDIA GeForce 8600M GT'], $platforms['mac']['minimum']['graphics']['models']);
        self::assertNull($platforms['mac']['recommended']);

        self::assertSame('Ubuntu 12.04 LTS', $platforms['linux']['minimum']['os']['raw']);
        self::assertSame(['Intel Core i3-3220'], $platforms['linux']['minimum']['processor']['models']);
        self::assertSame(['NVIDIA GeForce GTX 660'], $platforms['linux']['minimum']['graphics']['models']);
        self::assertNull($platforms['linux']['recommended']);
    }

    /**
     * Tests that a macOS-only page does not invent Windows requirements.
     */
    public function testMacOnly(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="mac">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> macOS 11 Big Sur<br></li>
            <li><strong>Processor:</strong> Apple M1<br></li>
            <li><strong>Memory:</strong> 8 GB RAM<br></li>
            <li><strong>Graphics:</strong> Apple M1<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements'];

        self::assertSame(['mac'], array_keys($requirements['platforms']));
        self::assertSame('macOS 11 Big Sur', $requirements['platforms']['mac']['minimum']['os']['raw']);
    }

    /**
     * Tests that Linux requirements without an OS line still parse, since some publishers omit it.
     */
    public function testLinuxWithoutOsLine(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="linux">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>Processor:</strong> Intel Core i3-3220<br></li>
            <li><strong>Memory:</strong> 4 GB RAM<br></li>
            <li><strong>Graphics:</strong> NVIDIA GeForce GTX 660<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements'];

        self::assertNull($requirements['platforms']['linux']['minimum']['os']['raw']);
        self::assertSame(['linux'], array_keys($requirements['platforms']));
        self::assertSame(['Intel Core i3-3220'], $requirements['platforms']['linux']['minimum']['processor']['models']);
    }

    /**
     * Tests that a tier containing only an architecture note parses as null instead of an empty tier.
     */
    public function testNotesOnlyBlock(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Recommended:</strong><br><ul class="bb_ul">
            <li>Requires a 64-bit processor and operating system<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        self::assertNull($app['system_requirements']);
    }

    /**
     * Tests that a Windows block with no usable requirements data parses as null.
     */
    public function testStorageOnlyBlock(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>Storage:</strong> 82 MB available space<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        self::assertNull($app['system_requirements']);
    }

    /**
     * Tests the standard two-column layout with labelled lists in both tiers.
     */
    public function testStandardTwoColumn(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_leftCol"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> Microsoft Windows XP / Vista / 7<br></li>
            <li><strong>Processor:</strong> 1.2GHz processor<br></li>
            <li><strong>Memory:</strong> 512 MB RAM<br></li>
            <li><strong>Graphics:</strong> DirectX 8-compatible graphics card
            with at least 32MB of video memory<br></li>
            </ul>
            </ul></div>
            <div class="game_area_sys_req_rightCol"><ul>
            <strong>Recommended:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> Microsoft Windows XP / Vista / 7<br></li>
            <li><strong>Processor:</strong> 1.4GHz processor or faster<br></li>
            <li><strong>Memory:</strong> 1 GB RAM<br></li>
            <li><strong>Graphics:</strong> DirectX 8-compatible graphics card
            with at least 32MB of video memory<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['XP', 'Vista', '7'], $requirements['minimum']['os']['windows']);
        self::assertSame('XP', $requirements['minimum']['os']['minimum_windows']);
        self::assertFalse($requirements['minimum']['os']['requires_64bit']);

        self::assertSame(1.2, $requirements['minimum']['processor']['speed_ghz']);
        self::assertNull($requirements['minimum']['processor']['cores']);
        self::assertSame([], $requirements['minimum']['processor']['models']);

        self::assertSame(512, $requirements['minimum']['memory']['mb']);

        self::assertSame(32, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame([], $requirements['minimum']['graphics']['models']);

        self::assertSame(1.4, $requirements['recommended']['processor']['speed_ghz']);
        self::assertSame(1024, $requirements['recommended']['memory']['mb']);
    }

    /**
     * Tests a single-column layout with only a minimum tier.
     */
    public function testSingleColumnMinimumOnly(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS *:</strong> Windows 7 / Vista / XP<br></li>
            <li><strong>Processor:</strong> 3.0 GHz P4, Dual Core 2.0 (or higher)
            or AMD64X2 (or higher)<br></li>
            <li><strong>Memory:</strong> 2 GB RAM<br></li>
            <li><strong>Graphics:</strong> Video card must be 128 MB or more and with support
            for Pixel Shader 2.0b (ATI Radeon X800 or higher / NVIDIA GeForce 7600 or higher /
            Intel HD Graphics 2000 or higher).<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertNull($requirements['recommended']);
        self::assertSame(['XP', 'Vista', '7'], $requirements['minimum']['os']['windows']);
        self::assertSame(2.0, $requirements['minimum']['processor']['speed_ghz']);
        self::assertSame(2, $requirements['minimum']['processor']['cores']);
        self::assertSame(['Pentium 4', 'Athlon 64 X2'], $requirements['minimum']['processor']['models']);
        self::assertSame(2048, $requirements['minimum']['memory']['mb']);
        self::assertSame(128, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame(
            ['ATI Radeon X800', 'NVIDIA GeForce 7600', 'Intel HD Graphics 2000'],
            $requirements['minimum']['graphics']['models']
        );
    }

    /**
     * Tests the legacy single-column layout where both tiers share one flat text block.
     */
    public function testLegacyFlatTiers(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <p><strong>Minimum:</strong> 500 mhz processor, 96mb ram, 16mb video card,
            Windows XP, Mouse, Keyboard, Internet Connection<br></p>
            <p><strong>Recommended:</strong> 800 mhz processor, 128mb ram, 32mb+ video card,
            Windows XP, Mouse, Keyboard, Internet Connection<br></p>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(0.5, $requirements['minimum']['processor']['speed_ghz']);
        self::assertSame(96, $requirements['minimum']['memory']['mb']);
        self::assertSame(16, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame(['XP'], $requirements['minimum']['os']['windows']);

        self::assertSame(0.8, $requirements['recommended']['processor']['speed_ghz']);
        self::assertSame(128, $requirements['recommended']['memory']['mb']);
        self::assertSame(32, $requirements['recommended']['graphics']['vram_mb']);
    }

    /**
     * Tests that a comma inside parentheses does not cut a flat tier fragment short, keeping the
     * model intact.
     *
     * @see https://store.steampowered.com/app/240/Counter-Strike_Source/
     */
    public function testCommaInParentheses(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <p><strong>Minimum: </strong>1.7 GHz Processor, 512MB RAM, DirectX&reg; 8.1 level Graphics
            Card (Requires support for SSE), Windows&reg; 7 (32/64-bit)/Vista/XP, Mouse, Keyboard,
            Internet Connection</p>
            <p><strong>Recommended: </strong>Pentium 4 processor (3.0GHz, or better), 1GB RAM,
            DirectX&reg; 9 level Graphics Card, Windows&reg; 7 (32/64-bit)/Vista/XP, Mouse, Keyboard,
            Internet Connection</p>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['XP', 'Vista', '7'], $requirements['minimum']['os']['windows']);
        self::assertSame(
            'DirectX® 8.1 level Graphics Card (Requires support for SSE)',
            $requirements['minimum']['graphics']['raw']
        );

        $recommended = $requirements['recommended'];

        self::assertSame('Pentium 4 processor (3.0GHz, or better)', $recommended['processor']['raw']);
        self::assertSame(3.0, $recommended['processor']['speed_ghz']);
        self::assertSame(['Pentium 4'], $recommended['processor']['models']);
        self::assertSame(1024, $recommended['memory']['mb']);
        self::assertSame('Windows® 7 (32/64-bit)/Vista/XP', $recommended['os']['raw']);
        self::assertSame(['XP', 'Vista', '7'], $recommended['os']['windows']);
        self::assertFalse($recommended['os']['requires_64bit']);
    }

    /**
     * Tests `<strong>` labels separated by `<br>` without list items.
     */
    public function testStrongLedColumns(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_leftCol"><ul>
            <strong>OS Version: </strong>Windows 10 64-bit<br>
            <strong>Processor: </strong>Intel Core i5-3570 @3.4GHz or AMD Ryzen 5 1400 @3.5GHz<br>
            <strong>Memory: </strong>8GB System RAM<br>
            <strong>Graphics:</strong>NVIDIA Geforce GTX 650 TI (2GB) or AMD HD 7750 (1GB)<br>
            </ul></div>
            <div class="game_area_sys_req_rightCol"><ul>
            <strong>OS Version: </strong>Windows 10 64-bit<br>
            <strong>Processor: </strong>Intel Core i5-6600k @3.5GHz or AMD Ryzen 5 1600 @3.2GHz<br>
            <strong>Memory: </strong>8GB System RAM<br>
            <strong>Graphics:</strong>NVIDIA GeForce GTX 1070 TI (8GB) or AMD RX Vega 56 (8GB)<br>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['10'], $requirements['minimum']['os']['windows']);
        self::assertTrue($requirements['minimum']['os']['requires_64bit']);
        self::assertSame(3.4, $requirements['minimum']['processor']['speed_ghz']);
        self::assertSame(['Intel Core i5-3570', 'AMD Ryzen 5 1400'], $requirements['minimum']['processor']['models']);
        self::assertSame(8192, $requirements['minimum']['memory']['mb']);
        self::assertSame(1024, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame(
            ['NVIDIA Geforce GTX 650 TI', 'AMD HD 7750'],
            $requirements['minimum']['graphics']['models']
        );

        self::assertSame(3.2, $requirements['recommended']['processor']['speed_ghz']);
        self::assertSame(8192, $requirements['recommended']['graphics']['vram_mb']);
    }

    /**
     * Tests a list without a tier header, which is read as the minimum tier.
     */
    public function testHeaderlessList(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <ul class="bb_ul">
            <li><strong>OS *:</strong> Windows 2000, XP or Vista<br></li>
            <li><strong>Processor:</strong> 486 or higher<br></li>
            <li><strong>Memory:</strong> 16 MB<br></li>
            <li><strong>Graphics:</strong> DirectX<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertNull($requirements['recommended']);
        self::assertSame(['2000', 'XP', 'Vista'], $requirements['minimum']['os']['windows']);
        self::assertSame('2000', $requirements['minimum']['os']['minimum_windows']);
        self::assertSame(['486'], $requirements['minimum']['processor']['models']);
        self::assertSame(16, $requirements['minimum']['memory']['mb']);
        self::assertSame([], $requirements['minimum']['graphics']['models']);
    }

    /**
     * Tests that a version list offering both architectures does not require 64-bit.
     */
    public function testCombined32And64Bit(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_leftCol"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> Windows 7 32-bit and 64-bit<br></li>
            <li><strong>Processor:</strong> 2.4 GHz processor<br></li>
            <li><strong>Memory:</strong> 1 GB RAM<br></li>
            <li><strong>Graphics:</strong> DirectX 9 graphics card<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        self::assertFalse(
            $app['system_requirements']['platforms']['windows']['minimum']['os']['requires_64bit']
        );
    }

    /**
     * Tests that an unlabelled 64-bit note marks the OS as requiring 64-bit.
     */
    public function testRequires64BitNote(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_leftCol"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li>Requires a 64-bit processor and operating system<br></li>
            <li><strong>OS:</strong> Windows 7 / 8 / 10 64-bit<br></li>
            <li><strong>Processor:</strong> Quad-core Intel or AMD processor<br></li>
            <li><strong>Memory:</strong> 8 GB RAM<br></li>
            <li><strong>Graphics:</strong> Video card with 2GB of video memory<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertTrue($requirements['minimum']['os']['requires_64bit']);
        self::assertSame(4, $requirements['minimum']['processor']['cores']);
        self::assertSame([], $requirements['minimum']['processor']['models']);
        self::assertSame(8192, $requirements['minimum']['memory']['mb']);
        self::assertSame(2048, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame([], $requirements['minimum']['graphics']['models']);
    }

    /**
     * Tests that a "VRAM …" additional note refines the graphics requirement.
     */
    public function testVramAdditionalNote(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_leftCol"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> 64-bit Windows 11<br></li>
            <li><strong>Processor:</strong> Core i5-8400 / Ryzen 5 2600<br></li>
            <li><strong>Memory:</strong> 12 GB RAM<br></li>
            <li><strong>Graphics:</strong> GeForce GTX 1660 / Radeon RX 5500 XT 8GB / Arc A580<br></li>
            <li><strong>Additional Notes:</strong> VRAM 6 GB<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['11'], $requirements['minimum']['os']['windows']);
        self::assertTrue($requirements['minimum']['os']['requires_64bit']);
        self::assertSame(['Core i5-8400', 'Ryzen 5 2600'], $requirements['minimum']['processor']['models']);
        self::assertSame(12288, $requirements['minimum']['memory']['mb']);
        self::assertSame(6144, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame(
            ['GeForce GTX 1660', 'Radeon RX 5500 XT', 'Arc A580'],
            $requirements['minimum']['graphics']['models']
        );
    }

    /**
     * Tests that trademark symbols are stripped from model names for reliable matching.
     */
    public function testTrademarkStripping(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>OS:</strong> Windows 10<br></li>
            <li><strong>Processor:</strong> 4 hardware CPU threads - Intel® Core™ i5 750 or higher<br></li>
            <li><strong>Memory:</strong> 8 GB RAM<br></li>
            <li><strong>Graphics:</strong> Video card must be 1 GB or more and should be
            a DirectX 11-compatible with support for Shader Model 5.0<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(4, $requirements['minimum']['processor']['threads']);
        self::assertSame(['Intel Core i5 750'], $requirements['minimum']['processor']['models']);
        self::assertSame(1024, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame([], $requirements['minimum']['graphics']['models']);
    }

    /**
     * Tests real saved store pages parse into the same consistent format.
     */
    public function testDiscountedFixture(): void
    {
        $app = AppDetailsParser::tryParseStorePage($this->readFixture('discounted.html'));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['XP', 'Vista', '7'], $requirements['minimum']['os']['windows']);
        self::assertSame(1.2, $requirements['minimum']['processor']['speed_ghz']);
        self::assertSame(512, $requirements['minimum']['memory']['mb']);
        self::assertSame(1.4, $requirements['recommended']['processor']['speed_ghz']);
        self::assertSame(1024, $requirements['recommended']['memory']['mb']);
    }

    public function testInvalidDateFixture(): void
    {
        $app = AppDetailsParser::tryParseStorePage($this->readFixture('invalid date.html'));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['7', '8', '10'], $requirements['minimum']['os']['windows']);
        self::assertTrue($requirements['minimum']['os']['requires_64bit']);
        self::assertSame(['Intel Core i5', 'AMD A6'], $requirements['minimum']['processor']['models']);
        self::assertSame(4096, $requirements['minimum']['memory']['mb']);
        self::assertSame(2048, $requirements['minimum']['graphics']['vram_mb']);
        self::assertSame(4, $requirements['recommended']['processor']['cores']);
        self::assertSame(8192, $requirements['recommended']['memory']['mb']);
        self::assertSame(4096, $requirements['recommended']['graphics']['vram_mb']);
    }

    public function testYearOnlyFixture(): void
    {
        $app = AppDetailsParser::tryParseStorePage($this->readFixture('invalid date (year only).html'));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['Intel Pentium'], $requirements['minimum']['processor']['models']);
        self::assertSame(['GeForce 8600 GT'], $requirements['minimum']['graphics']['models']);
        self::assertSame(['Intel Core i3'], $requirements['recommended']['processor']['models']);
        self::assertSame(['GeForce GT 530'], $requirements['recommended']['graphics']['models']);
    }

    public function testTagsFixtureHasNoRecommendedTier(): void
    {
        $app = AppDetailsParser::tryParseStorePage($this->readFixture('tags.html'));

        $requirements = $app['system_requirements']['platforms']['windows'];

        self::assertSame(['7', '8', '10'], $requirements['minimum']['os']['windows']);
        self::assertSame(2.1, $requirements['minimum']['processor']['speed_ghz']);
        self::assertSame(
            ['AMD Athlon64 X2', 'Intel Core2 Duo E6300'],
            $requirements['minimum']['processor']['models']
        );
        self::assertSame(
            ['AMD Radeon HD 2600 XT', 'NVIDIA Geforce 8600'],
            $requirements['minimum']['graphics']['models']
        );
        self::assertNull($requirements['recommended']);
    }

    /**
     * Tests that flat macOS requirements classify a named graphics card as graphics, not memory,
     * and keep the macOS OS text.
     */
    public function testFlatMacRequirements(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="mac">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong> OS X Snow Leopard 10.6.3, 1GB RAM, 4GB Hard Drive Space,
            NVIDIA GeForce 8 or higher
            </ul></div>
            </div>
            HTML));

        $requirements = $app['system_requirements'];
        $minimum = $requirements['platforms']['mac']['minimum'];

        self::assertSame('OS X Snow Leopard 10.6.3', $minimum['os']['raw']);
        self::assertSame(1024, $minimum['memory']['mb']);
        self::assertSame('NVIDIA GeForce 8 or higher', $minimum['graphics']['raw']);
        self::assertSame(['NVIDIA GeForce 8'], $minimum['graphics']['models']);
    }

    /**
     * Tests that a flat macOS processor named after Apple is not mistaken for the operating system.
     */
    public function testFlatMacProcessor(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="mac">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong> Apple M1, 8GB RAM, macOS 11
            </ul></div>
            </div>
            HTML));

        $minimum = $app['system_requirements']['platforms']['mac']['minimum'];

        self::assertSame('macOS 11', $minimum['os']['raw']);
        self::assertSame('Apple M1', $minimum['processor']['raw']);
        self::assertSame(['Apple M1'], $minimum['processor']['models']);
        self::assertSame(8192, $minimum['memory']['mb']);
    }

    /**
     * Tests that flat video memory is classified as graphics even though it contains "memory".
     */
    public function testFlatVideoMemory(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong> Windows 7, 2 GHz processor, 2GB RAM, 512MB video memory
            </ul></div>
            </div>
            HTML));

        $minimum = $app['system_requirements']['platforms']['windows']['minimum'];

        self::assertSame(2048, $minimum['memory']['mb']);
        self::assertSame('512MB video memory', $minimum['graphics']['raw']);
        self::assertSame(512, $minimum['graphics']['vram_mb']);
    }

    /**
     * Tests that connector prose around vendor names does not become a processor model.
     */
    public function testProcessorConnectorProse(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="linux">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong><br><ul class="bb_ul">
            <li><strong>Processor:</strong> Dual core from Intel or AMD at 2.8 GHz<br></li>
            </ul>
            </ul></div>
            </div>
            HTML));

        $processor = $app['system_requirements']['platforms']['linux']['minimum']['processor'];

        self::assertSame(2.8, $processor['speed_ghz']);
        self::assertSame([], $processor['models']);
    }

    /**
     * Tests that Windows 98 is the oldest version when a flat list mentions 98, ME and 2000.
     */
    public function testWindows98Rank(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong> Windows 98 / ME / 2000
            </ul></div>
            </div>
            HTML));

        $os = $app['system_requirements']['platforms']['windows']['minimum']['os'];

        self::assertSame(['98', 'ME', '2000'], $os['windows']);
        self::assertSame('98', $os['minimum_windows']);
    }

    /**
     * Tests that a legacy flat block whose tiers both contain no usable data parses as null.
     */
    public function testEmptyLegacyTiers(): void
    {
        $app = AppDetailsParser::tryParseStorePage(self::createPage(<<<HTML
            <div class="game_area_sys_req sysreq_content active" data-os="win">
            <div class="game_area_sys_req_full"><ul>
            <strong>Minimum:</strong> None<br>
            <strong>Recommended:</strong> None
            </ul></div>
            </div>
            HTML));

        self::assertNull($app['system_requirements']);
    }

    private function readFixture(string $fixture): string
    {
        $fixture = file_get_contents(__DIR__ . "/../Fixture/$fixture");

        self::assertIsString($fixture);

        return $fixture;
    }

    private static function createPage(string $sysreq): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html><head>
            <meta itemprop="image" content="https://example.com/capsule.jpg">
            </head>
            <body class="v6 app">
            <div class="apphub_AppName">Test Game</div>
            <div class="breadcrumbs"><a href="/search/">All Games</a></div>
            <div class="apphub_OtherSiteInfo">
            <a href="https://steamcommunity.com/app/123">Community Hub</a>
            </div>
            <div class="game_description_snippet">Blurb</div>
            <div class="glance_ctn_responsive_left">
            <div class="release_date"><div class="subtitle column">Release Date:</div>
            <div class="date">Oct 28, 2026</div></div>
            </div>
            <div data-appid="123"></div>
            <div class="game_page_autocollapse sys_req" style="max-height: 300px;">
            <h2>System Requirements</h2>
            <div class="sysreq_contents">
            $sysreq
            </div>
            </div>
            </body></html>
            HTML;
    }
}
