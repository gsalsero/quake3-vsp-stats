<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vsp-q3a.php'; // Include the VSPParserQ3A class

class ConvertColorCodesTest extends TestCase
{
    protected static $statsAggregator;
    protected static $statsProcessor;
    protected $q3aParser;
    protected $xpParser;
    protected $xpParser103;

    public static function setUpBeforeClass(): void
    {
        self::$statsAggregator = new StatsAggregator();
        self::$statsProcessor = new StatsProcessor();
    }

    protected function setUp(): void
    {
        $q3aConfig = ["gametype" => "q3a"];
        $xpConfig = ["gametype" => "xp"];
        $xpConfig103 = ["gametype" => "xp", "xp_version" => 103];

        $this->q3aParser = new VSPParserQ3A($q3aConfig, self::$statsAggregator, self::$statsProcessor);
        $this->xpParser = new VSPParserQ3A($xpConfig, self::$statsAggregator, self::$statsProcessor);
        $this->xpParser103 = new VSPParserQ3A($xpConfig103, self::$statsAggregator, self::$statsProcessor);
    }

    public function testQ3aConvertAllColorCodes()
    {
        $input = "^1Red ^2Green ^3Blue ^4Yellow ^5Cyan ^6Magenta ^7White ^0Gray";
        $expected = "`#FFFFFF`#FF0000Red `#00FF00Green `#FFFF00Blue `#4444FFYellow `#00FFFFCyan `#FF00FFMagenta `#FFFFFFWhite `#777777Gray";
        $this->assertEquals($expected, $this->q3aParser->convertColorCodes($input));
    }

    public function testQ3aConvertEmptyName()
    {
        $input = "";
        $expected = " ";
        $this->assertEquals($expected, $this->q3aParser->convertColorCodes($input));
    }

    public function testQ3aConvertColorCodesWithSpecialChar()
    {
        $input = "^fSpecialChar";
        $expected = "`#FFFFFFSpecialChar";
        $this->assertEquals($expected, $this->q3aParser->convertColorCodes($input));
    }

    public function testQ3aConvertColorCodesWithHexColor()
    {
        $input = "^xFF0000Red";
        $expected = "`#FFFFFF`#FF0000Red";
        $this->assertEquals($expected, $this->q3aParser->convertColorCodes($input));
    }

    public function testQ3aConvertAllListedColors()
    {
        $input = "^0Gray ^1Red ^2Green ^3Yellow ^4Blue ^5Cyan ^6Magenta ^7White";
        $expected = "`#FFFFFF`#777777Gray `#FF0000Red `#00FF00Green `#FFFF00Yellow `#4444FFBlue `#00FFFFCyan `#FF00FFMagenta `#FFFFFFWhite";
        $this->assertEquals($expected, $this->q3aParser->convertColorCodes($input));
    }

    public function testXpConvertAllColorCodes()
    {
        $input = "^1Red ^2Green ^3Blue ^4Yellow ^5Cyan ^6Magenta ^7White ^0Gray";
        $expected = "`#e90000Red `#00dd24Green `#f5d800Blue `#2e61c8Yellow `#16b4a5Cyan `#f408f1Magenta `#efefefWhite `#555555Gray";
        $this->assertEquals($expected, $this->xpParser->convertColorCodes($input));
    }

    public function testXpConvertColorCodesWithSpecialChar()
    {
        $input = "#23SpecialChar";
        $expected = "`#efefef" . chr(hexdec('23')) . "SpecialChar";
        $this->assertEquals($expected, $this->xpParser->convertXPColorCodes($input));
    }

    public function testXpConvertSpecialChars103()
    {
        $input = "+A#SpecialChar";
        $expected = "`#efefef@SpecialChar";
        $this->assertEquals($expected, $this->xpParser103->convertXPColorCodes($input));
    }

    public function testXpConvertEmptyName()
    {
        $input = "";
        $expected = "`#efefef";
        $this->assertEquals($expected, $this->xpParser->convertColorCodes($input));
    }

    public function testXpConvertColorCodesWithHexColor()
    {
        $input = "^xFF0000Red";
        $expected = "`#FF0000Red";
        $this->assertEquals($expected, $this->xpParser->convertColorCodes($input));
    }

    public function testXpConvertAllListedColors()
    {
        $input = "^0Gray ^1Red ^2Green ^3Yellow ^4Blue ^5Cyan ^6Magenta ^7White";
        $expected = "`#555555Gray `#e90000Red `#00dd24Green `#f5d800Yellow `#2e61c8Blue `#16b4a5Cyan `#f408f1Magenta `#efefefWhite";
        $this->assertEquals($expected, $this->xpParser->convertColorCodes($input));
    }
}

// Mock classes for StatsAggregator and StatsProcessor
class StatsAggregator {}
class StatsProcessor {}
