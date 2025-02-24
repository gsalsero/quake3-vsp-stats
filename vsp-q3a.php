<?php
/**
 * VSPParserQ3A
 *
 * A parser for Quake 3 server logs.
 */
class VSPParserQ3A
{
  private array $config = [];
  private GameDataProcessor $gameDataProcessor;
  private PlayerSkillProcessor $playerSkillProcessor;
  private array $playerInfo = [];
  private array $miscStats = [];
  private array $translationData = [];
  private string $rawTimestamp = "";
  private array $baseTime = [];
  private bool $gameInProgress = false;
  /** @var resource|null */
  private $logFileHandle = null;
  private string $logFilePath = "";
  private array $logdata = [];
  private int $currentFilePosition = 0;
  private int $gameStartFilePosition = 0;
  private string $original_log = "";
  private array $has_acc_stats = [];

  // Class constant for log read size.
  private const LOG_READ_SIZE = 1024;

  // Constructor: initialize configuration, aggregator and processor.
  public function __construct(
    array $configData,
    GameDataProcessor $gameDataProcessor,
    PlayerSkillProcessor $playerSkillProcessor
  ) {
    $this->initializeConfig($configData);
    $this->gameDataProcessor = $gameDataProcessor;
    $this->playerSkillProcessor = $playerSkillProcessor;
    $this->miscStats = [];
    $this->translationData = [];
    $this->playerInfo = [];
    $this->logdata = [];
    $this->gameInProgress = false;
    // Initialize translation arrays for weapon name cleanup and character translations
    $this->translationData["weapon_name"]["search"] = ["/MOD_/", "/_SPLASH/"];
    $this->translationData["weapon_name"]["replace"] = ["", ""];
    $this->translationData["char_trans"] = [
      "^<" => "^4",
      "^>" => "^6",
      "^&" => "^6",
      "\x01" => "(",
      "\x02" => "▀",
      "\x03" => ")",
      "\x04" => "█",
      "\x05" => " ",
      "\x06" => "█",
      "\x07" => "(",
      "\x08" => "▄",
      "\x09" => ")",
      "\x0b" => "■", // note: duplicate key removed
      "\x0c" => " ",
      "\x0d" => "►",
      "\x0e" => "·",
      "\x0f" => "·",
      "\x10" => "[",
      "\x11" => "]",
      "\x12" => "|¯",
      "\x13" => "¯",
      "\x14" => "¯|",
      "\x15" => "|",
      "\x16" => " ",
      "\x17" => "|",
      "\x18" => "|_",
      "\x19" => "_",
      "\x1a" => "_|",
      "\x1b" => "¯",
      "\x1c" => "·",
      "\x1d" => "(",
      "\x1e" => "-",
      "\x1f" => ")",
      "\x7f" => "<-",
      "\x80" => "(",
      "\x81" => "=",
      "\x82" => ")",
      "\x83" => "|",
      "\x84" => " ",
      "\x85" => "·",
      "\x86" => "▼",
      "\x87" => "▲",
      "\x88" => "◄",
      "\x89" => " ",
      "\x8a" => " ",
      "\x8b" => "■",
      "\x8c" => " ",
      "\x8d" => "►",
      "\x8e" => "·",
      "\x8f" => "·",
      "\x90" => "[",
      "\x91" => "]",
      "\x92" => "0",
      "\x93" => "1",
      "\x94" => "2",
      "\x95" => "3",
      "\x96" => "4",
      "\x97" => "5",
      "\x98" => "6",
      "\x99" => "7",
      "\x9a" => "8",
      "\x9b" => "9",
      "\x9c" => "·",
      "\x9d" => "(",
      "\x9e" => "-",
      "\x9f" => ")",
      "\xa0" => " ",
      "\xa1" => "!",
      "\xa2" => "\"",
      "\xa3" => "#",
      "\xa4" => "$",
      "\xa5" => "%",
      "\xa6" => "&",
      "\xa7" => "'",
      "\xa8" => "(",
      "\xa9" => ")",
      "\xaa" => "*",
      "\xab" => "+",
      "\xac" => ",",
      "\xad" => "-",
      "\xae" => ".",
      "\xaf" => "/",
      "\xb0" => "0",
      "\xb1" => "1",
      "\xb2" => "2",
      "\xb3" => "3",
      "\xb4" => "4",
      "\xb5" => "5",
      "\xb6" => "6",
      "\xb7" => "7",
      "\xb8" => "8",
      "\xb9" => "9",
      "\xba" => ":",
      "\xbb" => ";",
      "\xbc" => "<",
      "\xbd" => "=",
      "\xbe" => ">",
      "\xbf" => "?",
      "\xc0" => "@",
      "\xc1" => "A",
      "\xc2" => "B",
      "\xc3" => "C",
      "\xc4" => "D",
      "\xc5" => "E",
      "\xc6" => "F",
      "\xc7" => "G",
      "\xc8" => "H",
      "\xc9" => "I",
      "\xca" => "J",
      "\xcb" => "K",
      "\xcc" => "L",
      "\xcd" => "M",
      "\xce" => "N",
      "\xcf" => "O",
      "\xd0" => "P",
      "\xd1" => "Q",
      "\xd2" => "R",
      "\xd3" => "S",
      "\xd4" => "T",
      "\xd5" => "U",
      "\xd6" => "V",
      "\xd7" => "W",
      "\xd8" => "X",
      "\xd9" => "Y",
      "\xda" => "Z",
      "\xdb" => "[",
      "\xdc" => "\\",
      "\xdd" => "]",
      "\xde" => "^",
      "\xdf" => "_",
      "\xe0" => "'",
      "\xe1" => "A",
      "\xe2" => "B",
      "\xe3" => "C",
      "\xe4" => "D",
      "\xe5" => "E",
      "\xe6" => "F",
      "\xe7" => "G",
      "\xe8" => "H",
      "\xe9" => "I",
      "\xea" => "J",
      "\xeb" => "K",
      "\xec" => "L",
      "\xed" => "M",
      "\xee" => "N",
      "\xef" => "O",
      "\xf0" => "P",
      "\xf1" => "Q",
      "\xf2" => "R",
      "\xf3" => "S",
      "\xf4" => "T",
      "\xf5" => "U",
      "\xf6" => "V",
      "\xf7" => "W",
      "\xf8" => "X",
      "\xf9" => "Y",
      "\xfa" => "Z",
      "\xfb" => "{",
      "\xfc" => "|",
      "\xfd" => "}",
      "\xfe" => "\"",
      "\xff" => "->",
    ];
  }

  // Initialize configuration from given data array.
  private function initializeConfig(array $configData): void
  {
    $this->config["savestate"] = 0;
    $this->config["gametype"] = "";
    $this->config["backuppath"] = "";
    $this->config["trackID"] = "playerName";
    // change: xp version for special chars
    $this->config["xp_version"] = 200;
    // Merge in passed config
    foreach ($configData as $key => $value) {
      $this->config[$key] = $value;
    }
    if (!empty($this->config["backuppath"])) {
      $this->config["backuppath"] = ensureTrailingSlash(
        $this->config["backuppath"]
      );
    }
    echo "[parser options]: ";
    print_r($this->config);
  }

  // Reset auxiliary variables for a new game session.
  private function resetSessionData(): void
  {
    $this->playerInfo = [];
    $this->miscStats = [];
    $this->baseTime = [
      "month" => 12,
      "date" => 28,
      "year" => 1971,
      "hour" => 23,
      "min" => 59,
      "sec" => 59,
    ];
  }

  // Write shutdown savestate information – unused.
  private function saveShutdownState(): void
  {
    $this->logdata["last_shutdown_end_position"] = ftell($this->logFileHandle);
    $seekResult = fseek($this->logFileHandle, -self::LOG_READ_SIZE, SEEK_CUR);
    if ($seekResult === 0) {
      $this->logdata["last_shutdown_hash"] = md5(
        fread($this->logFileHandle, self::LOG_READ_SIZE)
      );
    } else {
      $currentPos = ftell($this->logFileHandle);
      fseek($this->logFileHandle, 0);
      $this->logdata["last_shutdown_hash"] = md5(
        fread($this->logFileHandle, $currentPos)
      );
    }
    $savestateFile = fopen(
      "./logdata/savestate_" .
        sanitizeFilename($this->logFilePath) .
        ".inc.php",
      "wb"
    );
    fwrite($savestateFile, "<?php /* DO NOT EDIT THIS FILE! */\n");
    fwrite(
      $savestateFile,
      "\$this->logdata['last_shutdown_hash']='{$this->logdata["last_shutdown_hash"]}';\n"
    );
    fwrite(
      $savestateFile,
      "\$this->logdata['last_shutdown_end_position']={$this->logdata["last_shutdown_end_position"]};\n"
    );
    fwrite($savestateFile, "?>");
    fclose($savestateFile);
  }

  // Verify savestate (unused).
  private function verifySavestate(): void
  {
    echo "Verifying savestate\n";
    $fileHandle = fopen($this->logFilePath, "rb");
    fseek($fileHandle, $this->logdata["last_shutdown_end_position"]);
    $seekBack = fseek($fileHandle, -self::LOG_READ_SIZE, SEEK_CUR);
    if ($seekBack === 0) {
      $hashBlock = fread($fileHandle, self::LOG_READ_SIZE);
    } else {
      $curPos = ftell($fileHandle);
      fseek($fileHandle, 0);
      $hashBlock = fread($fileHandle, $curPos);
    }
    if (strcmp(md5($hashBlock), $this->logdata["last_shutdown_hash"]) === 0) {
      echo " - Hash matched, resuming parsing from previous saved location in log file\n";
      fseek($this->logFileHandle, $this->logdata["last_shutdown_end_position"]);
    } else {
      echo " - Hash did not match, assuming new log file\n";
      fseek($this->logFileHandle, 0);
    }
    fclose($fileHandle);
  }

  // Open and process the log file.
  public function processLogFile(string $logFileName): void
  {
    echo "\nProcessing log file: {$logFileName}\n";
    $this->logFilePath = realpath($logFileName);
    if (!file_exists($this->logFilePath)) {
      errorAndExit("error: log file \"{$logFileName}\" does not exist");
    }

    // change: excessiveplus 1.03 fix
    $this->original_log = $this->logFilePath;
    if (
      $this->config["gametype"] === "xp" &&
      $this->config["xp_version"] == 103
    ) {
      require "xp103_compat.inc.php";
      $this->logFilePath = repair_xp_logfile(
        $this->logFilePath,
        $this->config["savestate"] == 1
      );
    }
    // endchange

    $this->resetSessionData();
    if ($this->config["savestate"] == 1) {
      echo "savestate 1 processing enabled\n";
      global $db;
      $sql =
        "SELECT `value` FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}savestate WHERE `logfile` = " .
        $db->qstr($this->original_log);
      $rs = $db->Execute($sql);
      if ($rs && !$rs->EOF) {
        eval($rs->fields[0]);
      }
      $this->logFileHandle = fopen($this->logFilePath, "rb");
      if (!empty($this->logdata)) {
        check_savestate($this);
      }
    } else {
      $this->logFileHandle = fopen($this->logFilePath, "rb");
    }
    if (!$this->logFileHandle) {
      debugPrint("error: {$this->logFilePath} could not be opened");
      return;
    }
    $this->translationData["logfile_size"] = filesize($this->logFilePath);
    while (!feof($this->logFileHandle)) {
      $this->currentFilePosition = ftell($this->logFileHandle);
      $line = fgets($this->logFileHandle, cBIG_STRING_LENGTH);
      $line = rtrim($line, "\r\n");
      $this->processLogLine($line);
    }
    fclose($this->logFileHandle);
    if (
      isset($this->original_log) &&
      function_exists("remove_xp_tmp_logfile")
    ) {
      remove_xp_tmp_logfile($this->logFilePath);
    }
  }

  // Remove color codes from a string.
  private function removeColorCodes(string $str): string
  {
    $cleanStr = preg_replace("/\\^[xX][\da-fA-F]{6}/", "", $str);
    $cleanStr = preg_replace("/\\^[^\\^]/", "", $cleanStr);
    return $cleanStr;
  }

  // Convert XP-specific color codes.
  private function convertXPColorCodes(string $str): string
  {
    if ($this->config["xp_version"] <= 103) {
      // Use preg_replace_callback instead of deprecated /e modifier
      $str = preg_replace_callback(
        "/\+([\x01-\x7F])#/",
        function ($matches) {
          return chr(ord($matches[1]) + 127);
        },
        $str
      );
    } else {
      $str = preg_replace_callback(
        "/#(#|[0-9a-f]{2})/i",
        function ($matches) {
          return $matches[1] === "#" ? "#" : chr(hexdec($matches[1]));
        },
        $str
      );
    }
    $defaultColors = [
      "#555555",
      "#e90000",
      "#00dd24",
      "#f5d800",
      "#2e61c8",
      "#16b4a5",
      "#f408f1",
      "#efefef",
      "#ebbc1b",
    ];
    $tmp = ["\xde" => "^"];
    $str = strtr(
      $str,
      array_diff_assoc($this->translationData["char_trans"], $tmp)
    );
    if ($str[0] !== "^") {
      $str = "^7" . $str;
    }
    $str = preg_replace("/\^(a[1-9]|[fFrRbBl])/", "", $str);
    $str = preg_replace("/\^s(\^x[a-fA-F0-9]{6}|\^[^\^])/", "\\1", $str);
    $str = preg_replace("/\^s/", "^7", $str);
    $str = preg_replace_callback(
      "/(\^(x[a-fA-F0-9]{6}|[^\^]))\^(x[a-fA-F0-9]{6}|[^\^])/",
      function ($matches) {
        return $matches[1];
      },
      $str
    );
    $str = preg_replace("/\^x([a-fA-F0-9]{6})/i", "`#$1", $str);
    $str = preg_replace_callback(
      "/\^([^\^<])/",
      function ($matches) use ($defaultColors) {
        return "`" . $defaultColors[ord($matches[1]) % 8];
      },
      $str
    );
    $str = strtr($str, $tmp);
    return $str;
  }

  // Convert color codes (delegates to XP conversion if gametype is xp).
  private function convertColorCodes(string $str): string
  {
    if ($this->config["gametype"] === "xp") {
      $newStr = $this->convertXPColorCodes($str);
      if (!empty($newStr)) {
        return $newStr;
      }
    }
    $str = strtr($str, $this->translationData["char_trans"]);
    $enableColor = true;
    $strLength = strlen($str);
    if ($strLength < 1) {
      return " ";
    }
    $resultStr = $enableColor ? "`#FFFFFF" : "";
    for ($i = 0; $i < $strLength - 1; $i++) {
      if ($str[$i] === "^" && $str[$i + 1] !== "^") {
        $charCode = ord($str[$i + 1]);
        if ($enableColor) {
          if (in_array($charCode, [70, 102, 66, 98, 78], true)) {
            $i++;
            continue;
          }
          if (
            ($charCode === 88 || $charCode === 120) &&
            strlen($str) - $i > 6
          ) {
            if (preg_match("/^[\da-fA-F]{6}/", substr($str, $i + 2, 6))) {
              $resultStr .= "`#" . substr($str, $i + 2, 6);
              $i += 7;
              continue;
            }
          }
          switch ($charCode % 8) {
            case 0:
              $resultStr .= "`#777777";
              break;
            case 1:
              $resultStr .= "`#FF0000";
              break;
            case 2:
              $resultStr .= "`#00FF00";
              break;
            case 3:
              $resultStr .= "`#FFFF00";
              break;
            case 4:
              $resultStr .= "`#4444FF";
              break;
            case 5:
              $resultStr .= "`#00FFFF";
              break;
            case 6:
              $resultStr .= "`#FF00FF";
              break;
            case 7:
              $resultStr .= "`#FFFFFF";
              break;
          }
        }
        $i++;
      } else {
        $resultStr .= $str[$i];
      }
    }
    if ($i < $strLength) {
      $resultStr .= $str[$i];
    }
    return $resultStr;
  }

  // Lookup a player by matching the given id.
  private function lookupPlayerById(string $playerId): string
  {
    foreach ($this->playerInfo as $key => $pInfo) {
      if ($this->playerInfo[$key]["id"] === $playerId) {
        return $key;
      }
    }
    return "";
  }

  // Lookup a player by matching the given name.
  private function lookupPlayerByName(string $name): string
  {
    foreach ($this->playerInfo as $key => $pInfo) {
      if ($this->playerInfo[$key]["name"] === $name) {
        return $key;
      }
    }
    return "";
  }

  // Generate a formatted timestamp.
  private function generateTimestamp(): string
  {
    if (
      preg_match(
        "/^(\d+)[\\:\\.](\d+)[\\:\\.](\d+)/",
        $this->rawTimestamp,
        $match
      )
    ) {
      $timeParts = [
        "hour" => $match[1],
        "min" => $match[2],
        "sec" => $match[3],
      ];
      return date(
        "Y-m-d H:i:s",
        adodb_mktime(
          $timeParts["hour"],
          $timeParts["min"],
          $timeParts["sec"],
          $this->baseTime["month"],
          $this->baseTime["date"],
          $this->baseTime["year"]
        )
      );
    } elseif (preg_match("/^(\d+):(\d+)/", $this->rawTimestamp, $match)) {
      $timeParts = [
        "min" => $match[1],
        "sec" => $match[2],
      ];
      return date(
        "Y-m-d H:i:s",
        adodb_mktime(
          $this->baseTime["hour"],
          $this->baseTime["min"] + $timeParts["min"],
          $this->baseTime["sec"] + $timeParts["sec"],
          $this->baseTime["month"],
          $this->baseTime["date"],
          $this->baseTime["year"]
        )
      );
    } elseif (preg_match("/^(\d+).(\d+)/", $this->rawTimestamp, $match)) {
      $timeParts = ["min" => 0, "sec" => $match[1]];
      return date(
        "Y-m-d H:i:s",
        adodb_mktime(
          $this->baseTime["hour"],
          $this->baseTime["min"],
          $this->baseTime["sec"] + $timeParts["sec"],
          $this->baseTime["month"],
          $this->baseTime["date"],
          $this->baseTime["year"]
        )
      );
    }
    return "";
  }

  // Process server time line.
  private function processServerTime(string &$line): bool
  {
    if (
      !preg_match(
        "/^ServerTime:\s+(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})\s+/",
        $line,
        $match
      )
    ) {
      return false;
    }
    $this->baseTime = [
      "year" => $match[1],
      "month" => $match[2],
      "date" => $match[3],
      "hour" => $match[4],
      "min" => $match[5],
      "sec" => $match[6],
    ];
    $this->playerSkillProcessor->setGameData(
      "_v_time_start",
      date(
        "Y-m-d H:i:s",
        adodb_mktime(
          $this->baseTime["hour"],
          $this->baseTime["min"],
          $this->baseTime["sec"],
          $this->baseTime["month"],
          $this->baseTime["date"],
          $this->baseTime["year"]
        )
      )
    );
    return true;
  }

  // Process game initialization.
  private function processGameInit(string &$line): bool
  {
    if (!preg_match("/^InitGame: (.*)/", $line, $match)) {
      return false;
    }
    if ($this->gameInProgress) {
      debugPrint("corrupt game (no Shutdown after Init), ignored\n");
      debugPrint("{$this->rawTimestamp} $line\n");
      $this->playerSkillProcessor->updatePlayerStreaks();
      $this->playerSkillProcessor->clearProcessorData();
    }
    $this->gameInProgress = true;
    $this->gameStartFilePosition = $this->currentFilePosition;
    $this->resetSessionData();
    $serverVars = $match[1];
    $serverVarsArray = [];
    while (
      preg_match("/^\\\(.+)\\\(.+)\\\/U", $serverVars, $varMatch) ||
      preg_match("/^\\\(.+)\\\(.+)/", $serverVars, $varMatch)
    ) {
      $varName = $varMatch[1];
      $varValue = $varMatch[2];
      $serverVars = substr(
        $serverVars,
        strlen($varName) + strlen($varValue) + 2
      );
      if (
        $varName === "gamestartup" &&
        preg_match(
          "/^(\d+)[-\/](\d+)[-\/](\d+) +(\d+)[:-](\d+)[:-](\d+)/",
          $varValue,
          $timeMatch
        )
      ) {
        $this->baseTime = [
          "month" => $timeMatch[1],
          "date" => $timeMatch[2],
          "year" => $timeMatch[3],
          "hour" => $timeMatch[4],
          "min" => $timeMatch[5],
          "sec" => $timeMatch[6],
        ];
      }
      $serverVarsArray[$varName] = $varValue;
    }
    $this->playerSkillProcessor->startGameAnalysis();
    foreach ($serverVarsArray as $key => $value) {
      $this->playerSkillProcessor->setGameData($key, $value);
    }
    $this->playerSkillProcessor->setGameData(
      "_v_time_start",
      $this->generateTimestamp()
    );
    $this->playerSkillProcessor->setGameData(
      "_v_map",
      $serverVarsArray["mapname"]
    );
    $this->playerSkillProcessor->setGameData("_v_game", "q3a");
    $this->playerSkillProcessor->setGameData(
      "_v_mod",
      $serverVarsArray["gamename"]
    );
    $this->playerSkillProcessor->setGameData(
      "_v_game_type",
      $serverVarsArray["g_gametype"]
    );
    $this->translationData["mod"] = $serverVarsArray["gamename"];
    $this->translationData["gametype"] = $serverVarsArray["g_gametype"];
    $this->translationData["gameversion"] =
      $serverVarsArray["xp_version"] ?? $serverVarsArray["gameversion"];
    return true;
  }

  // Process client userinfo change.
  private function processClientUserinfoChanged(string &$line): bool
  {
    if (!preg_match("/^ClientUserinfoChanged: (\d+) (.*)/", $line, $match)) {
      return false;
    }
    $clientId = $match[1];
    $vars = $match[2];
    while (
      preg_match("/^(.+)\\\(.*)\\\/U", $vars, $varMatch) ||
      preg_match("/^(.+)\\\(.*)/", $vars, $varMatch)
    ) {
      $varName = $varMatch[1];
      $varValue = $varMatch[2];
      $vars = substr($vars, strlen($varName) + strlen($varValue) + 2);
      if ($varName === "n") {
        $newName = $this->convertColorCodes($varValue);
        if (
          isset($this->playerInfo[$clientId]["id"]) &&
          $this->config["trackID"] === "playerName" &&
          $this->playerInfo[$clientId]["id"] !== $varValue
        ) {
          $this->playerSkillProcessor->updatePlayerDataField(
            "sto",
            $this->playerInfo[$clientId]["id"],
            "alias",
            $newName
          );
          $this->playerSkillProcessor->updatePlayerName(
            $this->playerInfo[$clientId]["id"],
            $newName
          );
          $this->playerSkillProcessor->resolvePlayerIDConflict(
            $this->playerInfo[$clientId]["id"],
            $varValue
          );
          $this->playerInfo[$clientId]["id"] = $varValue;
        } elseif (
          isset(
            $this->playerInfo[$clientId]["id"],
            $this->playerInfo[$clientId]["name"]
          ) &&
          $this->playerInfo[$clientId]["name"] !== $newName
        ) {
          $this->playerSkillProcessor->updatePlayerDataField(
            "sto",
            $this->playerInfo[$clientId]["id"],
            "alias",
            $newName
          );
          $this->playerSkillProcessor->updatePlayerName(
            $this->playerInfo[$clientId]["id"],
            $newName
          );
        } elseif ($this->config["trackID"] === "playerName") {
          $this->playerInfo[$clientId]["id"] = $varValue;
        } elseif (
          $this->config["trackID"] === "guid" &&
          isset($this->playerInfo[$clientId]["guid"])
        ) {
          $this->playerInfo[$clientId]["id"] =
            $this->playerInfo[$clientId]["guid"];
        } elseif (
          preg_match("/^ip=(.+)/i", $this->config["trackID"], $tmpMatch) &&
          isset($this->playerInfo[$clientId]["ip"]) &&
          preg_match($tmpMatch[1], $this->playerInfo[$clientId]["ip"], $ipMatch)
        ) {
          $this->playerInfo[$clientId]["id"] = $ipMatch[1];
        } else {
          debugPrint("\$cfg['parser']['trackID'] is invalid, ignored\n");
          debugPrint(
            "Use \$cfg['parser']['trackID'] = 'playerName'; in your config\n"
          );
          debugPrint("{$this->rawTimestamp} $line\n");
          $this->playerSkillProcessor->clearProcessorData();
          $this->gameInProgress = false;
          return true;
        }
        $this->playerInfo[$clientId]["name"] = $newName;
      } elseif ($varName === "t") {
        $this->playerInfo[$clientId]["team"] = $varValue;
        if ($this->playerInfo[$clientId]["team"] !== "3") {
          if (!isset($this->playerSkillProcessor->players_team)) {
            $this->playerSkillProcessor->players_team = [];
            $this->has_acc_stats = [];
          }
          $this->playerSkillProcessor->players_team[$clientId] = [
            "team" => $this->playerInfo[$clientId]["team"],
            "connected" => true,
          ];
          $this->has_acc_stats[$clientId] = false;
          $this->playerSkillProcessor->updatePlayerTeam(
            $this->playerInfo[$clientId]["id"],
            $this->playerInfo[$clientId]["team"]
          );
        }
      } elseif ($varName === "model") {
        if ($this->playerInfo[$clientId]["team"] !== "3") {
          if (
            !isset($this->playerInfo[$clientId]["icon"]) ||
            $this->playerInfo[$clientId]["icon"] !== $varValue
          ) {
            $this->playerInfo[$clientId]["icon"] = $varValue;
          }
        }
      }
    }
    return true;
  }

  // Process client begin event.
  private function processClientBegin(string &$line): bool
  {
    if (!preg_match("/^ClientBegin: (\d+)/", $line, $match)) {
      return false;
    }
    $clientId = $match[1];
    if (isset($this->playerInfo[$clientId]["id"])) {
      if ($this->playerInfo[$clientId]["team"] !== "3") {
        if (isset($this->playerInfo[$clientId]["name"])) {
          $ip = $this->playerInfo[$clientId]["ip"] ?? null;
          $tld =
            $this->playerInfo[$clientId]["rtld"] ??
            ($this->playerInfo[$clientId]["tld"] ?? null);
          $this->playerSkillProcessor->initializePlayerData(
            $this->playerInfo[$clientId]["id"],
            $this->playerInfo[$clientId]["name"],
            $ip,
            $tld
          );
        }
        if (isset($this->playerInfo[$clientId]["team"])) {
          $this->playerSkillProcessor->updatePlayerTeam(
            $this->playerInfo[$clientId]["id"],
            $this->playerInfo[$clientId]["team"]
          );
        }
        if (isset($this->playerInfo[$clientId]["role"])) {
          $this->playerSkillProcessor->setPlayerRole(
            $this->playerInfo[$clientId]["id"],
            $this->playerInfo[$clientId]["role"]
          );
        }
        if (isset($this->playerInfo[$clientId]["icon"])) {
          $this->playerSkillProcessor->updatePlayerDataField(
            "sto",
            $this->playerInfo[$clientId]["id"],
            "icon",
            $this->playerInfo[$clientId]["icon"]
          );
        }
        if (isset($this->playerInfo[$clientId]["ip"])) {
          $this->playerSkillProcessor->updatePlayerDataField(
            "sto",
            $this->playerInfo[$clientId]["id"],
            "ip",
            $this->playerInfo[$clientId]["ip"]
          );
        }
        if (isset($this->playerInfo[$clientId]["guid"])) {
          $this->playerSkillProcessor->updatePlayerDataField(
            "sto",
            $this->playerInfo[$clientId]["id"],
            "guid",
            $this->playerInfo[$clientId]["guid"]
          );
        }
        if (isset($this->playerInfo[$clientId]["tld"])) {
          $this->playerSkillProcessor->updatePlayerDataField(
            "sto",
            $this->playerInfo[$clientId]["id"],
            "tld",
            $this->playerInfo[$clientId]["rtld"] ??
              $this->playerInfo[$clientId]["tld"]
          );
        }
      }
    }
    return true;
  }

  // Process kill event.
  private function processKillEvent(string &$line): bool
  {
    if (
      !preg_match(
        "/^Kill: (\d+) (\d+) \d+: (.*) killed (.*) by (\w+)/",
        $line,
        $match
      )
    ) {
      return false;
    }
    $attacker = $match[1];
    $victim = $match[2];
    $weapon = $match[5];
    if ($attacker > 128) {
      $attacker = $victim;
    }
    $weapon = preg_replace(
      $this->translationData["weapon_name"]["search"],
      $this->translationData["weapon_name"]["replace"],
      $weapon
    );
    if (
      isset($this->playerInfo[$attacker]["id"]) &&
      isset($this->playerInfo[$victim]["id"])
    ) {
      if (isset($this->playerSkillProcessor->players_team)) {
        $this->playerSkillProcessor->processKillEvent(
          $attacker,
          $victim,
          $weapon,
          $this->playerInfo
        );
      } else {
        $this->playerSkillProcessor->processKillEvent(
          $this->playerInfo[$attacker]["id"],
          $victim,
          $weapon
        );
      }
    }
    return true;
  }

  // Process item pickup event.
  private function processItemPickup(string &$line): bool
  {
    if (!preg_match("/^Item: (\d+) (.*)/", $line, $match)) {
      return false;
    }
    $clientId = $match[1];
    $item = $match[2];
    $item = preg_replace("/ammo_/", "ammo|", $item, 1);
    $item = preg_replace("/weapon_/", "weapon|", $item, 1);
    $item = preg_replace("/item_/", "item|", $item, 1);
    if (isset($this->playerInfo[$clientId]["id"])) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $clientId,
        $item,
        1,
        $this->playerInfo
      );
    }
    return true;
  }

  // Process client chat message.
  private function processClientChat(string &$line): bool
  {
    if (!preg_match("/^say: (.+): /U", $line, $match)) {
      return false;
    }
    $namePart = $match[1];
    $chatMsg = substr($line, strlen($match[0]));
    $clientKey = $this->lookupPlayerByName($this->convertColorCodes($namePart));
    if (strlen($clientKey) > 0) {
      $chatMsg = $this->removeColorCodes($chatMsg);
      $this->playerSkillProcessor->updatePlayerDataField(
        "sto_glo",
        $this->playerInfo[$clientKey]["id"],
        "chat",
        $chatMsg
      );
      $this->playerSkillProcessor->updatePlayerQuote(
        $this->playerInfo[$clientKey]["id"],
        $chatMsg
      );
    }
    return true;
  }

  // Process client connect event.
  private function processClientConnect(string &$line): bool
  {
    if (!preg_match("/^ClientConnect: (\d+)/", $line, $match)) {
      return false;
    }
    $clientId = $match[1];
    if (isset($this->playerInfo[$clientId])) {
      unset($this->playerInfo[$clientId]);
    }
    return true;
  }

  // Process client disconnect event.
  private function processClientDisconnect(string &$line): bool
  {
    if (!preg_match("/^ClientDisconnect: (\d+)/", $line, $match)) {
      return false;
    }
    if (isset($this->playerSkillProcessor->players_team)) {
      $this->playerSkillProcessor->players_team[$match[1]]["connected"] = false;
    }
    return true;
  }

  // Process game shutdown.
  private function processGameShutdown(string &$line): bool
  {
    if (!preg_match("/^ShutdownGame:/", $line, $match)) {
      return false;
    }
    if ($this->config["savestate"] == 1) {
      save_savestate($this);
    }
    $this->playerSkillProcessor->updatePlayerStreaks();
    $this->playerSkillProcessor->launch_skill_events();
    $this->gameDataProcessor->storeGameData(
      $this->playerSkillProcessor->getPlayerStats(),
      $this->playerSkillProcessor->getGameData()
    );
    $this->playerSkillProcessor->clearProcessorData();
    $this->gameInProgress = false;
    return true;
  }

  // Process warmup event.
  private function processWarmup(string &$line): bool
  {
    if (!preg_match("/^Warmup:/", $line, $match)) {
      return false;
    }
    debugPrint("warmup game, ignored\n");
    $this->playerSkillProcessor->clearProcessorData();
    $this->gameInProgress = false;
    return true;
  }

  // Process team score line.
  private function processTeamScoreLine(string &$line): bool
  {
    if (!preg_match("/^red:(\d+)\s*blue:(\d+)/", $line, $match)) {
      return false;
    }
    $tmp = $GLOBALS["skillset"]["event"]["team|score"];
    if (
      !(
        $this->config["gametype"] === "xp" &&
        in_array($this->translationData["gametype"], [
          "4",
          "5",
          "6",
          "7",
          "8",
          "9",
        ])
      )
    ) {
      $GLOBALS["skillset"]["event"]["team|score"] = 0.0;
    }
    $this->playerSkillProcessor->updateTeamEventSkill(
      "1",
      "team|score",
      $match[1]
    );
    $this->playerSkillProcessor->updateTeamEventSkill(
      "2",
      "team|score",
      $match[2]
    );
    $GLOBALS["skillset"]["event"]["team|score"] = $tmp;
    if (intval($match[1]) > intval($match[2])) {
      $this->playerSkillProcessor->updateTeamEventSkill("1", "team|wins", 1);
      $this->playerSkillProcessor->updateTeamEventSkill("2", "team|loss", 1);
    } elseif (intval($match[1]) < intval($match[2])) {
      $this->playerSkillProcessor->updateTeamEventSkill("1", "team|loss", 1);
      $this->playerSkillProcessor->updateTeamEventSkill("2", "team|wins", 1);
    }
    return true;
  }

  // Process player score at game end.
  private function processPlayerScore(string &$line): bool
  {
    if (
      !preg_match(
        "/^score: (-?\d+)\s+ping: (\d+)\s+client: (\d+)/",
        $line,
        $match
      )
    ) {
      return false;
    }
    $score = $match[1];
    $ping = $match[2];
    $clientId = $match[3];
    if (isset($this->playerInfo[$clientId])) {
      if (isset($this->playerSkillProcessor->players_team)) {
        $this->playerSkillProcessor->updatePlayerEvent(
          $clientId,
          "score",
          $score,
          $this->playerInfo
        );
      } else {
        $this->playerSkillProcessor->updatePlayerEvent(
          $this->playerInfo[$clientId]["id"],
          "score",
          $score
        );
      }
      $this->playerSkillProcessor->updatePlayerDataField(
        "avg",
        $this->playerInfo[$clientId]["id"],
        "ping",
        $ping
      );
    }
    return true;
  }

  // Process CTF awards.
  private function processCTFAwards(string &$line): bool
  {
    if (preg_match("/^AWARD_FlagRecovery: (\d+)/", $line, $match)) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Flag_Return",
        1
      );
      return true;
    } elseif (preg_match("/^AWARD_FlagSteal: (\d+)/", $line, $match)) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Flag_Pickup",
        1
      );
      return true;
    } elseif (preg_match("/^AWARD_CarrierKill: (\d+)/", $line, $match)) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Kill_Carrier",
        1
      );
      return true;
    } elseif (
      preg_match("/^AWARD_CarrierDangerProtect: (\d+)/", $line, $match)
    ) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Defend_Hurt_Carrier",
        1
      );
      return true;
    } elseif (preg_match("/^AWARD_CarrierProtection: (\d+)/", $line, $match)) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Defend_Carrier",
        1
      );
      return true;
    } elseif (preg_match("/^AWARD_FlagDefense: (\d+)/", $line, $match)) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Defend_Flag",
        1
      );
      return true;
    } elseif (
      preg_match("/^AWARD_FlagCarrierKillAssist: (\d+)/", $line, $match)
    ) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Flag_Assist_Frag",
        1
      );
      return true;
    } elseif (preg_match("/^AWARD_FlagCaptureAssist: (\d+)/", $line, $match)) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Flag_Assist_Return",
        1
      );
      return true;
    } elseif (preg_match("/^AWARD_FlagCapture: (\d+)/", $line, $match)) {
      $this->playerSkillProcessor->updatePlayerEvent(
        $this->playerInfo[$match[1]]["id"],
        "CTF|Flag_Capture",
        1
      );
      return true;
    }
    return false;
  }

  // Process team scores and finalize game stats.
  private function processTeamScoreAndGameEnd(string &$line): bool
  {
    if (preg_match("/^processStatsGameTypesOSPClanArena_EndGame/", $line)) {
      if (isset($this->miscStats["score"])) {
        foreach ($this->miscStats["score"] as $clientKey => $score) {
          if (isset($this->playerInfo[$clientKey])) {
            $this->playerSkillProcessor->updatePlayerEvent(
              $this->playerInfo[$clientKey]["id"],
              "score",
              $score
            );
          }
        }
      }
      if (
        isset(
          $this->miscStats["team_score"]["red"],
          $this->miscStats["team_score"]["blue"]
        )
      ) {
        $this->playerSkillProcessor->updateTeamEventSkill(
          "1",
          "team|score",
          $this->miscStats["team_score"]["red"]
        );
        $this->playerSkillProcessor->updateTeamEventSkill(
          "2",
          "team|score",
          $this->miscStats["team_score"]["blue"]
        );
        if (
          intval($this->miscStats["team_score"]["red"]) >
          intval($this->miscStats["team_score"]["blue"])
        ) {
          $this->playerSkillProcessor->updateTeamEventSkill(
            "1",
            "team|wins",
            1
          );
          $this->playerSkillProcessor->updateTeamEventSkill(
            "2",
            "team|loss",
            1
          );
        } elseif (
          intval($this->miscStats["team_score"]["red"]) <
          intval($this->miscStats["team_score"]["blue"])
        ) {
          $this->playerSkillProcessor->updateTeamEventSkill(
            "1",
            "team|loss",
            1
          );
          $this->playerSkillProcessor->updateTeamEventSkill(
            "2",
            "team|wins",
            1
          );
        }
      }
      return true;
    } elseif (preg_match("/^Warmup:/", $line)) {
      return true;
    } elseif (preg_match("/^red:(\d+)\s*blue:(\d+)/", $line, $match)) {
      $this->miscStats["team_score"]["red"] = $match[1];
      $this->miscStats["team_score"]["blue"] = $match[2];
      return true;
    } elseif (
      preg_match(
        "/^score: (-?\d+)\s+ping: (\d+)\s+client: (\d+)/",
        $line,
        $match
      )
    ) {
      $clientKey = $match[3];
      $this->miscStats["score"][$clientKey] = $match[1];
      $ping = $match[2];
      if (isset($this->playerInfo[$clientKey])) {
        $this->playerSkillProcessor->updatePlayerDataField(
          "avg",
          $this->playerInfo[$clientKey]["id"],
          "ping",
          $ping
        );
      }
      return true;
    } elseif (preg_match("/^Game_End:/", $line)) {
      $dummy = "processStatsGameTypesOSPClanArena_EndGame";
      $this->processTeamScoreAndGameEnd($dummy);
      while (!feof($this->logFileHandle)) {
        $curPos = ftell($this->logFileHandle);
        $nextLine = fgets($this->logFileHandle, cBIG_STRING_LENGTH);
        $nextLine = rtrim($nextLine, "\r\n");
        $this->extractTimestamp($nextLine);
        if (preg_match("/^ShutdownGame:/", $nextLine)) {
          $dummyShutdown = "ShutdownGame:";
          $this->processGameShutdown($dummyShutdown);
          return true;
        } elseif (preg_match("/^InitGame:/", $nextLine)) {
          fseek($this->logFileHandle, $curPos);
          return true;
        }
      }
      return true;
    } elseif (preg_match("/^ShutdownGame:/", $line, $match)) {
      $startPos = ftell($this->logFileHandle);
      while (!feof($this->logFileHandle)) {
        $nextLine = fgets($this->logFileHandle, cBIG_STRING_LENGTH);
        $curPos = ftell($this->logFileHandle);
        $nextLine = rtrim($nextLine, "\r\n");
        $this->extractTimestamp($nextLine);
        if (preg_match("/^InitGame:/", $nextLine)) {
          if (
            preg_match("/Score_Time\\\EndOfMatch/", $nextLine) ||
            preg_match("/Score_Time\\\Round 1\\//", $nextLine) ||
            preg_match("/g_gametype\\\[^5]/", $nextLine)
          ) {
            $dummy = "processStatsGameTypesOSPClanArena_EndGame";
            $this->processLogLine($dummy);
            fseek($this->logFileHandle, $startPos);
            return false;
          } else {
            fseek($this->logFileHandle, $curPos);
            return true;
          }
        }
      }
      return true;
    }
    return false;
  }

  // Process threewave events.
  private function processThreewaveEvent(string &$line): bool
  {
    if (
      (stristr($this->translationData["mod"], "osp") ||
        stristr($this->translationData["gameversion"], "osp")) &&
      $this->translationData["gametype"] === "5"
    ) {
      if ($this->processTeamScoreAndGameEnd($line)) {
        return true;
      }
    }
    $events = [
      "Flag_Return",
      "Flag_Pickup",
      "Kill_Carrier",
      "Defend_Hurt_Carrier",
      "Hurt_Carrier_Defend",
      "Defend_Carrier",
      "Defend_Base",
      "Defend_Flag",
      "Flag_Assist_Frag",
      "Flag_Assist_Return",
      "Flag_Capture",
    ];
    foreach ($events as $event) {
      if (preg_match("/^{$event}: (\d+)/", $line, $match)) {
        if (isset($this->playerSkillProcessor->players_team)) {
          $this->playerSkillProcessor->updatePlayerEvent(
            $match[1],
            "CTF|{$event}",
            1,
            $this->playerInfo
          );
        } else {
          $this->playerSkillProcessor->updatePlayerEvent(
            $this->playerInfo[$match[1]]["id"],
            "CTF|{$event}",
            1
          );
        }
        return true;
      }
    }
    if (preg_match("/^Weapon_Stats: (\d+) (.*)/", $line, $match)) {
      $clientId = $match[1];
      $stats = $match[2];
      if (
        isset($this->playerSkillProcessor->players_team) &&
        @$this->has_acc_stats[$clientId]
      ) {
        return true;
      }
      while (preg_match("/^(.+):(\d+):(\d+)(:\d+:\d+)* /U", $stats, $sMatch)) {
        $weaponName = $sMatch[1];
        if (isset($sMatch[4])) {
          $shots = $sMatch[2];
          $hits = $sMatch[3];
        } else {
          if ($sMatch[2] > $sMatch[3]) {
            $shots = $sMatch[2];
            $hits = $sMatch[3];
          } else {
            $shots = $sMatch[3];
            $hits = $sMatch[2];
          }
        }
        $stats = substr($stats, strlen($sMatch[0]));
        if ($weaponName === "Gauntlet" || $weaponName === "G") {
          $weaponName = "GAUNTLET";
        } elseif (
          $weaponName === "MachineGun" ||
          $weaponName === "Machinegun" ||
          $weaponName === "MG"
        ) {
          $weaponName = "MACHINEGUN";
        } elseif ($weaponName === "Shotgun" || $weaponName === "SG") {
          $weaponName = "SHOTGUN";
        } elseif ($weaponName === "G.Launcher" || $weaponName === "GL") {
          $weaponName = "GRENADE";
        } elseif ($weaponName === "R.Launcher" || $weaponName === "RL") {
          $weaponName = "ROCKET";
        } elseif (
          $weaponName === "LightningGun" ||
          $weaponName === "Lightning" ||
          $weaponName === "LG"
        ) {
          $weaponName = "LIGHTNING";
        } elseif ($weaponName === "Railgun" || $weaponName === "RG") {
          $weaponName = "RAILGUN";
        } elseif ($weaponName === "Plasmagun" || $weaponName === "PG") {
          $weaponName = "PLASMA";
        } elseif ($weaponName === "Hook") {
          $weaponName = "GRAPPLE";
        } else {
          $weaponName = preg_replace("/^MOD_/", "", $weaponName);
        }
        if ($shots > 0) {
          if (isset($this->playerSkillProcessor->players_team)) {
            $this->playerSkillProcessor->updateAccuracyEvent(
              $clientId,
              $clientId,
              "accuracy|{$weaponName}_hits",
              $hits,
              $this->playerInfo
            );
            $this->playerSkillProcessor->updateAccuracyEvent(
              $clientId,
              $clientId,
              "accuracy|{$weaponName}_shots",
              $shots,
              $this->playerInfo
            );
          } else {
            $this->playerSkillProcessor->updateAccuracyEvent(
              $this->playerInfo[$clientId]["id"],
              $this->playerInfo[$clientId]["id"],
              "accuracy|{$weaponName}_hits",
              $hits
            );
            $this->playerSkillProcessor->updateAccuracyEvent(
              $this->playerInfo[$clientId]["id"],
              $this->playerInfo[$clientId]["id"],
              "accuracy|{$weaponName}_shots",
              $shots
            );
          }
        }
      }
      while (preg_match("/^(.+):(\d+)( |$)/U", $stats, $sMatch)) {
        $statName = $sMatch[1];
        $statVal = $sMatch[2];
        if (($statName === "Given" || $statName === "DG") && $statVal > 0) {
          if (isset($this->playerSkillProcessor->players_team)) {
            $this->playerSkillProcessor->updatePlayerEvent(
              $clientId,
              "damage given",
              $statVal,
              $this->playerInfo
            );
          } else {
            $this->playerSkillProcessor->updatePlayerEvent(
              $this->playerInfo[$clientId]["id"],
              "damage given",
              $statVal
            );
          }
        }
        if (($statName === "Recvd" || $statName === "DR") && $statVal > 0) {
          if (isset($this->playerSkillProcessor->players_team)) {
            $this->playerSkillProcessor->updatePlayerEvent(
              $clientId,
              "damage taken",
              $statVal,
              $this->playerInfo
            );
          } else {
            $this->playerSkillProcessor->updatePlayerEvent(
              $this->playerInfo[$clientId]["id"],
              "damage taken",
              $statVal
            );
          }
        }
        if (($statName === "TeamDmg" || $statName === "TD") && $statVal > 0) {
          if (isset($this->playerSkillProcessor->players_team)) {
            $this->playerSkillProcessor->updatePlayerEvent(
              $clientId,
              "damage to team",
              $statVal,
              $this->playerInfo
            );
          } else {
            $this->playerSkillProcessor->updatePlayerEvent(
              $this->playerInfo[$clientId]["id"],
              "damage to team",
              $statVal
            );
          }
        }
        $stats = substr($stats, strlen($sMatch[0]));
      }
      if (isset($this->playerSkillProcessor->players_team)) {
        $this->has_acc_stats[$clientId] = true;
      }
      return true;
    }
    return false;
  }

  // Process freeze events.
  private function processFreezeEvent(string &$line): bool
  {
    if (preg_match("/^Round starts/", $line)) {
      return true;
    } elseif (preg_match("/^Exit: Map voting complete/", $line)) {
      debugPrint("3wave portal game, ignored\n");
      $this->playerSkillProcessor->clearProcessorData();
      $this->gameInProgress = false;
      return true;
    } elseif (
      preg_match(
        "/^Client Connect Using IP Address: (\d+\.\d+\.\d+\.\d+)(:\d+)*(\s+\((.+)\))?/",
        $line,
        $match
      )
    ) {
      $this->miscStats["last_scanned_ip"] = $match[1];
      if (isset($match[4])) {
        $this->miscStats["last_scanned_guid"] = $match[4];
      }
      return true;
    } elseif (preg_match("/^ClientConnect: (\d+)/", $line, $match)) {
      $clientId = $match[1];
      if (isset($this->playerInfo[$clientId])) {
        unset($this->playerInfo[$clientId]);
      }
      if (isset($this->miscStats["last_scanned_ip"])) {
        $this->playerInfo[$clientId]["ip"] =
          $this->miscStats["last_scanned_ip"];
      }
      if (isset($this->miscStats["last_scanned_guid"])) {
        $this->playerInfo[$clientId]["guid"] =
          $this->miscStats["last_scanned_guid"];
      }
      return true;
    } elseif (preg_match("/^\^.Stats for (.*)/", $line, $match)) {
      $details = $match[1];
      $clientKey = $this->lookupPlayerByName(
        $this->convertColorCodes($details)
      );
      if (strlen($clientKey) < 1) {
        return true;
      }
      $this->miscStats["client_id_of_last_scanned_stats"] = $clientKey;
      return true;
    } elseif (
      isset($this->miscStats["client_id_of_last_scanned_stats"]) &&
      preg_match(
        "/^\^\d\[WP\](\w+)\s+\^\d\s+\d+\.\d+ \((\d+)\/(\d+)\)/",
        $line,
        $match
      )
    ) {
      $weapon = $match[1];
      $hits = $match[2];
      $shots = $match[3];
      if ($weapon === "MG") {
        $weapon = "MACHINEGUN";
      } elseif ($weapon === "SG") {
        $weapon = "SHOTGUN";
      } elseif ($weapon === "GL") {
        $weapon = "GRENADE";
      } elseif ($weapon === "RL") {
        $weapon = "ROCKET";
      } elseif ($weapon === "LG") {
        $weapon = "LIGHTNING";
      } elseif ($weapon === "RG") {
        $weapon = "RAILGUN";
      } elseif ($weapon === "PG") {
        $weapon = "PLASMA";
      } elseif ($weapon === "NG") {
        $weapon = "NAILGUN";
      }
      $clientKey = $this->miscStats["client_id_of_last_scanned_stats"];
      if ($shots > 0) {
        $this->playerSkillProcessor->updateAccuracyEvent(
          $this->playerInfo[$clientKey]["id"],
          $this->playerInfo[$clientKey]["id"],
          "accuracy|{$weapon}_hits",
          $hits
        );
        $this->playerSkillProcessor->updateAccuracyEvent(
          $this->playerInfo[$clientKey]["id"],
          $this->playerInfo[$clientKey]["id"],
          "accuracy|{$weapon}_shots",
          $shots
        );
      }
      return true;
    } elseif (
      isset($this->miscStats["client_id_of_last_scanned_stats"]) &&
      preg_match("/^\^\d(D[GT])\s+\^\d\s*(\d+)/", $line, $match)
    ) {
      $code = $match[1];
      $value = $match[2];
      $clientKey = $this->miscStats["client_id_of_last_scanned_stats"];
      if ($code === "DG" && $value > 0) {
        $this->playerSkillProcessor->updatePlayerEvent(
          $this->playerInfo[$clientKey]["id"],
          "damage given",
          $value
        );
      } elseif ($code === "DT" && $value > 0) {
        $this->playerSkillProcessor->updatePlayerEvent(
          $this->playerInfo[$clientKey]["id"],
          "damage taken",
          $value
        );
        unset($this->miscStats["client_id_of_last_scanned_stats"]);
      }
      return true;
    }
    return false;
  }

  // Process client details.
  private function processClientDetails(string &$line): bool
  {
    if (preg_match("/^ClientDetails: (.*)/", $line, $match)) {
      $details = $match[1];
      while (
        preg_match("/^(.+)\\\(.*)\\\/U", $details, $varMatch) ||
        preg_match("/^(.+)\\\(.*)/", $details, $varMatch)
      ) {
        $varName = $varMatch[1];
        $varValue = $varMatch[2];
        $details = substr($details, strlen($varName) + strlen($varValue) + 2);
        if ($varName === "ip") {
          $this->miscStats["last_scanned_ip"] = $varValue;
        } elseif ($varName === "guid") {
          $this->miscStats["last_scanned_guid"] = $varValue;
        }
      }
      return true;
    } elseif (preg_match("/^ClientConnect: (\d+)/", $line, $match)) {
      $clientId = $match[1];
      if (isset($this->playerInfo[$clientId])) {
        unset($this->playerInfo[$clientId]);
      }
      if (isset($this->miscStats["last_scanned_ip"])) {
        $this->playerInfo[$clientId]["ip"] =
          $this->miscStats["last_scanned_ip"];
      }
      if (isset($this->miscStats["last_scanned_guid"])) {
        $this->playerInfo[$clientId]["guid"] =
          $this->miscStats["last_scanned_guid"];
      }
      return true;
    }
    return false;
  }

  // Process client connect or chat.
  private function processClientConnectOrChat(string &$line): bool
  {
    if (preg_match("/^ClientConnect: (\d+) (.*)/", $line, $match)) {
      $clientId = $match[1];
      $vars = $match[2];
      if (isset($this->playerInfo[$clientId])) {
        unset($this->playerInfo[$clientId]);
      }
      while (
        preg_match("/^\\\(.+)\\\(.*)\\\/U", $vars, $varMatch) ||
        preg_match("/^\\\(.+)\\\(.*)/", $vars, $varMatch)
      ) {
        $varName = $varMatch[1];
        $varValue = $varMatch[2];
        $vars = substr($vars, strlen($varName) + strlen($varValue) + 2);
        if ($varName === "ip") {
          $this->playerInfo[$clientId]["ip"] = preg_replace(
            '/\:\d+$/',
            "",
            $varValue
          );
        } elseif ($varName === "guid") {
          $this->playerInfo[$clientId]["guid"] = $varValue;
        } elseif ($varName === "tld") {
          $this->playerInfo[$clientId]["tld"] = $varValue;
        } elseif ($varName === "rtld") {
          $this->playerInfo[$clientId]["rtld"] = $varValue;
        }
      }
      return true;
    } elseif (preg_match("/^say: (.+): /U", $line, $match)) {
      $namePart = $match[1];
      $chatMsg = substr($line, strlen($match[0]));
      $chatMsg = preg_replace("/^&.*\.wav /i", "", $chatMsg);
      if (preg_match("/ (\d+)$/U", $chatMsg, $numMatch)) {
        $clientId = $numMatch[1];
        $chatMsg = substr($chatMsg, 0, -(strlen($numMatch[1]) + 1));
      } else {
        $clientId = $this->lookupPlayerByName(
          $this->convertColorCodes($namePart)
        );
      }
      if (strlen($clientId) > 0) {
        if ($this->config["xp_version"] <= 103) {
          $chatMsg = preg_replace_callback(
            "/\+([\x01-\x7F])#/",
            function ($matches) {
              return chr(ord($matches[1]) + 127);
            },
            $chatMsg
          );
        } else {
          $chatMsg = preg_replace_callback(
            "/#(#|[0-9a-f]{2})/i",
            function ($matches) {
              return $matches[1] === "#" ? "#" : chr(hexdec($matches[1]));
            },
            $chatMsg
          );
        }
        $chatMsg = strtr($chatMsg, $this->translationData["char_trans"]);
        $this->playerSkillProcessor->updatePlayerQuote(
          $this->playerInfo[$clientId]["id"],
          $this->removeColorCodes($chatMsg)
        );
      }
      return true;
    }
    return false;
  }

  // Process RA3 events.
  private function processRA3Event(string &$line): bool
  {
    if (
      preg_match(
        "/^Kill: (\d+) (\d+) \d+: (.*) killed (.*) by MOD_UNKNOWN/",
        $line,
        $match
      ) &&
      ($this->config["gametype"] !== "xp" ||
        $this->translationData["gametype"] === "8")
    ) {
      $attacker = $match[1];
      $victim = $match[2];
      if ($attacker > 128) {
        $attacker = $victim;
      }
      if (
        isset($this->playerInfo[$attacker]["id"]) &&
        isset($this->playerInfo[$victim]["id"])
      ) {
        if (isset($this->playerSkillProcessor->players_team)) {
          $this->playerSkillProcessor->updatePlayerEvent(
            $attacker,
            "THAW",
            1,
            $this->playerInfo
          );
        } else {
          $this->playerSkillProcessor->updatePlayerEvent(
            $this->playerInfo[$attacker]["id"],
            "THAW",
            1
          );
        }
      }
      return true;
    }
    return false;
  }

  // Process UT events (preliminary).
  private function processUTEventPre(string &$line): bool
  {
    if (preg_match("/^Warmup:/", $line)) {
      return true;
    } elseif (preg_match("/^Item: \d+ ut_.*/", $line)) {
      $line = preg_replace("/(Item: \d+ )ut_/", "\${1}", $line, 1);
      return false;
    } elseif (
      preg_match("/^Kill: \d+ \d+ \d+: .* killed .* by UT_MOD_/", $line)
    ) {
      $line = preg_replace(
        "/(Kill: \d+ \d+ \d+: .* killed .* by )UT_MOD_/",
        "\${1}MOD_",
        $line,
        1
      );
      return false;
    } elseif (preg_match("/^ClientUserinfo: (\d+) (.*)/", $line, $match)) {
      $clientId = $match[1];
      $vars = $match[2];
      while (
        preg_match("/^\\\(.+)\\\(.*)\\\/U", $vars, $varMatch) ||
        preg_match("/^\\\(.+)\\\(.*)/", $vars, $varMatch)
      ) {
        $varName = $varMatch[1];
        $varValue = $varMatch[2];
        $vars = substr($vars, strlen($varName) + strlen($varValue) + 2);
        if ($varName === "ip") {
          $this->playerInfo[$clientId]["ip"] = $varValue;
        } elseif ($varName === "cl_guid") {
          $this->playerInfo[$clientId]["guid"] = $varValue;
        } elseif ($varName === "model") {
          if (
            !isset($this->playerInfo[$clientId]["icon"]) ||
            $this->playerInfo[$clientId]["icon"] !== $varValue
          ) {
            $this->playerInfo[$clientId]["icon"] = $varValue;
          }
        }
      }
      return true;
    }
    return false;
  }

  // Process UT events.
  private function processUTEvent(string &$line): bool
  {
    if (preg_match("/^ClientUserinfoChanged: (\d+) (.*)/", $line, $match)) {
      $clientId = $match[1];
      $originalLine = $line;
      $savedPos = $this->currentFilePosition;
      $nextLine = fgets($this->logFileHandle, cBIG_STRING_LENGTH);
      $nextLine = rtrim($nextLine, "\r\n");
      $this->extractTimestamp($nextLine);
      if (
        preg_match(
          "/^ClientConnect: (\d+).*(\\((\d+\.\d+\.\d+\.\d+).*\\)$)/",
          $nextLine,
          $m
        )
      ) {
        if ($clientId == $m[1]) {
          $tempId = $m[1];
          if (isset($this->playerInfo[$tempId])) {
            unset($this->playerInfo[$tempId]);
          }
          if (isset($m[3])) {
            $this->playerInfo[$tempId]["ip"] = $m[3];
          }
          $this->processClientUserinfoChanged($originalLine);
          return true;
        } else {
          $this->currentFilePosition = $savedPos;
          fseek($this->logFileHandle, $savedPos);
          return false;
        }
      } else {
        $this->currentFilePosition = $savedPos;
        fseek($this->logFileHandle, $savedPos);
        return false;
      }
    } elseif (
      preg_match(
        "/^ClientConnect: (\d+).*(\\((\d+\.\d+\.\d+\.\d+).*\\)$)/",
        $line,
        $match
      )
    ) {
      $clientId = $match[1];
      if (isset($this->playerInfo[$clientId])) {
        unset($this->playerInfo[$clientId]);
      }
      if (isset($match[3])) {
        $this->playerInfo[$clientId]["ip"] = $match[3];
      }
      return true;
    } elseif (
      preg_match("/^Kill: \d+ \d+ \d+ \d+: .* killed .* by \w+/", $line)
    ) {
      $line = preg_replace("/^(Kill: \d+ \d+ \d+) \d+/", "\${1}", $line, 1);
      return false;
    } elseif (preg_match("/^say: (\d+) \d+: (.+):/U", $line, $match)) {
      $chatMsg = substr($line, strlen($match[0]));
      $clientId = $match[1];
      if (isset($this->playerInfo[$clientId]["id"])) {
        $this->playerSkillProcessor->updatePlayerQuote(
          $this->playerInfo[$clientId]["id"],
          $this->removeColorCodes($chatMsg)
        );
      }
      return true;
    }
    return false;
  }

  // Dispatch game type–specific event processing.
  private function dispatchGameTypeEvent(string &$line): bool
  {
    if (
      $this->config["gametype"] === "osp" ||
      $this->config["gametype"] === "cpma"
    ) {
      if ($this->processThreewaveEvent($line)) {
        return true;
      } elseif ($this->processRA3Event($line)) {
        return true;
      }
    } elseif ($this->config["gametype"] === "threewave") {
      if ($this->processThreewaveEvent($line)) {
        return true;
      } elseif ($this->processFreezeEvent($line)) {
        return true;
      } else {
        return false;
      }
    } elseif ($this->config["gametype"] === "battle") {
      if ($this->processThreewaveEvent($line)) {
        return true;
      } elseif ($this->processClientDetails($line)) {
        return true;
      } else {
        return false;
      }
    } elseif ($this->config["gametype"] === "freeze") {
      if ($this->processThreewaveEvent($line)) {
        return true;
      } elseif ($this->processRA3Event($line)) {
        return true;
      } else {
        return false;
      }
    } elseif ($this->config["gametype"] === "ut") {
      return $this->processUTEventPre($line);
    } elseif ($this->config["gametype"] === "ra3") {
      return $this->processUTEvent($line);
    } elseif ($this->config["gametype"] === "lrctf") {
      return $this->processCTFAwards($line);
    } elseif ($this->config["gametype"] === "xp") {
      if ($this->processThreewaveEvent($line)) {
        return true;
      } elseif ($this->processRA3Event($line)) {
        return true;
      } elseif ($this->processClientConnectOrChat($line)) {
        return true;
      } elseif ($this->isClientGuid($line)) {
        return true;
      } else {
        return false;
      }
    }
    return false;
  }

  // Change the client's GUID.
  private function isClientGuid(string &$line): bool
  {
    if (!preg_match("/^ClientGuid: (\d+) (.*)/", $line, $matches)) {
      return false;
    }
    $clientId = $matches[1];
    $guid = trim($matches[2]);
    if (isset($this->playerInfo[$clientId])) {
      $this->playerInfo[$clientId]["guid"] = $guid;
    }
    return true;
  }

  // Extract timestamp from the beginning of a line.
  private function extractTimestamp(string &$line): bool
  {
    if (preg_match("/^\[(\d+[\:\.]\d+[\:\.]\d+)\]\s*/", $line, $match)) {
      $this->rawTimestamp = $match[1];
      $line = substr($line, strlen($match[0]));
      return true;
    } elseif (preg_match("/^(\d+[\:\.]\d+[\:\.]\d+)\s*/", $line, $match)) {
      $this->rawTimestamp = $match[1];
      $line = substr($line, strlen($match[0]));
      return true;
    } elseif (preg_match("/^ *(\d+[\:\.]\d+)\s*/", $line, $match)) {
      $this->rawTimestamp = $match[1];
      $line = substr($line, strlen($match[0]));
      return true;
    }
    return false;
  }

  // Stub for unknown processing.
  private function Fa8539cfc(string &$line): bool
  {
    return false;
  }

  // Main log line processor.
  private function processLogLine(string &$line): void
  {
    $this->extractTimestamp($line);
    if ($this->processGameInit($line)) {
      echo sprintf(
        "(%05.2f%%) ",
        (100.0 * ftell($this->logFileHandle)) /
          $this->translationData["logfile_size"]
      );
    } elseif ($this->gameInProgress) {
      if ($this->dispatchGameTypeEvent($line)) {
        // handled
      } elseif ($this->processClientUserinfoChanged($line)) {
        // handled
      } elseif ($this->processItemPickup($line)) {
        // handled
      } elseif ($this->processKillEvent($line)) {
        // handled
      } elseif ($this->processClientConnect($line)) {
        // handled
      } elseif ($this->processClientDisconnect($line)) {
        // handled
      } elseif ($this->processClientBegin($line)) {
        // handled
      } elseif ($this->processClientChat($line)) {
        // handled
      } elseif ($this->processGameShutdown($line)) {
        // handled
      } elseif ($this->processPlayerScore($line)) {
        // handled
      } elseif ($this->processTeamScoreLine($line)) {
        // handled
      } elseif ($this->processServerTime($line)) {
        // handled
      } elseif ($this->processWarmup($line)) {
        // handled
      } elseif ($this->Fa8539cfc($line)) {
        // handled
      }
    }
  }
}
?>
