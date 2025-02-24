<?php
class PlayerSkillProcessor
{
  private array $gameData = [];
  private int $gameCounter = 0;
  private int $roundCounter = 0;
  private array $teamCounts = [];
  private array $playerStats = [];
  private array $translationData = [];
  public array $players_team = [];

  private function getWeaponSkillFactor(string $weaponEvent): float
  {
    if (isset($GLOBALS["skillset"]["weapon_factor"][$weaponEvent])) {
      return (float) $GLOBALS["skillset"]["weapon_factor"][$weaponEvent];
    }
    return 0.0;
  }

  private function getEventSkillFactor(string $eventName): float
  {
    if (isset($GLOBALS["skillset"]["event"][$eventName])) {
      return (float) $GLOBALS["skillset"]["event"][$eventName];
    }
    return 0.0;
  }

  private function getPlayerSkill(string $playerID): float
  {
    global $db;
    $playerID = secureString($playerID); // Add this line
    $sql = "select skill from {$GLOBALS['cfg']['db']['table_prefix']}playerprofile where playerID=$playerID";
    $rs = $db->Execute($sql);
    if ($rs && !$rs->EOF) {
      // Convert comma to dot if needed and cast to float.
      return (float) str_replace(",", ".", $rs->fields[0]);
    }
    return $GLOBALS["skillset"]["defaults"]["value"];
  }

  public function updatePlayerDataField(
    string $action,
    string $playerID,
    string $dataName,
    $value
  ): void {
    if (!isset($this->playerStats[$playerID])) {
      return;
    }
    if ($action === "rep") {
      $this->playerStats[$playerID]["data"][$dataName][0] = $action;
      $this->playerStats[$playerID]["data"][$dataName][1] = $value;
    } elseif ($action === "inc") {
      $this->playerStats[$playerID]["data"][$dataName][0] = $action;
      if (isset($this->playerStats[$playerID]["data"][$dataName][1])) {
        $this->playerStats[$playerID]["data"][$dataName][1] += $value;
      } else {
        $this->playerStats[$playerID]["data"][$dataName][1] = $value;
      }
    } elseif ($action === "avg") {
      $this->playerStats[$playerID]["data"][$dataName][0] = $action;
      if (isset($this->playerStats[$playerID]["data"][$dataName][1])) {
        $this->playerStats[$playerID]["data"][$dataName][1] = round(
          ($value + $this->playerStats[$playerID]["data"][$dataName][1]) / 2.0,
          2
        );
      } else {
        $this->playerStats[$playerID]["data"][$dataName][1] = $value;
      }
    } elseif ($action === "sto") {
      $this->playerStats[$playerID]["data"][$dataName][0] = $action;
      $index = count($this->playerStats[$playerID]["data"][$dataName]);
      $this->playerStats[$playerID]["data"][$dataName][$index] = $value;
    } elseif ($action === "sto_uni") {
      $this->playerStats[$playerID]["data"][$dataName][0] = $action;
      if (!isset($this->playerStats[$playerID]["data"][$dataName][1])) {
        $this->playerStats[$playerID]["data"][$dataName][1] = $value;
      } else {
        $index = count($this->playerStats[$playerID]["data"][$dataName]);
        unset($this->playerStats[$playerID]["data"][$dataName][0]);
        if (
          array_search(
            $value,
            $this->playerStats[$playerID]["data"][$dataName]
          ) === false
        ) {
          $this->playerStats[$playerID]["data"][$dataName][$index] = $value;
        }
        $this->playerStats[$playerID]["data"][$dataName][0] = $action;
      }
    }
  }

  public function resolvePlayerIDConflict(
    string $oldPlayerID,
    string $newPlayerID
  ): void {
    if (!isset($this->playerStats[$oldPlayerID])) {
      return;
    }
    if (isset($this->playerStats[$newPlayerID])) {
      debugPrint("PlayerID Conflict Detected\n");
    }
    foreach ($this->playerStats as $pid => $pdata) {
      if (!isset($pdata["events"])) {
        continue;
      }
      foreach ($pdata["events"] as $round => $roundEvents) {
        foreach ($roundEvents as $team => $teamEvents) {
          foreach ($teamEvents as $role => $eventData) {
            foreach ($eventData as $eventKey => $eventValues) {
              if (!isset($eventValues["2D"])) {
                continue;
              }
              foreach ($eventValues["2D"] as $subKey => $subEvents) {
                foreach ($subEvents as $subRole => $subEventData) {
                  foreach ($subEventData as $conflictID => $value) {
                    if (!isset($conflictID)) {
                      continue;
                    }
                    if (strcmp($conflictID, $oldPlayerID) === 0) {
                      $this->playerStats[$pid]["events"][$round][$team][$role][
                        $eventKey
                      ]["2D"][$subKey][$subRole][$newPlayerID] = $value;
                      unset(
                        $this->playerStats[$pid]["events"][$round][$team][
                          $role
                        ][$eventKey]["2D"][$subKey][$subRole][$oldPlayerID]
                      );
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    $this->playerStats[$newPlayerID] = $this->playerStats[$oldPlayerID];
    unset($this->playerStats[$oldPlayerID]);
  }

  public function updatePlayerName(string $playerID, string $newName): void
  {
    if (!isset($this->playerStats[$playerID])) {
      return;
    }
    $this->playerStats[$playerID]["profile"]["name"] = $newName;
  }

  private function setPlayerIcon(string $playerID, string $icon): void
  {
    if (!isset($this->playerStats[$playerID])) {
      return;
    }
    $this->playerStats[$playerID]["vdata"]["icon"][0] = "";
    $this->playerStats[$playerID]["vdata"]["icon"][1] = $icon;
  }

  public function setPlayerRole(string $playerID, string $role): void
  {
    if (!isset($this->playerStats[$playerID])) {
      return;
    }
    $this->playerStats[$playerID]["vdata"]["role"][0] = "";
    $this->playerStats[$playerID]["vdata"]["role"][1] = $role;
  }

  public function updatePlayerTeam(string $playerID, string $team): void
  {
    if (!isset($this->playerStats[$playerID])) {
      return;
    }
    $this->playerStats[$playerID]["vdata"]["team"][0] = "";
    $this->playerStats[$playerID]["vdata"]["team"][1] = $team;
    if (!isset($this->teamCounts[$team])) {
      $this->teamCounts[$team] = 1;
    }
  }

  private function ensureTeamCount(string $team): void
  {
    if (!isset($this->teamCounts[$team])) {
      $this->teamCounts[$team] = 1;
    }
  }

  private function incrementRoundCounter(): void
  {
    $this->roundCounter++;
  }

  public function setGameData(string $key, $value): void
  {
    if (preg_match("/^_v_/", $key)) {
      $this->gameData[$key] = $value;
    } elseif (
      isset($GLOBALS["cfg"]["data_filter"]["gamedata"][""]) &&
      preg_match($GLOBALS["cfg"]["data_filter"]["gamedata"][""], $key)
    ) {
      return;
    } else {
      $this->gameData[$key] = $value;
    }
  }

  public function initializePlayerData(
    string $playerID,
    string $playerName,
    ?string $ip = null,
    ?string $tld = null
  ): void {
    foreach ($GLOBALS["player_ban_list"] as $banPattern) {
      if (preg_match($banPattern, $playerID)) {
        return;
      }
    }
    if (isset($this->playerStats[$playerID])) {
      return;
    }
    $this->playerStats[$playerID]["v"]["original_id"] = $playerID;
    $this->playerStats[$playerID]["profile"]["name"] = $playerName;
    $this->playerStats[$playerID]["profile"]["ip"] = $ip;
    $this->playerStats[$playerID]["profile"]["tld"] = $tld;
    $skill = $this->getPlayerSkill($playerID);
    $this->playerStats[$playerID]["profile"]["org_skill"] = $this->playerStats[
      $playerID
    ]["profile"]["skill"] = $skill;
    $this->playerStats[$playerID]["profile"]["kills"] = 0;
    $this->playerStats[$playerID]["profile"]["deaths"] = 0;
    $this->playerStats[$playerID]["profile"]["kill_streak"] = 0;
    $this->playerStats[$playerID]["profile"]["kill_streak_counter"] = 0;
    $this->playerStats[$playerID]["profile"]["death_streak"] = 0;
    $this->playerStats[$playerID]["profile"]["death_streak_counter"] = 0;
    $this->playerStats[$playerID]["data"] = [];
    $this->playerStats[$playerID]["vdata"]["team"][0] = "";
    $this->playerStats[$playerID]["vdata"]["team"][1] = "";
    $this->playerStats[$playerID]["vdata"]["role"][0] = "";
    $this->playerStats[$playerID]["vdata"]["role"][1] = "";
    $this->updatePlayerDataField("sto", $playerID, "alias", $playerName);
  }

  public function updatePlayerQuote(string $playerID, string $quote): void
  {
    if (!isset($this->playerStats[$playerID])) {
      return;
    }
    if (preg_match("/\d/", $quote) || preg_match("/@/", $quote)) {
      return;
    }
    $this->playerStats[$playerID]["vdata"]["quote"][0] = "rep";
    if (!isset($this->playerStats[$playerID]["vdata"]["quote"][1])) {
      $this->playerStats[$playerID]["vdata"]["quote"][1] = $quote;
    } elseif (strlen($this->playerStats[$playerID]["vdata"]["quote"][1]) < 5) {
      $this->playerStats[$playerID]["vdata"]["quote"][1] = $quote;
    } elseif (strlen($quote) > 25) {
      $this->playerStats[$playerID]["vdata"]["quote"][1] = $quote;
    } elseif (mt_rand(1, 10) <= 5) {
      $this->playerStats[$playerID]["vdata"]["quote"][1] = $quote;
    }
  }

  public function startGameAnalysis(): void
  {
    $this->clearProcessorData();
    $this->gameCounter++;
    echo "Analyzing game " . sprintf("%04d ", $this->gameCounter);
    flushOutputBuffers();
    $this->roundCounter = 0;
  }

  public function updatePlayerStreaks(): void
  {
    if (isset($this->playerStats)) {
      foreach ($this->playerStats as $playerID => $pdata) {
        if (
          $this->playerStats[$playerID]["profile"]["death_streak_counter"] >
          $this->playerStats[$playerID]["profile"]["death_streak"]
        ) {
          $this->playerStats[$playerID]["profile"]["death_streak"] =
            $this->playerStats[$playerID]["profile"]["death_streak_counter"];
        }
        if (
          $this->playerStats[$playerID]["profile"]["kill_streak_counter"] >
          $this->playerStats[$playerID]["profile"]["kill_streak"]
        ) {
          $this->playerStats[$playerID]["profile"]["kill_streak"] =
            $this->playerStats[$playerID]["profile"]["kill_streak_counter"];
        }
      }
    }
  }

  private function clearPlayerEvents(): void
  {
    foreach ($this->playerStats as $playerID => $pdata) {
      if (isset($this->playerStats[$playerID]["events"])) {
        unset($this->playerStats[$playerID]["events"]);
      }
      // Reset skill and counters
      $this->playerStats[$playerID]["profile"][
        "org_skill"
      ] = $this->playerStats[$playerID]["profile"][
        "skill"
      ] = $this->getPlayerSkill($playerID);
      $this->playerStats[$playerID]["profile"]["kills"] = 0;
      $this->playerStats[$playerID]["profile"]["deaths"] = 0;
      $this->playerStats[$playerID]["profile"]["kill_streak"] = 0;
      $this->playerStats[$playerID]["profile"]["kill_streak_counter"] = 0;
      $this->playerStats[$playerID]["profile"]["death_streak"] = 0;
      $this->playerStats[$playerID]["profile"]["death_streak_counter"] = 0;
      $this->playerStats[$playerID]["data"] = [];
    }
  }

  public function clearProcessorData(): void
  {
    if (isset($this->playerStats)) {
      unset($this->playerStats);
    }
    if (isset($this->gameData)) {
      unset($this->gameData);
    }
    if (isset($this->teamCounts)) {
      unset($this->teamCounts);
    }
    if (isset($this->translationData)) {
      unset($this->translationData);
    }
  }

  /**
   * @return array|false
   */
  public function getPlayerStats(): ?array
  {
    if (isset($this->playerStats)) {
      return $this->playerStats;
    }
    return null;
  }

  /**
   * @return array|false
   */
  public function getGameData(): ?array
  {
    if (isset($this->gameData)) {
      return $this->gameData;
    }
    return null;
  }

  public function updateTeamEventSkill(
    string $team,
    string $eventName,
    float $value
  ): void {
    $eventPrefix = "";
    $eventFull = $eventName;
    if (preg_match("/^(.*)\|(.*)/", $eventFull, $match)) {
      $eventPrefix = $match[1];
      $eventFull = $match[2];
    }
    if (
      isset($GLOBALS["cfg"]["data_filter"]["events"][$eventPrefix]) &&
      preg_match(
        $GLOBALS["cfg"]["data_filter"]["events"][$eventPrefix],
        $eventFull
      )
    ) {
      return;
    }
    if (!$this->playerStats) {
      return;
    }

    $variance = $GLOBALS["skillset"]["defaults"]["variance"];
    $players = ["add" => [], "substract" => []];
    $skills = ["add" => 0.0, "substract" => 0.0];

    foreach ($this->playerStats as $pid => $pdata) {
      $role = $this->playerStats[$pid]["vdata"]["role"][1];
      $inTeam = false;
      if (
        isset($this->playerStats[$pid]["events"][$this->roundCounter][$team])
      ) {
        $inTeam = true;
        if (
          !isset(
            $this->playerStats[$pid]["events"][$this->roundCounter][$team][
              $role
            ]["1D"][$eventName]
          )
        ) {
          $this->playerStats[$pid]["events"][$this->roundCounter][$team][$role][
            "1D"
          ][$eventName] = 0;
        }
        $this->playerStats[$pid]["events"][$this->roundCounter][$team][$role][
          "1D"
        ][$eventName] += $value;
        $players["add"][] = $pid;
        $skills["add"] += $this->playerStats[$pid]["profile"]["org_skill"];
      }
      if (
        !$inTeam ||
        (isset($this->playerStats[$pid]["events"][$this->roundCounter]) &&
          count($this->playerStats[$pid]["events"][$this->roundCounter]) > 1)
      ) {
        $players["substract"][] = $pid;
        $skills["substract"] +=
          $this->playerStats[$pid]["profile"]["org_skill"];
      }
    }
    $n = [
      "add" => count($players["add"]),
      "substract" => count($players["substract"]),
    ];
    $event_factor = $this->getEventSkillFactor($eventName);
    if ($event_factor && $n["add"] && $n["substract"]) {
      $max_n = max($n["add"], $n["substract"]);
      $av_skills = [
        "substract" => $skills["substract"] / $max_n,
        "add" => $skills["add"] / $max_n,
      ];
      $probAddWins =
        1 /
        (1 +
          exp(
            (($av_skills["substract"] - $av_skills["add"]) *
              ($event_factor > 0 ? 1 : -1)) /
              ($variance * $max_n)
          ));
      $factor =
        (1 - $probAddWins) *
        $value *
        $event_factor *
        min($n["add"], $n["substract"]);
      $prob_array = ["add" => [], "substract" => []];
      $prob_sum = ["add" => 0.0, "substract" => 0.0];
      foreach ($players as $type => $plist) {
        $enemy_avg =
          $type === "add" ? $av_skills["substract"] : $av_skills["add"];
        foreach ($plist as $index => $id) {
          $skill = $this->playerStats[$id]["profile"]["org_skill"];
          $prob_win =
            1 /
            (1 +
              exp(
                (($enemy_avg - $skill) * ($event_factor > 0 ? 1 : -1)) /
                  $variance
              ));
          $prob_array[$type][$index] =
            $type === "add" ? 1 - $prob_win : $prob_win;
          $prob_sum[$type] += $prob_array[$type][$index];
        }
      }
      foreach ($players as $type => $plist) {
        $negative = $type === "add" ? 1.0 : -1.0;
        foreach ($plist as $index => $id) {
          $player_factor = $prob_array[$type][$index] / $prob_sum[$type];
          $this->playerStats[$id]["profile"]["skill"] +=
            $negative * $factor * $player_factor;
        }
      }
    }
  }

  // The following methods (updatePlayerEvent, updateAccuracyEvent, processKillEvent,
  // event_skills_update, and launch_skill_events) have been updated similarly:
  public function updatePlayerEvent(
    $playerID,
    string $eventName,
    float $value,
    &$clients_info = false
  ): void {
    if ($clients_info) {
      $client_id = $playerID;
      $playerID = $clients_info[$client_id]["id"];
    }
    if (!isset($this->playerStats[$playerID])) {
      return;
    }
    $eventPrefix = "";
    $eventFull = $eventName;
    if (preg_match("/^(.*)\|(.*)/", $eventFull, $match)) {
      $eventPrefix = $match[1];
      $eventFull = $match[2];
    }
    if (
      isset($GLOBALS["cfg"]["data_filter"]["events"][$eventPrefix]) &&
      preg_match(
        $GLOBALS["cfg"]["data_filter"]["events"][$eventPrefix],
        $eventFull
      )
    ) {
      return;
    }
    $team = $clients_info
      ? $this->players_team[$client_id]["team"]
      : $this->playerStats[$playerID]["vdata"]["team"][1];
    $role = $this->playerStats[$playerID]["vdata"]["role"][1];
    if (
      !isset(
        $this->playerStats[$playerID]["events"][$this->roundCounter][$team][
          $role
        ]["1D"][$eventName]
      )
    ) {
      $this->playerStats[$playerID]["events"][$this->roundCounter][$team][
        $role
      ]["1D"][$eventName] = 0;
    }
    $this->playerStats[$playerID]["events"][$this->roundCounter][$team][$role][
      "1D"
    ][$eventName] += $value;
    $this->event_skills_update(
      $clients_info ? $client_id : $playerID,
      $eventName,
      $value,
      $clients_info
    );
  }

  public function updateAccuracyEvent(
    $playerID1,
    $playerID2,
    string $eventName,
    float $value,
    &$clients_info = false
  ): void {
    if ($clients_info) {
      $first_id = $playerID1;
      $playerID1 = $clients_info[$first_id]["id"];
      $second_id = $playerID2;
      $playerID2 = $clients_info[$second_id]["id"];
    }
    if (
      !isset($this->playerStats[$playerID1]) ||
      !isset($this->playerStats[$playerID2])
    ) {
      return;
    }
    $team1 = $clients_info
      ? $this->players_team[$first_id]["team"]
      : $this->playerStats[$playerID1]["vdata"]["team"][1];
    $role1 = $this->playerStats[$playerID1]["vdata"]["role"][1];
    $team2 = $clients_info
      ? $this->players_team[$second_id]["team"]
      : $this->playerStats[$playerID2]["vdata"]["team"][1];
    $quotedRole2 = $this->playerStats[$playerID2]["vdata"]["role"][1];
    if (
      isset(
        $this->playerStats[$playerID1]["events"][$this->roundCounter][$team1][
          $role1
        ]["2D"][$team2][$quotedRole2][$playerID2][$eventName]
      )
    ) {
      $this->playerStats[$playerID1]["events"][$this->roundCounter][$team1][
        $role1
      ]["2D"][$team2][$quotedRole2][$playerID2][$eventName] += $value;
    } else {
      $this->playerStats[$playerID1]["events"][$this->roundCounter][$team1][
        $role1
      ]["2D"][$team2][$quotedRole2][$playerID2][$eventName] = $value;
    }
    if ($clients_info) {
      $playerID1 = $first_id;
      $playerID2 = $second_id;
    }
    if ($playerID1 == $playerID2) {
      $this->event_skills_update(
        $playerID1,
        $eventName,
        $value,
        $clients_info,
        false
      );
    }
  }

  public function processKillEvent(
    $killer,
    $victim,
    string $weaponEvent,
    &$clients_info = false
  ): void {
    $variance = $GLOBALS["skillset"]["defaults"]["variance"];
    if ($clients_info) {
      $killer_id = $killer;
      $killer = $clients_info[$killer_id]["id"];
      $victim_id = $victim;
      $victim = $clients_info[$victim_id]["id"];
    }
    if (
      !isset($this->playerStats[$killer]) ||
      !isset($this->playerStats[$victim])
    ) {
      return;
    }
    $teamKiller = $clients_info
      ? $this->players_team[$killer_id]["team"]
      : $this->playerStats[$killer]["vdata"]["team"][1];
    $roleKiller = $this->playerStats[$killer]["vdata"]["role"][1];
    $teamVictim = $clients_info
      ? $this->players_team[$victim_id]["team"]
      : $this->playerStats[$victim]["vdata"]["team"][1];
    $roleVictim = $this->playerStats[$victim]["vdata"]["role"][1];

    if (
      $clients_info ? $killer_id != $victim_id : strcmp($killer, $victim) !== 0
    ) {
      if (
        count($this->teamCounts) > 1 &&
        strcmp($teamKiller, $teamVictim) === 0
      ) {
        if (
          !isset(
            $this->playerStats[$killer]["events"][$this->roundCounter][
              $teamKiller
            ][$roleKiller]["2D"][$teamVictim][$roleVictim][$victim][
              "teamkill|$weaponEvent"
            ]
          )
        ) {
          $this->playerStats[$killer]["events"][$this->roundCounter][
            $teamKiller
          ][$roleKiller]["2D"][$teamVictim][$roleVictim][$victim][
            "teamkill|$weaponEvent"
          ] = 0;
        }
        $this->playerStats[$killer]["events"][$this->roundCounter][$teamKiller][
          $roleKiller
        ]["2D"][$teamVictim][$roleVictim][$victim]["teamkill|$weaponEvent"]++;
        $this->event_skills_update(
          $clients_info ? $killer_id : $killer,
          "teamkill|$weaponEvent",
          1,
          $clients_info
        );
      } else {
        $event_factor = $this->getWeaponSkillFactor($weaponEvent);
        $killer_skill = $this->playerStats[$killer]["profile"]["skill"];
        $victim_skill = $this->playerStats[$victim]["profile"]["skill"];
        $prob_killer_wins =
          1 /
          (1 +
            exp(
              (($victim_skill - $killer_skill) * ($event_factor > 0 ? 1 : -1)) /
                $variance
            ));
        $deltaSkill = 1 - $prob_killer_wins;
        if (!isset($this->translationData["first killer"])) {
          $this->updatePlayerEvent(
            $clients_info ? $killer_id : $killer,
            "first killer",
            1,
            $clients_info
          );
          $this->updatePlayerEvent(
            $clients_info ? $victim_id : $victim,
            "first victim",
            1,
            $clients_info
          );
          $this->translationData["first killer"] = 1;
        }
        if (
          !isset(
            $this->playerStats[$killer]["events"][$this->roundCounter][
              $teamKiller
            ][$roleKiller]["2D"][$teamVictim][$roleVictim][$victim][
              "kill|$weaponEvent"
            ]
          )
        ) {
          $this->playerStats[$killer]["events"][$this->roundCounter][
            $teamKiller
          ][$roleKiller]["2D"][$teamVictim][$roleVictim][$victim][
            "kill|$weaponEvent"
          ] = 0;
        }
        $this->playerStats[$killer]["events"][$this->roundCounter][$teamKiller][
          $roleKiller
        ]["2D"][$teamVictim][$roleVictim][$victim]["kill|$weaponEvent"]++;
        $this->playerStats[$killer]["profile"]["kills"]++;
        $this->playerStats[$killer]["profile"]["kill_streak_counter"]++;
        if (
          $this->playerStats[$killer]["profile"]["death_streak_counter"] >
          $this->playerStats[$killer]["profile"]["death_streak"]
        ) {
          $this->playerStats[$killer]["profile"]["death_streak"] =
            $this->playerStats[$killer]["profile"]["death_streak_counter"];
        }
        $this->playerStats[$killer]["profile"]["death_streak_counter"] = 0;
        $this->playerStats[$killer]["profile"]["skill"] +=
          $event_factor * $deltaSkill;
        $this->playerStats[$victim]["profile"]["skill"] -=
          $event_factor * $deltaSkill;
      }
    } else {
      if (
        !isset(
          $this->playerStats[$killer]["events"][$this->roundCounter][
            $teamKiller
          ][$roleKiller]["2D"][$teamVictim][$roleVictim][$victim][
            "suicide|$weaponEvent"
          ]
        )
      ) {
        $this->playerStats[$killer]["events"][$this->roundCounter][$teamKiller][
          $roleKiller
        ]["2D"][$teamVictim][$roleVictim][$victim]["suicide|$weaponEvent"] = 0;
      }
      $this->playerStats[$killer]["events"][$this->roundCounter][$teamKiller][
        $roleKiller
      ]["2D"][$teamVictim][$roleVictim][$victim]["suicide|$weaponEvent"]++;
      $this->event_skills_update(
        $clients_info ? $killer_id : $killer,
        "suicide|$weaponEvent",
        1,
        $clients_info
      );
    }
    $this->playerStats[$victim]["profile"]["deaths"]++;
    $this->playerStats[$victim]["profile"]["death_streak_counter"]++;
    if (
      $this->playerStats[$victim]["profile"]["kill_streak_counter"] >
      $this->playerStats[$victim]["profile"]["kill_streak"]
    ) {
      $this->playerStats[$victim]["profile"]["kill_streak"] =
        $this->playerStats[$victim]["profile"]["kill_streak_counter"];
    }
    $this->playerStats[$victim]["profile"]["kill_streak_counter"] = 0;
  }

  private function event_skills_update(
    $playerID,
    string $eventName,
    float $value,
    &$clients_info = false,
    bool $team_penalty = true
  ): void {
    if ($clients_info) {
      $client_id = $playerID;
      $playerID = $clients_info[$client_id]["id"];
    }
    $team = $clients_info
      ? $this->players_team[$client_id]["team"]
      : $this->playerStats[$playerID]["vdata"]["team"][1];
    $event_factor = $this->getEventSkillFactor($eventName);
    if (!$event_factor) {
      return;
    }
    $player_skill = $this->playerStats[$playerID]["profile"]["skill"];
    $variance = $GLOBALS["skillset"]["defaults"]["variance"];
    $players = [];
    $skills = 0.0;
    $teamplayers = 0;
    if ($clients_info) {
      foreach ($this->players_team as $cl_id => $arr) {
        if ($arr["connected"] && isset($clients_info[$cl_id])) {
          if ($arr["team"] == $team) {
            $teamplayers++;
          } else {
            $id = $clients_info[$cl_id]["id"];
            if (!isset($this->playerStats[$id])) {
              continue;
            }
            $players[] = $id;
            $skills += $this->playerStats[$id]["profile"]["skill"];
          }
        }
      }
    } else {
      foreach ($this->playerStats as $pid => $pdata) {
        if ($this->playerStats[$pid]["vdata"]["team"][1] == $team) {
          $teamplayers++;
        } else {
          $players[] = $pid;
          $skills += $this->playerStats[$pid]["profile"]["skill"];
        }
      }
    }
    $n = count($players);
    if ($n && $teamplayers) {
      $av_skills = $skills / $n;
      $prob_win =
        1 /
        (1 +
          exp(
            (($av_skills - $player_skill) * ($event_factor > 0 ? 1 : -1)) /
              $variance
          ));
      $factor = (1 - $prob_win) * $value * $event_factor;
      if ($team_penalty) {
        $team_factor =
          $event_factor > 0 ? $n / $teamplayers : $teamplayers / $n;
        $factor *= $team_factor > 1 ? 1 : $team_factor;
      }
      $prob_array = [];
      $prob_sum = 0.0;
      foreach ($players as $id) {
        $skill = $this->playerStats[$id]["profile"]["skill"];
        $prob_array[$id] =
          1 /
          (1 +
            exp(
              (($player_skill - $skill) * ($event_factor > 0 ? 1 : -1)) /
                $variance
            ));
        $prob_sum += $prob_array[$id];
      }
      foreach ($players as $id) {
        $player_factor = $prob_array[$id] / $prob_sum;
        $this->playerStats[$id]["profile"]["skill"] -= $factor * $player_factor;
      }
      $this->playerStats[$playerID]["profile"]["skill"] += $factor;
    }
  }

  public function launch_skill_events(): void
  {
    if (isset($this->playerStats)) {
      foreach ($this->playerStats as $playerID => $pdata) {
        if (isset($this->playerStats[$playerID]["profile"]["org_skill"])) {
          $variation =
            $this->playerStats[$playerID]["profile"]["skill"] -
            $this->playerStats[$playerID]["profile"]["org_skill"];
          $this->updatePlayerEvent(
            $playerID,
            "skill|begins",
            round($this->playerStats[$playerID]["profile"]["org_skill"], 2)
          );
          $this->updatePlayerEvent(
            $playerID,
            "skill|" . ($variation > 0 ? "wins" : "loses"),
            round($variation, 2)
          );
          $this->updatePlayerEvent(
            $playerID,
            "skill|ends",
            round($this->playerStats[$playerID]["profile"]["skill"], 2)
          );
        }
      }
    }
  }
}
?>
