<?php
declare(strict_types=1);

/* vsp stats processor, copyright 2004-2005, myrddin8 AT gmail DOT com (a924cb279be8cb6089387d402288c9f2) */

function parseFileListing(array $fileLines): array
{
  $result = [];
  foreach ($fileLines as $line) {
    if (
      preg_match(
        "/([-dl][rwxst-]+).* ([0-9]*) ([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{2}:[0-9]{2})|[0-9]{4}) (.+)/",
        $line,
        $matches
      )
    ) {
      // Determine file type (directory, link, etc.) by finding the position of the first char in "-dl"
      $fileType = (int) strpos("-dl", $matches[1][0]);
      $fileData = [
        "line" => $matches[0],
        "type" => $fileType,
        "rights" => $matches[1],
        "number" => $matches[2],
        "user" => $matches[3],
        "group" => $matches[4],
        "size" => $matches[5],
        "date" => date("m-d", strtotime($matches[6])),
        "time" => $matches[7],
        "name" => $matches[9],
      ];
      $result[] = $fileData;
    }
  }
  return $result;
}

function parseCommandLineArgs(string $inputStr): array
{
  $args = ["argv" => []];
  while (
    preg_match('/^\s*"(.+)"/U', $inputStr, $match) ||
    preg_match("/^\s*([^\s]+)\s*/", $inputStr, $match)
  ) {
    // Remove the matched portion from the beginning of the input string.
    $inputStr = substr($inputStr, strlen($match[0]));
    $args["argv"][] = $match[1];
  }
  $args["argc"] = count($args["argv"]);
  return $args;
}

function flushOutputBuffers(): void
{
  while (ob_get_level() > 0) {
    ob_end_flush();
  }
  flush();
}

function ensureTrailingSlash(string $path): string
{
  return rtrim($path, "\\/") . "/";
}

function copyDirectoryRecursive(string $sourceDir, string $destDir): void
{
  $sourceDir = rtrim($sourceDir, "/");
  $destDir = rtrim($destDir, "/");
  @mkdir($destDir, 0777);
  $items = getDirectoryListing($sourceDir);
  foreach ($items as $item) {
    if ($item !== "") {
      $sourcePath = $sourceDir . "/" . $item;
      if ($sourcePath !== $destDir) {
        $destPath = $destDir . "/" . $item;
        if (is_dir($sourcePath)) {
          copyDirectoryRecursive($sourcePath, $destPath);
        } else {
          copy($sourcePath, $destPath);
        }
      }
    }
  }
}

function getDirectoryListing(string $dir): array
{
  $listing = [];
  if ($handle = opendir($dir)) {
    while (false !== ($entry = readdir($handle))) {
      if ($entry !== "." && $entry !== "..") {
        $listing[] = $entry;
      }
    }
    closedir($handle);
  }
  return $listing;
}

function readStdinLine(int $maxLength = 255): string
{
  $stdinHandle = fopen("php://stdin", "r");
  $line = fgets($stdinHandle, $maxLength);
  $line = rtrim((string) $line);
  fclose($stdinHandle);
  return $line;
}

function ensureDirectoryExists(string $dirPath): bool
{
  $dirPath = str_replace("\\", "/", $dirPath);
  if (!file_exists($dirPath)) {
    $currentPath = "";
    $parts = explode("/", $dirPath);
    // Initialize result flag as true.
    $created = true;
    foreach ($parts as $part) {
      if ($part === "") {
        continue;
      }
      $currentPath .= $part . "/";
      if (!file_exists($currentPath)) {
        if (!mkdir($currentPath, 0775)) {
          return false;
        }
      }
    }
    return true;
  }
  return true;
}

function sanitizeFilename(string $filename): string
{
  $filename = str_replace(['../', '..\\'], '', $filename);
  return str_replace(
    ["\\", "<", ">", "/", "=", ":", "*", "?", '"', " ", "|"],
    "_",
    $filename
  );
}

function getElapsedTime(array &$startTime): float
{
  $currentTime = gettimeofday();
  $elapsed =
    (float) ($currentTime["sec"] - $startTime["sec"]) +
    (float) (($currentTime["usec"] - $startTime["usec"]) / 1000000);
  return $elapsed;
}
?>
