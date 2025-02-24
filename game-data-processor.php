<?php
class GameDataProcessor
{
  public int $games_parsed = 0;
  public int $games_inserted = 0;
  private array $playerStats = [];
  private ?string $gameID = null;
  private array $gameData = [];

  public function __construct() {}

  private function purgeEmptyOneDEvents(): void
  {
    foreach ($this->playerStats as $playerID => $playerData) {
      foreach ($playerData as $key => $value) {
        if ($key === "events") {
          foreach ($value as $round => $roundEvents) {
            foreach ($roundEvents as $team => $teamEvents) {
              foreach ($teamEvents as $role => $eventGroup) {
                $flag = 0;
                if (
                  count($eventGroup["1D"]) <= 1 &&
                  $this->playerStats[$playerID]["profile"]["deaths"] == 0
                ) {
                  $flag = 1;
                  if (array_key_exists("2D", $eventGroup)) {
                    $flag = 0;
                  }
                }
                if ($flag === 1) {
                  unset(
                    $this->playerStats[$playerID]["events"][$round][$team][
                      $role
                    ]
                  );
                }
              }
            }
          }
        }
      }
    }
  }

  private function purifyDatabaseEvents(): void
  {
    global $db;
    $sql = "SELECT count(*) FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata2d";
    $rs = $db->Execute($sql);
    if ($rs && $rs->fields[0] > 10000) {
      $tablesToPurge = [];
      $tablesToPurge["{$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata1d"] = 1;
      $tablesToPurge["{$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata2d"] = 1;
      foreach ($tablesToPurge as $table => $dummy) {
        echo "purifyDb: checking for probable bad entries from $table\n";
        $sql = "SELECT eventCategory, eventName, count(*) as c FROM $table GROUP BY eventCategory, eventName HAVING c < 3";
        $rs = $db->Execute($sql);
        if ($rs && !$rs->EOF) {
          echo "purifyDb: removing probable bad entries from $table\n";
          do {
            $delSql =
              "DELETE FROM $table WHERE eventCategory = " .
              $db->qstr($rs->fields[0]) .
              " AND eventName = " .
              $db->qstr($rs->fields[1]);
            $res = $db->Execute($delSql);
            if ($res) {
              echo "purifyDb: removed: category-{$rs->fields[0]}, name-{$rs->fields[1]}\n";
            }
          } while ($rs->MoveNext() && !$rs->EOF);
        }
        echo "purifyDb: done\n";
      }
    }
  }

  public function generateAwards(): void
  {
    $tp = $GLOBALS["cfg"]["db"]["table_prefix"];
    global $db;
    // Convert exclude list values.
    foreach ($GLOBALS["player_exclude_list"] as $key => $value) {
      $GLOBALS["player_exclude_list"][$key] = $db->qstr($value);
    }
    // Determine last update time.
    $last_update = false;
    if ($GLOBALS["cfg"]["display"]["days_inactivity"]) {
      $sql = "SELECT max(timeStart) FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}gameprofile";
      $rs = $db->execute($sql);
      if ($rs && !$rs->EOF) {
        $last_update = "'{$rs->fields[0]}'";
      } else {
        $sql = "SELECT value FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}gamedata WHERE name = 'last_update_time'";
        $rs = $db->execute($sql);
        if ($rs && !$rs->EOF) {
          $last_update = "'{$rs->fields[0]}'";
        } else {
          $last_update = "CURRENT_TIMESTAMP";
        }
      }
    }
    include_once "pub/games/{$GLOBALS["cfg"]["game"]["name"]}/awardsets/{$GLOBALS["cfg"]["awardset"]}/{$GLOBALS["cfg"]["awardset"]}-awards.php";
    @include_once "pub/games/{$GLOBALS["cfg"]["game"]["name"]}/weaponsets/{$GLOBALS["cfg"]["weaponset"]}/{$GLOBALS["cfg"]["weaponset"]}-weapons.php";
    echo "\ngenerateAwards: Generating Awards...";
    flushOutputBuffers();
    if (!isset($GLOBALS["awardset"])) {
      echo "Award Definitions not found.\n";
      echo " pub/games/{$GLOBALS["cfg"]["game"]["name"]}/awardsets/{$GLOBALS["cfg"]["awardset"]}/{$GLOBALS["cfg"]["awardset"]}-awards.php\n";
      return;
    }
    $awardset_expanded = [];
    $sql = "SELECT distinct eventName FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata2d WHERE eventCategory = 'kill' ORDER BY eventName";
    $rs = $db->Execute($sql);
    foreach ($GLOBALS["awardset"] as $awardKey => $awardData) {
      if (strstr($awardKey, "_v_weapons")) {
        if ($rs) {
          $rs->MoveFirst();
          do {
            $expandedKey = preg_replace(
              "/_v_weapons/",
              $rs->fields[0],
              $awardKey
            );
            if (isset($GLOBALS["awardset"][$awardKey]["name"])) {
              if (isset($weaponset[$rs->fields[0]]["name"])) {
                $awardset_expanded[$expandedKey]["name"] = preg_replace(
                  "/_v_weapons/",
                  $weaponset[$rs->fields[0]]["name"],
                  $GLOBALS["awardset"][$awardKey]["name"]
                );
              } else {
                $awardset_expanded[$expandedKey]["name"] = preg_replace(
                  "/_v_weapons/",
                  ucfirst(strtolower(str_replace("_", " ", $rs->fields[0]))),
                  $GLOBALS["awardset"][$awardKey]["name"]
                );
              }
            }
            if (isset($GLOBALS["awardset"][$awardKey]["image"])) {
              $awardset_expanded[$expandedKey]["image"] = preg_replace(
                "/_v_weapons/",
                $rs->fields[0],
                $GLOBALS["awardset"][$awardKey]["image"]
              );
            }
            if (isset($GLOBALS["awardset"][$awardKey]["category"])) {
              $awardset_expanded[$expandedKey]["category"] = preg_replace(
                "/_v_weapons/",
                $rs->fields[0],
                $GLOBALS["awardset"][$awardKey]["category"]
              );
            }
            foreach (
              $GLOBALS["awardset"][$awardKey]["sql"]
              as $sqlKey => $sqlValue
            ) {
              $awardset_expanded[$expandedKey]["sql"][$sqlKey] = preg_replace(
                "/_v_weapons/",
                $rs->fields[0],
                $GLOBALS["awardset"][$awardKey]["sql"][$sqlKey]
              );
            }
          } while ($rs->MoveNext() && !$rs->EOF);
        }
      } else {
        $awardset_expanded[$awardKey] = $GLOBALS["awardset"][$awardKey];
      }
    }
    $sql = "DELETE FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}awards WHERE 1";
    $db->Execute($sql);
    foreach ($awardset_expanded as $awardKey => $awardData) {
      foreach ($awardset_expanded[$awardKey]["sql"] as $sqlKey => $sqlValue) {
        $sqlValue = preg_replace("/awardset/", "awardset_expanded", $sqlValue);
        eval("\$sqlValue=\"$sqlValue\";");
        $awardset_expanded[$awardKey]["sql_final"] = preg_replace(
          "/\s+/",
          " ",
          $sqlValue
        );
        $rs = $db->Execute($sqlValue);
        $awardset_expanded[$awardKey]["sql"][$sqlKey] = @$rs->fields;
        $awardset_expanded[$awardKey]["result"] =
          $awardset_expanded[$awardKey]["sql"][$sqlKey][0];
      }
      if (
        isset($awardset_expanded[$awardKey]["name"]) &&
        $awardset_expanded[$awardKey]["result"] !== null
      ) {
        if (!isset($awardset_expanded[$awardKey]["category"])) {
          $awardset_expanded[$awardKey]["category"] = "";
        }
        $sql =
          "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}awards SET `sql`='', name='', awardID=" .
          $db->qstr($awardKey);
        $db->Execute($sql);
        $sql =
          "UPDATE {$GLOBALS["cfg"]["db"]["table_prefix"]}awards SET name=" .
          $db->qstr($awardset_expanded[$awardKey]["name"]) .
          ", category=" .
          $db->qstr($awardset_expanded[$awardKey]["category"]) .
          ", image=" .
          $db->qstr($awardset_expanded[$awardKey]["image"]) .
          ", playerID=" .
          $db->qstr($awardset_expanded[$awardKey]["result"]) .
          ", `sql`=" .
          $db->qstr($awardset_expanded[$awardKey]["sql_final"]) .
          " WHERE awardID=" .
          $db->qstr($awardKey);
        $db->Execute($sql);
      }
    }
    echo "done\n";
    flushOutputBuffers();
  }

  public function prune_old_games(): void
  {
    global $db;
    if ($GLOBALS["cfg"]["games_limit"] < 0) {
      return;
    }
    $sql = "SELECT COUNT(*) FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}gamedata WHERE name = '_v_time_start'";
    $rs = $db->Execute($sql);
    if ($rs->fields[0] <= $GLOBALS["cfg"]["games_limit"]) {
      return;
    }
    print "\npruneOldGames: prunning old games...";
    flushOutputBuffers();
    $tables = ["gamedata", "playerdata", "eventdata1d", "eventdata2d"];
    while (true) {
      $gameIDs = [];
      $sql = "SELECT gameID FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}gamedata WHERE name = '_v_time_start' ORDER BY value DESC LIMIT {$GLOBALS["cfg"]["games_limit"]}, 500";
      $rs = $db->Execute($sql);
      if (!$rs || $rs->EOF) {
        break;
      }
      while ($rs && !$rs->EOF) {
        $gameIDs[] = $rs->fields[0];
        $rs->MoveNext();
      }
      foreach ($gameIDs as $gameID) {
        foreach ($tables as $table) {
          $sql = "DELETE FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}$table WHERE gameID = $gameID";
          $db->Execute($sql);
        }
      }
    }
    print "optimizing tables...";
    foreach ($tables as $table) {
      $sql = "OPTIMIZE TABLE {$GLOBALS["cfg"]["db"]["table_prefix"]}$table";
      $db->Execute($sql);
    }
    print "done\n";
    flushOutputBuffers();
  }

  public function storeGameData(?array &$players, ?array &$gameOptions): void
  {
    global $db;
    $this->games_parsed++;
    if (!$players) {
      print "game is empty?, ignored\n";
      flushOutputBuffers();
      return;
    }
    // Batched queries structure.
    $queries = [
      "gamedata" => [
        "sql" => [
          "REPLACE INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}gamedata (gameID, name, value) VALUES ",
        ],
        "queries" => [],
      ],
      "gameprofile" => [
        "sql" => [
          "REPLACE INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}gameprofile (gameID, timeStart) VALUES ",
        ],
      ],
    ];
    $this->playerStats = $players;
    $this->gameData = $gameOptions;
    $this->gameData["_v_players"] = count($this->playerStats);
    if (!isset($this->gameData["_v_players"])) {
      $this->gameData["_v_players"] = "?";
    }
    if (!isset($this->gameData["_v_map"])) {
      $this->gameData["_v_map"] = "?";
    }
    if (!isset($this->gameData["_v_mod"])) {
      $this->gameData["_v_mod"] = "?";
    }
    if (!isset($this->gameData["_v_game"])) {
      $this->gameData["_v_game"] = "?";
    }
    if (!isset($this->gameData["_v_game_type"])) {
      $this->gameData["_v_game_type"] = "?";
    }
    if (!isset($this->gameData["_v_time_start"])) {
      $this->gameData["_v_time_start"] = "1000-01-01 00:00:00";
    }
    // Generate a new gameID (using microtime) ensuring uniqueness.
    do {
      preg_match("/^0\.(\d+) (\d+)/", microtime(), $match);
      $match = $match[2] . $match[1];
    } while ($this->gameID === $match);
    $this->gameID = $match;
    if ($this->gameData) {
      foreach ($this->gameData as $key => $value) {
        $key = $db->qstr($key);
        $value = $db->qstr($value);
        $queries["gamedata"]["queries"][] = "($this->gameID, $key, $value)";
      }
    }
    $qtime = $db->qstr($this->gameData["_v_time_start"]);
    if ($GLOBALS["cfg"]["parser"]["check_unique_gameID"]) {
      $sql = "SELECT gameID FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}gameprofile WHERE timeStart = $qtime LIMIT 1";
      $rs = $db->Execute($sql);
      if ($rs->fields[0]) {
        print "duplicated game timestamp, ignored\n";
        flushOutputBuffers();
        return;
      }
    }
    $queries["gameprofile"]["queries"][] = "($this->gameID, $qtime)";
    print "updating database...";
    flushOutputBuffers();
    foreach ($this->playerStats as $playerID => $stats) {
      // Format skill as a decimal string.
      $this->playerStats[$playerID]["profile"]["skill"] = number_format(
        $this->playerStats[$playerID]["profile"]["skill"],
        4,
        ".",
        ""
      );
      if ($GLOBALS["cfg"]["parser"]["use_original_playerID"]) {
        $stats["v"]["original_id"] = $stats["v"]["original_id"];
      } else {
        $stats["v"]["original_id"] = $playerID;
      }
      $stats["v"]["original_id"] = $db->qstr(
        substr($stats["v"]["original_id"], 0, 99)
      );
      $name = $db->qstr($this->playerStats[$playerID]["profile"]["name"]);
      if (
        isset($GLOBALS["cfg"]["parser"]["use_most_used_playerName"]) &&
        $GLOBALS["cfg"]["parser"]["use_most_used_playerName"] == 1
      ) {
        $sql = sprintf(
          "SELECT dataValue, count(*) as num FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}playerdata WHERE dataName = %s AND playerID = {$stats["v"]["original_id"]} GROUP BY dataValue ORDER BY num DESC",
          $db->qstr("alias")
        );
        $rs = $db->SelectLimit($sql, 1, 0);
        if ($rs && !$rs->EOF) {
          $name = $db->qstr($rs->fields[0]);
        }
      }
      // Get country code.
      if ($GLOBALS["cfg"]["ip2country"]["countries_only"]) {
        $code = $this->playerStats[$playerID]["profile"]["tld"];
        if (@$GLOBALS["cfg"]["parser"]["use_most_used_playerIP"]) {
          $sql = sprintf(
            "SELECT dataValue, count(*) as num FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}playerdata WHERE dataName = 'tld' AND playerID = {$stats["v"]["original_id"]} GROUP BY dataValue ORDER BY num DESC"
          );
          $rs = $db->SelectLimit($sql, 1, 0);
          if ($rs && !$rs->EOF) {
            $code = $rs->fields[0];
          }
        }
        if ($code) {
          $countryCode = $db->qstr($code);
        }
      } else {
        $ip = $this->playerStats[$playerID]["profile"]["ip"];
        if ($GLOBALS["cfg"]["parser"]["use_most_used_playerIP"]) {
          $sql = sprintf(
            "SELECT dataValue, count(*) as num FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}playerdata WHERE dataName = 'ip' AND playerID = {$stats["v"]["original_id"]} GROUP BY dataValue ORDER BY num DESC"
          );
          $rs = $db->SelectLimit($sql, 1, 0);
          if ($rs && !$rs->EOF) {
            $ip = $rs->fields[0];
          }
        }
        $ip_number = sprintf("%u", ip2long($ip));
        $sql = "SELECT country_code2 FROM {$GLOBALS["cfg"]["db"]["table_prefix"]}ip2country WHERE $ip_number BETWEEN ip_from AND ip_to";
        $rs = $db->Execute($sql);
        if ($rs->fields[0]) {
          $countryCode = $db->qstr($rs->fields[0]);
        }
      }
      if (!@$countryCode || $countryCode == "''") {
        $countryCode = $db->qstr("XX");
      }
      // Prepare playerprofile query.
      if (!isset($queries["playerprofile"])) {
        $queries["playerprofile"] = [
          "sql" => [
            "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}playerprofile (playerID, playerName, countryCode,
                            skill, kills, deaths, games, kill_streak, death_streak, first_seen, last_seen) VALUES ",
            " ON DUPLICATE KEY UPDATE playerName = VALUES(playerName), countryCode = VALUES(countryCode), skill = VALUES(skill),
                            kills = kills + VALUES(kills), deaths = deaths + VALUES(deaths), games = games + VALUES(games),
                            kill_streak = IF(VALUES(kill_streak) > kill_streak, VALUES(kill_streak), kill_streak),
                            death_streak = IF(VALUES(death_streak) > death_streak, VALUES(death_streak), death_streak),
                            first_seen = IF(VALUES(first_seen) < first_seen, VALUES(first_seen), first_seen),
                            last_seen = IF(VALUES(last_seen) > last_seen, VALUES(last_seen), last_seen)",
          ],
          "queries" => [],
        ];
      }
      $queries["playerprofile"][
        "queries"
      ][] = "({$stats["v"]["original_id"]}, $name, $countryCode,
                    {$this->playerStats[$playerID]["profile"]["skill"]}, {$this->playerStats[$playerID]["profile"]["kills"]},
                    {$this->playerStats[$playerID]["profile"]["deaths"]}, 1, {$this->playerStats[$playerID]["profile"]["kill_streak"]},
                    {$this->playerStats[$playerID]["profile"]["death_streak"]}, '{$this->gameData["_v_time_start"]}',
                    '{$this->gameData["_v_time_start"]}')";
      $dataIndexes = [];
      foreach ($stats as $key => $dataGroup) {
        if ($key === "data" || $key === "vdata") {
          foreach ($dataGroup as $dataName => $dataArray) {
            $dataValue = $dataArray[1];
            $dataName = $db->qstr($dataName);
            $action = $dataArray[0];
            if ($action === "rep" || $action === "inc" || $action === "avg") {
              $dataArray[1] = $db->qstr($dataArray[1]);
              if (!isset($queries["playerdata$action"])) {
                $aux =
                  $action === "rep"
                    ? "VALUES(dataValue)"
                    : ($action === "inc"
                      ? "dataValue+VALUES(dataValue)"
                      : "round((dataValue+VALUES(dataValue))/2.0,2.0)");
                $queries["playerdata$action"] = [
                  "sql" => [
                    "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}playerdata (playerID, gameID, dataName, dataNo, dataValue) VALUES ",
                    " ON DUPLICATE KEY UPDATE dataValue = $aux",
                  ],
                  "queries" => [],
                ];
              }
              $queries["playerdata$action"][
                "queries"
              ][] = "({$stats["v"]["original_id"]}, 0, $dataName, 0, {$dataArray[1]})";
            } elseif ($action === "sto") {
              unset($dataArray[0]);
              foreach ($dataArray as $index => $value) {
                if (!isset($dataIndexes[$dataName])) {
                  $dataIndexes[$dataName] = 0;
                }
                $dataNo = $dataIndexes[$dataName]++;
                if (!isset($queries["playerdata"])) {
                  $queries["playerdata"] = [
                    "sql" => [
                      "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}playerdata (playerID, gameID, dataName, dataNo, dataValue) VALUES ",
                    ],
                    "queries" => [],
                  ];
                }
                if (!isset($queries["playerdata_total"])) {
                  $queries["playerdata_total"] = [
                    "sql" => [
                      "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}playerdata_total (playerID, dataName, dataValue, dataCount) VALUES ",
                      " ON DUPLICATE KEY UPDATE dataCount = dataCount + 1",
                    ],
                    "queries" => [],
                  ];
                }
                $value = $db->qstr($value);
                $queries["playerdata"][
                  "queries"
                ][] = "({$stats["v"]["original_id"]}, $this->gameID, $dataName, $dataNo, $value)";
                $queries["playerdata_total"][
                  "queries"
                ][] = "({$stats["v"]["original_id"]}, $dataName, $value, 1)";
              }
            }
          }
        } elseif ($key === "events") {
          foreach ($dataGroup as $round => $roundEvents) {
            foreach ($roundEvents as $team => $teamEvents) {
              $quotedTeam = $db->qstr($team);
              foreach ($teamEvents as $role => $eventGroup) {
                $quotedRole = $db->qstr($role);
                foreach ($eventGroup as $eventName => $eventData) {
                  if ($eventName === "1D") {
                    foreach ($eventData as $eventName2 => $eventValue) {
                      $eventCategory = "";
                      if (preg_match("/^(.*)\|(.*)/", $eventName2, $matches)) {
                        $eventCategory = $matches[1];
                        $eventName2 = $matches[2];
                      }
                      $eventName2 = $db->qstr($eventName2);
                      $eventValue = $db->qstr(
                        is_float($eventValue)
                          ? number_format($eventValue, 2, ".", "")
                          : $eventValue
                      );
                      $eventCategory = $db->qstr($eventCategory);
                      if (!isset($queries["eventdata1d"])) {
                        $queries["eventdata1d"] = [
                          "sql" => [
                            "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata1d (playerID, gameID, round, team, role, eventName, eventCategory, eventValue) VALUES ",
                          ],
                          "queries" => [],
                        ];
                      }
                      $queries["eventdata1d"][
                        "queries"
                      ][] = "({$stats["v"]["original_id"]}, $this->gameID, $round, $quotedTeam, $quotedRole, $eventName2, $eventCategory, $eventValue)";
                      if ($eventCategory === "'skill'") {
                        if (
                          $eventName2 === "'begins'" ||
                          $eventName2 === "'ends'"
                        ) {
                          if (!isset($queries["eventdata1d_minskill"])) {
                            $queries["eventdata1d_minskill"] = [
                              "sql" => [
                                "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata1d_total (playerID, eventCategory, eventName, eventValue) VALUES ",
                                " ON DUPLICATE KEY UPDATE eventValue = IF(0+eventValue < 0+VALUES(eventValue), eventValue, VALUES(eventValue))",
                              ],
                              "queries" => [],
                            ];
                            $queries["eventdata1d_maxskill"] = [
                              "sql" => [
                                "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata1d_total (playerID, eventCategory, eventName, eventValue) VALUES ",
                                " ON DUPLICATE KEY UPDATE eventValue = IF(0+eventValue > 0+VALUES(eventValue), eventValue, VALUES(eventValue))",
                              ],
                              "queries" => [],
                            ];
                          }
                          $queries["eventdata1d_minskill"][
                            "queries"
                          ][] = "({$stats["v"]["original_id"]}, 'skill', 'min', $eventValue)";
                          $queries["eventdata1d_maxskill"][
                            "queries"
                          ][] = "({$stats["v"]["original_id"]}, 'skill', 'max', $eventValue)";
                        }
                        continue;
                      }
                      if (!isset($queries["eventdata1d_total"])) {
                        $queries["eventdata1d_total"] = [
                          "sql" => [
                            "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata1d_total (playerID, eventCategory, eventName, eventValue) VALUES ",
                            " ON DUPLICATE KEY UPDATE eventValue = eventValue + VALUES(eventValue)",
                          ],
                          "queries" => [],
                        ];
                      }
                      $queries["eventdata1d_total"][
                        "queries"
                      ][] = "({$stats["v"]["original_id"]}, $eventCategory, $eventName2, $eventValue)";
                    }
                  } elseif ($eventName === "2D") {
                    foreach ($eventData as $eventName2 => $eventValue) {
                      $quotedEventName2 = $db->qstr($eventName2);
                      foreach ($eventValue as $role2 => $opponents) {
                        $quotedRole2 = $db->qstr($role2);
                        foreach (
                          $opponents
                          as $opponentName => $opponentEvent
                        ) {
                          $player2ID = $GLOBALS["cfg"]["parser"][
                            "use_original_playerID"
                          ]
                            ? $this->playerStats[$opponentName]["v"][
                              "original_id"
                            ]
                            : $opponentName;
                          $player2ID = $db->qstr(substr($player2ID, 0, 99));
                          foreach (
                            $opponentEvent
                            as $eventName3 => $eventValue
                          ) {
                            $eventCategory = "";
                            if (
                              preg_match("/^(.*)\|(.*)/", $eventName3, $matches)
                            ) {
                              $eventCategory = $matches[1];
                              $eventName3 = $matches[2];
                            }
                            $eventName3 = $db->qstr($eventName3);
                            $eventValue = $db->qstr($eventValue);
                            $eventCategory = $db->qstr($eventCategory);
                            if (!isset($queries["eventdata2d"])) {
                              $queries["eventdata2d"] = [
                                "sql" => [
                                  "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata2d (playerID, gameID, round, team, role, eventName, eventCategory, eventValue, player2ID, team2, role2) VALUES ",
                                ],
                                "queries" => [],
                              ];
                            }
                            if (!isset($queries["eventdata2d_total"])) {
                              $queries["eventdata2d_total"] = [
                                "sql" => [
                                  "INSERT INTO {$GLOBALS["cfg"]["db"]["table_prefix"]}eventdata2d_total (playerID, player2ID, eventCategory, eventName, eventValue) VALUES ",
                                  " ON DUPLICATE KEY UPDATE eventValue = eventValue + VALUES(eventValue)",
                                ],
                                "queries" => [],
                              ];
                            }
                            $queries["eventdata2d"][
                              "queries"
                            ][] = "({$stats["v"]["original_id"]}, $this->gameID, $round, $quotedTeam, $quotedRole, $eventName3, $eventCategory, $eventValue, $player2ID, $quotedEventName2, $quotedRole2)";
                            $queries["eventdata2d_total"][
                              "queries"
                            ][] = "({$stats["v"]["original_id"]}, $player2ID, $eventCategory, $eventName3, $eventValue)";
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    $extraData["last update time"] = date("Y-m-d H:i:s");
    $extraData["vsp version"] = constant("cVERSION");
    foreach ($extraData as $extraKey => $extraValue) {
      $name = $db->qstr($extraKey);
      $value = $db->qstr($extraValue);
      $queries["gamedata"]["queries"][] = "(0, $name, $value)";
    }
    // Execute batched queries.
    foreach ($queries as $query) {
      $sql = $query["sql"][0] . implode(", ", $query["queries"]);
      if (isset($query["sql"][1])) {
        $sql .= $query["sql"][1];
      }
      $db->Execute($sql);
      $err = $db->ErrorMsg();
      if ($err) {
        die("\n\nError: $err\nQuery: $sql");
      }
    }
    unset($queries);
    print "done\n";
    $this->games_inserted++;
    if (
      $GLOBALS["cfg"]["games_limit"] >= 0 &&
      $this->games_inserted % 500 === 0
    ) {
      $this->prune_old_games();
    }
    flushOutputBuffers();
  }
}
?>
