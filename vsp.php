<?php
declare(strict_types=1);

require_once "game-data-processor.php";
require_once "player-skill-processor.php";

/* vsp stats processor, copyright 2004-2005, myrddin8 AT gmail DOT com (a924cb279be8cb6089387d402288c9f2) */
define("cVERSION", "0.45-xp-1.1.2");
define(
  "cTITLE",
  /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ " ----------------------------------------------------------------------------- " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                     vsp stats processor (c) 2004-2005                         " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                               version " .
    cVERSION .
    "                                    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                 vsp by myrddin (myrddin8 AT gmail DOT com)                    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ " ----------------------------------------------------------------------------- " .
    "\r\n" .
    "\r\n"
);
define(
  "cUSAGE",
  /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "  ---------------------------------------------------------------------------  " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "  Usage: php vsp.php [options] [-p parserOptions] [logFilename]                " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    [options]                                                                  " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    ---------                                                                  " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    -c                 specify config file (must be in pub/configs/)           " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    -l                 specify logType (gamecode-gametype)                     " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         logType:-                                             " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           client           Client Logs (Any game)             " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a              Quake 3 Arena (and q3 engine games)" .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-battle       Quake 3 Arena BattleMod            " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-cpma         Quake 3 Arena CPMA (Promode)       " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-freeze       Quake 3 Arena (U)FreezeTag etc.    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-lrctf        Quake 3 Arena Lokis Revenge CTF    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-osp          Quake 3 Arena OSP                  " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-ra3          Quake 3 Arena Rocket Arena 3       " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-threewave    Quake 3 Arena Threewave            " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-ut           Quake 3 Arena UrbanTerror          " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           q3a-xp           Quake 3 Arena Excessive Plus       " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    -n                                                                         " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         No confirmation/prompts (for unattended runs etc.)    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    -a                 specify action                                          " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         perform a specific predefined action                  " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         *make sure this is the last option specified!*        " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         [logFilename] is not needed if this option is used    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         action:-                                              " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           clear_db         Clear the database in config       " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            ie. Reset Stats                    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           gen_awards       Generate only the awards           " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           clear_savestate  Clears the savestate information   " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            for the specified log. If no log   " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            file is specified, then all the    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            savestate information will be      " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            cleared. Currently only works with " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            the q3a gamecode                   " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           pop_ip2country   Deletes the information of the     " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            ip2country table and populates it  " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            from the CSV file specified in the " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            configuration                      " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                           prune_old_games  Removes all the detailed           " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                            information of old games           " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    -p [parserOptions]                                                         " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "       savestate       1                                                       " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         Enable savestate processing                           " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         Remembers previously scanned logs and events.         " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         If this option is enabled, VSP will remember the      " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         location in the log file where the last stats was     " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         parsed from. So the next time VSP is run with the     " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         savestate 1 option against the same log file, it will " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         start parsing the stats from the previous saved       " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         location.                                             " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         If you want VSP to forget this save state, then you   " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         have to delete the corresponding save state file from " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         the logdata/ folder. The name is in the format        " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         savestate_[special_Form_Of_Logfile_Name]              " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         Deleting that file and running VSP again with         " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         savestate 1 option will reparse the whole log again   " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         from the beginning. Also note that each logfile will  " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         have a separate save state file under the logdata     " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         folder. Do not edit/modify the savestate files! If    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                         you dont want it, just delete it.                     " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "       check ReadME or first few lines of a particular parser php for other    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "       valid options for that particular parser                                " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    [logFilename] could be an FTP link/url. Set FTP username/password in config" .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    [logFilename] may be a logDirectory for some games. ex:- *HalfLife*        " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "                                                                               " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "    Usage: php vsp.php [options] [-p parserOptions] [logFilename]              " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "  Example: php vsp.php -l q3a -p savestate 1 \"c:/quake iii arena/games.log\"    " .
    "\r\n" .
    /*__POBS_EXCLUDE__*/ "  ---------------------------------------------------------------------------  " .
    "\r\n" .
    "\r\n"
);

function printTitle(): void
{
  echo cTITLE;
}

function printUsage(): void
{
  echo cUSAGE;
}

function debugPrint(string $message): void
{
  $printFlag = 1;
  if ($printFlag === 1) {
    echo $message;
  }
}

function errorAndExit(string $errorMessage): void
{
  echo "\n$errorMessage\n";
  exitProgram();
}

function usageErrorExit(string $errorMessage): void
{
  printUsage();
  echo "$errorMessage\n";
  exitProgram();
}

function getFtpFileList(&$ftpConnection, string $remotePath): array
{
  $rawList = ftp_rawlist($ftpConnection, $remotePath);
  $parsedList = parseFileListing($rawList);
  $fileList = [];
  foreach ($parsedList as $fileInfo) {
    if ($fileInfo["type"] === 0) {
      $fileList[] = $fileInfo;
    }
  }
  return $fileList;
}

function downloadFtpLogs(string $ftpUrl): string
{
  $parsedUrl = parse_url($ftpUrl);
  echo "Attempting to connect to FTP server {$parsedUrl["host"]}:{$parsedUrl["port"]}...\n";
  if (isset($parsedUrl["user"]) || isset($parsedUrl["pass"])) {
    echo " - Specify the ftp username and password in the config and not in the VSP command line (Security reasons?)\n";
    exitProgram();
  }
  flushOutputBuffers();
  if (
    !($ftpConnection = ftp_connect(
      $parsedUrl["host"],
      (int) $parsedUrl["port"],
      30
    ))
  ) {
    echo " - Error: Failed to connect to ftp server. Verify FTP hostname/port.\n";
    echo " Also, your php host may not have ftp access via php enabled or may\n";
    echo " have blocked the php process from connecting to an external server\n";
    exitProgram();
  }
  if (
    !ftp_login(
      $ftpConnection,
      $GLOBALS["cfg"]["ftp"]["username"],
      $GLOBALS["cfg"]["ftp"]["password"]
    )
  ) {
    echo " - Error: Failed to login to ftp server. Verify FTP username/password in config\n";
    exitProgram();
  }
  echo " - Connection/Login successful.\n";
  if (isset($GLOBALS["cfg"]["ftp"]["pasv"]) && $GLOBALS["cfg"]["ftp"]["pasv"]) {
    if (ftp_pasv($ftpConnection, true)) {
      echo " - FTP passive mode enabled\n";
    } else {
      echo " - failed to enable FTP passive mode\n";
    }
  } else {
    echo " - not using FTP passive mode (disabled in config)\n";
  }
  if (!ensureDirectoryExists($GLOBALS["cfg"]["ftp"]["logs_path"])) {
    echo " - Error: Failed to create local directory \"" .
      $GLOBALS["cfg"]["ftp"]["logs_path"] .
      "\" for FTP log download.\n";
    echo " Check pathname/permissions.\n";
    exitProgram();
  }
  if (preg_match("/[\\/\\\\]$/", $parsedUrl["path"])) {
    echo " - Preparing to download all files from remote directory \"{$parsedUrl["path"]}\"\n";
    $fileList = getFtpFileList($ftpConnection, $parsedUrl["path"]);
    preg_match("/([^\\/\\\\]+[\\/\\\\])$/", $parsedUrl["path"], $matches);
    $remoteBasePath = $parsedUrl["path"];
    $localDirectory = ensureTrailingSlash(
      $GLOBALS["cfg"]["ftp"]["logs_path"] . $matches[1]
    );
    ensureDirectoryExists($localDirectory);
    $localTargetPath = $localDirectory;
  } else {
    echo " - Preparing to download the remote file \"{$parsedUrl["path"]}\"\n";
    preg_match("/([^\\/\\\\]+)$/", $parsedUrl["path"], $matches);
    $fileList[0]["name"] = $matches[1];
    $fileList[0]["size"] = ftp_size($ftpConnection, $parsedUrl["path"]);
    $remoteBasePath = substr(
      $parsedUrl["path"],
      0,
      strlen($parsedUrl["path"]) - strlen($fileList[0]["name"])
    );
    $localDirectory = ensureTrailingSlash($GLOBALS["cfg"]["ftp"]["logs_path"]);
    $localTargetPath = $localDirectory . $fileList[0]["name"];
  }
  if (!ctype_digit((string) $fileList[0]["size"]) || $fileList[0]["size"] < 0) {
    echo " - Error: cannot find Remote file \"{$fileList[0]["name"]}\" at ftp://{$parsedUrl["host"]}:{$parsedUrl["port"]}{$remoteBasePath}\n";
    exitProgram();
  }
  foreach ($fileList as $fileInfo) {
    $localFilePath = $localDirectory . $fileInfo["name"];
    $localFileSize = file_exists($localFilePath)
      ? filesize($localFilePath) - 1
      : 0;
    $remoteFilePath = $remoteBasePath . $fileInfo["name"];
    $remoteFileSize = $fileInfo["size"];
    echo " - Attempting to download \"$remoteFilePath\" from FTP server to \"$localFilePath\"...\n";
    flushOutputBuffers();
    if (
      isset($GLOBALS["cfg"]["ftp"]["overwrite"]) &&
      $GLOBALS["cfg"]["ftp"]["overwrite"]
    ) {
      echo " - overwrite mode\n";
      if (
        !ftp_get($ftpConnection, $localFilePath, $remoteFilePath, FTP_BINARY)
      ) {
        echo " Error: Failed to get ftp log from \"$remoteFilePath\" to \"$localFilePath\".\n";
        if (!$GLOBALS["cfg"]["ftp"]["pasv"]) {
          echo " Try enabling FTP passive mode in config.\n";
        }
        echo " Try making the ftplogs/ and logdata/ folder writable by all (chmod 777).\n";
        exitProgram();
      }
      echo " Downloaded remote file successfully\n";
      flushOutputBuffers();
    } else {
      if ($remoteFileSize === $localFileSize + 1) {
        echo " Remote file is the same size as Local file. Skipped Download.\n";
      } elseif ($remoteFileSize > $localFileSize + 1) {
        if (
          !ftp_get(
            $ftpConnection,
            $localFilePath,
            $remoteFilePath,
            FTP_BINARY,
            $localFileSize
          )
        ) {
          echo " Error: Failed to get ftp log from \"$remoteFilePath\" to \"$localFilePath\".\n";
          if (!$GLOBALS["cfg"]["ftp"]["pasv"]) {
            echo " Try enabling FTP passive mode in config.\n";
          }
          echo " Try making the ftplogs/ and logdata/ folder writable by all (chmod 777).\n";
          exitProgram();
        }
        echo " Downloaded/Resumed remote file successfully\n";
      } else {
        echo " Remote file is smaller than Local file. Skipped Download.\n";
      }
      flushOutputBuffers();
    }
  }
  echo $localTargetPath . "\n";
  return $localTargetPath;
}

function processCommandLineArgs(): void
{
  global $cliArgs;
  if (cIS_SHELL) {
    if (!isset($_SERVER["argc"])) {
      echo "Error: args not registered.\n";
      echo " register_argc_argv may need to be set to On in shell mode\n";
      echo " Please edit your php.ini and set variable register_argc_argv to On\n";
      exitProgram();
    }
    $cliArgs["argv"] = $_SERVER["argv"];
    $cliArgs["argc"] = $_SERVER["argc"];
  } else {
    $cmdLine = $_POST["CMD_LINE_ARGS"];
    $cliArgs = parseCommandLineArgs("vsp.php " . $cmdLine);
  }
  global $options;
  $options["parser-options"] = [];
  $options["prompt"] = 1;
  if ($cliArgs["argc"] > 1) {
    for ($i = 1; $i < $cliArgs["argc"] - 1; $i++) {
      if (strcmp($cliArgs["argv"][$i], "-a") === 0) {
        $i++;
        $options["action"] = $cliArgs["argv"][$i];
        if (
          !in_array(
            $options["action"],
            [
              "clear_db",
              "gen_awards",
              "clear_savestate",
              "pop_ip2country",
              "prune_old_games",
            ],
            true
          )
        ) {
          usageErrorExit("error: invalid action");
        }
        break;
      }
      if (strcmp($cliArgs["argv"][$i], "-n") === 0) {
        $options["prompt"] = 0;
        continue;
      }
      if ($i + 1 > $cliArgs["argc"] - 2) {
        usageErrorExit(
          "error: no value specified for option " . $cliArgs["argv"][$i]
        );
      }
      if (strcmp($cliArgs["argv"][$i], "-p") === 0) {
        $i++;
        for ($j = $i; $j < $cliArgs["argc"] - 1; $j += 2) {
          $options["parser-options"][$cliArgs["argv"][$j]] =
            $cliArgs["argv"][$j + 1];
        }
        break;
      } elseif (strcmp($cliArgs["argv"][$i], "-c") === 0) {
        $i++;
        $options["config"] = $cliArgs["argv"][$i];
      } elseif (strcmp($cliArgs["argv"][$i], "-l") === 0) {
        $i++;
        $options["log-gamecode"] = $cliArgs["argv"][$i];
        $options["log-gametype"] = "";
        if (preg_match("/(.*)-(.*)/", $options["log-gamecode"], $matches)) {
          $options["log-gamecode"] = $matches[1];
          $options["log-gametype"] = $matches[2];
          $options["parser-options"]["gametype"] = $options["log-gametype"];
        }
      } else {
        usageErrorExit("error: invalid option " . $cliArgs["argv"][$i]);
      }
    }
  } else {
    usageErrorExit("error: logfile not specified");
  }
  $options["logfile"] = $cliArgs["argv"][$cliArgs["argc"] - 1];
  if (!isset($options["action"])) {
    if (!isset($options["logfile"])) {
      usageErrorExit("error: logFile not specified");
    }
    if (!isset($options["log-gamecode"])) {
      usageErrorExit("error: logType not specified");
    }
  }
  $configPath = "pub/configs/";
  if (
    !isset($options["config"]) ||
    preg_match("/\\.\\./", $options["config"]) ||
    !is_file($configPath . $options["config"])
  ) {
    $options["config"] = $configPath . "cfg-default.php";
  } else {
    $options["config"] = $configPath . $options["config"];
  }
  echo "max_execution_time is " . ini_get("max_execution_time") . "\n\n";
  echo "[command-line options]: ";
  print_r($options);
  if (
    isset($options["parser-options"]["savestate"]) &&
    $options["parser-options"]["savestate"]
  ) {
    $testFile = "writetest_" . md5(uniqid((string) rand(), true));
    $fp = fopen("./logdata/" . $testFile, "wb");
    if (!$fp || !fwrite($fp, "* WRITE TEST *\n")) {
      echo "Error: savestate 1 processing requires logdata/ directory to be writable.\n";
      echo " Enable write permissions for logdata/ directory (chmod 777)\n";
      exitProgram();
    }
    fclose($fp);
    unlink("logdata/$testFile");
  }
}

function configureAndProcessGameLogs(): void
{
  global $options, $cliArgs;
  require_once $options["config"];
  if (preg_match("/^ftp:\\/\\//i", $options["logfile"])) {
    $options["logfile"] = downloadFtpLogs($options["logfile"]);
  }
  $options["parser-options"]["trackID"] = $GLOBALS["cfg"]["parser"]["trackID"];
  if (isset($GLOBALS["cfg"]["db"]["adodb_path"])) {
    $GLOBALS["cfg"]["db"]["adodb_path"] = ensureTrailingSlash(
      $GLOBALS["cfg"]["db"]["adodb_path"]
    );
  } else {
    $GLOBALS["cfg"]["db"]["adodb_path"] =
      ensureTrailingSlash(APP_ROOT_DIR) . "pub/lib/adodb/";
  }
  require_once "{$GLOBALS["cfg"]["db"]["adodb_path"]}adodb.inc.php";
  include_once "{$GLOBALS["cfg"]["db"]["adodb_path"]}tohtml.inc.php";
  require_once "sql/{$GLOBALS["cfg"]["db"]["adodb_driver"]}.inc.php";
  include_once "pub/include/playerBanList-{$GLOBALS["cfg"]["player_ban_list"]}.inc.php";
  include_once "pub/include/playerExcludeList-{$GLOBALS["cfg"]["player_exclude_list"]}.inc.php";
  foreach ($GLOBALS["player_ban_list"] as $key => $value) {
    $GLOBALS["player_ban_list"][$key] = "/^" . preg_quote($value, "/") . "$/";
  }
  $GLOBALS["db"] = &ADONewConnection($GLOBALS["cfg"]["db"]["adodb_driver"]);
  global $db;
  if (
    !$db->Connect(
      $GLOBALS["cfg"]["db"]["hostname"],
      $GLOBALS["cfg"]["db"]["username"],
      $GLOBALS["cfg"]["db"]["password"],
      $GLOBALS["cfg"]["db"]["dbname"]
    )
  ) {
    echo "Attempting to create/connect to database {$GLOBALS["cfg"]["db"]["dbname"]}\n";
    $GLOBALS["db"] = null;
    $GLOBALS["db"] = &ADONewConnection($GLOBALS["cfg"]["db"]["adodb_driver"]);
    $db->Connect(
      $GLOBALS["cfg"]["db"]["hostname"],
      $GLOBALS["cfg"]["db"]["username"],
      $GLOBALS["cfg"]["db"]["password"]
    );
    $db->Execute($sql_create[0]);
    if (
      !$db->Connect(
        $GLOBALS["cfg"]["db"]["hostname"],
        $GLOBALS["cfg"]["db"]["username"],
        $GLOBALS["cfg"]["db"]["password"],
        $GLOBALS["cfg"]["db"]["dbname"]
      )
    ) {
      echo " - failed to create/connect to database {$GLOBALS["cfg"]["db"]["dbname"]}\n";
      exitProgram();
    }
    echo " - database created\n";
  }
  if (isset($options["action"])) {
    switch ($options["action"]) {
      case "clear_db":
        if (cIS_SHELL && $options["prompt"]) {
          echo "Are you sure you want to clear the database {$GLOBALS["cfg"]["db"]["dbname"]} @ {$GLOBALS["cfg"]["db"]["hostname"]}? (y/n)\n";
          flushOutputBuffers();
          $confirm = readStdinLine();
        } else {
          $confirm = "y";
        }
        if ($confirm === "y" || $confirm === "Y") {
          foreach ($sql_destroy as $key => $sql) {
            $db->Execute($sql);
          }
          print "{$GLOBALS["cfg"]["db"]["table_prefix"]}* tables in {$GLOBALS["cfg"]["db"]["dbname"]} @ {$GLOBALS["cfg"]["db"]["hostname"]} has been cleared\n";
        }
        finalizeProgram();
        break;
      case "gen_awards":
        $processor = new GameDataProcessor();
        $processor->generateAwards();
        finalizeProgram();
        break;
      case "clear_savestate":
        if ($options["logfile"] === "clear_savestate") {
          $options["logfile"] = "";
        } else {
          $realpath_log = realpath($options["logfile"]);
        }
        if (cIS_SHELL && $options["prompt"]) {
          if ($options["logfile"]) {
            echo "Are you sure you want to clear the savestate information for the log file {$realpath_log}? (y/n)\n";
          } else {
            echo "Are you sure you want to clear the savestate information for ALL the log files? (y/n)\n";
          }
          flushOutputBuffers();
          $confirm = readStdinLine();
        } else {
          $confirm = "y";
        }
        if ($confirm === "y" || $confirm === "Y") {
          $logfile = $db->qstr($realpath_log);
          $sql = "DELETE FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}savestate";
          if ($options["logfile"]) {
            $sql .= " WHERE `logfile` = {$logfile}";
          }
          $db->Execute($sql);
          echo "Savestate information for log file {$realpath_log} cleared\n";
        }
        finalizeProgram();
        break;
      case "pop_ip2country":
        populateIp2countryTable();
        finalizeProgram();
        break;
      case "prune_old_games":
        $processor = new GameDataProcessor();
        $processor->prune_old_games();
        finalizeProgram();
        break;
    }
  }
  foreach ($sql_create as $key => $sql) {
    if ($key === 0) {
      continue;
    }
    $db->Execute($sql);
  }
  $sql = "SELECT COUNT(*) FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}ip2country";
  $rs = $db->Execute($sql);
  if (!$rs || !$rs->fields[0]) {
    populateIp2countryTable();
  }
  $db->SetFetchMode(ADODB_FETCH_NUM);
  if (!is_dir("pub/games/{$GLOBALS["cfg"]["game"]["name"]}")) {
    echo "Error: The variable \$cfg['game']['name'] is not set properly in config file.\n";
    echo " Edit your config file ({$options["config"]})\n";
    echo " Read the comments beside that variable and set that variable properly.\n";
    exitProgram();
  }
  if (!file_exists("vsp-{$options["log-gamecode"]}.php")) {
    usageErrorExit("error: unrecognized logType");
  }
  require_once "vsp-{$options["log-gamecode"]}.php";
  include_once "pub/games/{$GLOBALS["cfg"]["game"]["name"]}/skillsets/{$GLOBALS["cfg"]["skillset"]}/{$GLOBALS["cfg"]["skillset"]}-skill.php";
  if (!isset($GLOBALS["skillset"])) {
    echo "Skill Definitions not found.\n";
    echo " pub/games/{$GLOBALS["cfg"]["game"]["name"]}/skillsets/{$GLOBALS["cfg"]["skillset"]}/{$GLOBALS["cfg"]["skillset"]}-skill.php\n";
  }
  $processor = new GameDataProcessor();
  $skillProcessor = new PlayerSkillProcessor();
  $upperLogCode = strtoupper($options["log-gamecode"]);
  eval(
    "\$parser = new VSPParser$upperLogCode(\$options['parser-options'], \$processor, \$skillProcessor);"
  );
  if (is_dir($options["logfile"])) {
    $logFiles = scandir($options["logfile"]);
    foreach ($logFiles as $logFile) {
      if ($logFile === "." || $logFile === "..") {
        continue;
      }
      $parser->processLogFile($options["logfile"] . "/" . $logFile);
    }
  } else {
    $parser->processLogFile($options["logfile"]);
  }
  $processor->prune_old_games();
  $processor->generateAwards();
  echo "\ngames: parsed: " .
    $processor->games_parsed .
    "\tinserted: " .
    $processor->games_inserted .
    "\t ignored: " .
    ($processor->games_parsed - $processor->games_inserted) .
    "\n";
}

function populateIp2countryTable(): void
{
  global $db;
  echo "Populating ip to country table...";
  flushOutputBuffers();
  $sql = "DELETE FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}ip2country";
  $db->Execute($sql);
  $flag = false;
  $filename = realpath("sql/{$GLOBALS["cfg"]["ip2country"]["source"]}");
  if (!file_exists($filename)) {
    $filename = $GLOBALS["cfg"]["ip2country"]["source"];
  }
  $countries = [];
  if ($file = fopen($filename, "rb")) {
    while ($line = fgetcsv($file)) {
      $from_index = $GLOBALS["cfg"]["ip2country"]["columns"]["ip_from"];
      $to_index = $GLOBALS["cfg"]["ip2country"]["columns"]["ip_to"];
      $code_index = $GLOBALS["cfg"]["ip2country"]["columns"]["country_code2"];
      $name_index = $GLOBALS["cfg"]["ip2country"]["columns"]["country_name"];
      if (
        !isset(
          $line[$from_index],
          $line[$to_index],
          $line[$code_index],
          $line[$name_index]
        ) ||
        !is_numeric($line[$from_index]) ||
        !is_numeric($line[$to_index]) ||
        strlen($line[$code_index]) !== 2 ||
        !$line[$name_index]
      ) {
        continue;
      }
      $flag = true;
      $countryCode = $db->qstr($line[$code_index]);
      $countryName = $db->qstr($line[$name_index]);
      if (
        $GLOBALS["cfg"]["ip2country"]["countries_only"] &&
        array_key_exists($countryCode, $countries)
      ) {
        continue;
      }
      $countries[$countryCode] = true;
      $sql = "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}ip2country (ip_from, ip_to, country_code2, country_name)
                  VALUES ({$line[$from_index]}, {$line[$to_index]}, $countryCode, $countryName)";
      $db->Execute($sql);
    }
  }
  if (!$flag) {
    echo "\n - error at populating ip to country table.\n";
    exitProgram();
  }
  if (!array_key_exists("XX", $countries)) {
    $sql = "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}ip2country (ip_from, ip_to, country_code2, country_name)
              VALUES (4294967295, 4294967295, 'XX', 'UNKNOWN LOCATION')";
    $db->Execute($sql);
  }
  if (!array_key_exists("ZZ", $countries)) {
    $sql = "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}ip2country (ip_from, ip_to, country_code2, country_name)
              VALUES (4294967294, 4294967294, 'ZZ', 'UNKNOWN LOCATION')";
    $db->Execute($sql);
  }
  echo "done\n";
  flushOutputBuffers();
}

function save_savestate(&$parser): void
{
  $parser->logdata["last_shutdown_end_position"] = ftell(
    $parser->logFileHandle
  );
  $seekResult = fseek($parser->logFileHandle, -LOG_READ_SIZE, SEEK_CUR);
  if ($seekResult === 0) {
    $parser->logdata["last_shutdown_hash"] = md5(
      fread($parser->logFileHandle, LOG_READ_SIZE)
    );
  } else {
    $currentPos = ftell($parser->logFileHandle);
    fseek($parser->logFileHandle, 0);
    $parser->logdata["last_shutdown_hash"] = md5(
      fread($parser->logFileHandle, $currentPos)
    );
  }
  global $db;
  $logfile = $db->qstr(
    isset($parser->original_log) ? $parser->original_log : $parser->logFilePath
  );
  $sql = "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}savestate SET `logfile` = {$logfile}";
  $rs = $db->Execute($sql);
  $value = $db->qstr(
    "\$this->logdata['last_shutdown_hash']='{$parser->logdata["last_shutdown_hash"]}';\n" .
      "\$this->logdata['last_shutdown_end_position']={$parser->logdata["last_shutdown_end_position"]};"
  );
  $sql = "UPDATE {$GLOBALS["cfg"]["db"]["table_prefix"]}savestate SET `value` = {$value}";
  $rs = $db->Execute($sql);
}

function check_savestate(&$parser): void
{
  echo "Verifying savestate\n";
  $fp = fopen($parser->logFilePath, "rb");
  $seekResult = fseek($fp, $parser->logdata["last_shutdown_end_position"]);
  if ($seekResult === 0) {
    $seekResult2 = fseek($fp, -LOG_READ_SIZE, SEEK_CUR);
    if ($seekResult2 === 0) {
      $fileData = fread($fp, LOG_READ_SIZE);
    } else {
      $currentPos = ftell($fp);
      fseek($fp, 0);
      $fileData = fread($fp, $currentPos);
    }
    if (strcmp(md5($fileData), $parser->logdata["last_shutdown_hash"]) === 0) {
      echo " - Hash matched, resuming parsing from previous saved location in log file\n";
      fseek(
        $parser->logFileHandle,
        $parser->logdata["last_shutdown_end_position"]
      );
    } else {
      echo " - Hash did not match, assuming new log file\n";
      fseek($parser->logFileHandle, 0);
    }
  } else {
    echo " - Seek to prior location failed, assuming new log file\n";
    fseek($parser->logFileHandle, 0);
  }
  fclose($fp);
}

function checkWebAccess(): void
{
  require_once "./password.inc.php";
  if (strlen($vsp["password"]) < 6) {
    echo "<HTML><BODY><PRE>Web Access to vsp.php is currently disabled.\nIf you want to enable web access to vsp.php,\nlook in password.inc.php under your vsp folder using a text editor(notepad).\nRead the ReadME.txt file for additional information.";
    exitProgram();
  }
  if (!isset($_POST["password"])) { ?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
    <HTML> <HEAD> <TITLE>vsp stats processor</TITLE> </HEAD>
    <BODY> <center> <PRE> <?php printTitle(); ?>
    </PRE>
    <form action="vsp.php?mode=web" method="post">
      <TABLE BORDER="0" CELLSPACING="5" CELLPADDING="0">
        <TR> <TD>&nbsp;</TD> <TD>[options] [-p parserOptions] [logFilename]</TD> </TR>
        <TR> <TD VALIGN="TOP">php vsp.php</TD>
          <TD><input size="50" type="text" name="CMD_LINE_ARGS" /><BR>example: -l q3a-osp -p savestate 1 "games.log"</TD>
        </TR>
      </TABLE>
      <BR><BR> password = <input size=10 type=password name="password" /><BR><BR>
      <input type="submit" value="Submit ( Process Stats )" />
      <BR><BR>
    </form>
    <PRE> <?php printUsage(); ?>
    </PRE> </center> </BODY></HTML>
    <?php exit();}
  $userPass = $_POST["password"];
  if (md5($userPass) !== md5($vsp["password"])) {
    echo "<HTML><BODY><PRE>Invalid password.\nFor the correct password, Look in password.inc.php under your vsp folder using a text editor(notepad).";
    exitProgram();
  }
}

function initializeEnvironment(): void
{
  flushOutputBuffers();
  $GLOBALS["startTime"] = gettimeofday();
  set_time_limit(0);
  define("APP_ROOT_DIR", dirname(realpath(__FILE__)));
  if (
    (isset($_GET["mode"]) && $_GET["mode"] === "web") ||
    isset($_SERVER["QUERY_STRING"]) ||
    isset($_SERVER["HTTP_HOST"]) ||
    isset($_SERVER["SERVER_PROTOCOL"]) ||
    isset($_SERVER["SERVER_SOFTWARE"]) ||
    isset($_SERVER["SERVER_NAME"])
  ) {
    define("cIS_SHELL", 0);
  } else {
    define("cIS_SHELL", 1);
  }
  define("cBIG_STRING_LENGTH", "1024");
  if (cIS_SHELL) {
    ini_set("html_errors", "0");
    chdir(APP_ROOT_DIR);
  } else {
    ini_set("html_errors", "1");
    checkWebAccess();
    echo "<HTML><BODY><PRE>";
  }
  printTitle();
}

function exitProgram(): void
{
  if (!cIS_SHELL) {
    echo "</PRE></BODY></HTML>";
  }
  exit();
}

function finalizeProgram(): void
{
  printTitle();
  $elapsed = getElapsedTime($GLOBALS["startTime"]);
  $minutes = floor($elapsed / 60);
  $seconds = $elapsed % 60;
  echo "processed in {$minutes}m {$seconds}s ({$elapsed}s)\n";
  if (!cIS_SHELL) {
    echo "</PRE></BODY></HTML>";
  }
  exit();
}

require_once "vutil.php";
initializeEnvironment();
processCommandLineArgs();
configureAndProcessGameLogs();
finalizeProgram();

?>
