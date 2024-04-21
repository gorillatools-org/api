<?php
class KeyInfo {
    private $devSecretKey;
    public $http;
    private $titleId;

    public function __construct($devSecretKey, $titleId = null) {
        $this->titleId = $titleId;
        $this->devSecretKey = $devSecretKey;
        $this->http = new HttpClient();
        $this->http->setDefaultOption('headers', [
            'X-SecretKey' => $this->devSecretKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);
    }

    public function setTitleId($t) { $this->titleId = $t; }
    public function getTitleId() { return !empty($this->titleId) ? $this->titleId : null; }
    private function playfabapi() { return "https://{$this->titleId}.playfabapi.com"; }

    public function isKeyValid(): bool {
        try {
            $r = $this->http->post('https://fuckyou.playfabapi.com/Admin/GetCloudScriptRevision');
            if ($r->getStatusCode() == 400) {
                $d = json_decode($r->getBody());

                if ($d->error == "NotAuthenticated" && $d->errorCode == 1074) {
                    return false;
                } else if ($d->error == "DAULimitExceeded" && $d->errorCode == 1129) {
                    die("DAULimitExceeded");
                    return false;
                } elseif ($d != null && $d->errorCode == 1131 && !empty($d->errorMessage)) {
                    $p = explode("https://", $d->errorMessage);
                    if (count($p) >= 2) {
                        $sp = explode(".api.main.azureplayfab.com", $p[1]);
                        if (count($sp) >= 2 && preg_match("/^\w{5,6}$/", $sp[0])) {
                            $this->setTitleId($sp[0]);
                            return true;
                        }
                    }
                } else {
                    die($r->getBody());
                }
            }
        } catch (Exception $ex) {
            die($ex->getMessage()); // you MUst debug this urself!! :-)
            return false;
        }

        return false;
    }

    public function getSegIdOf($segmentName) {
        try {
            $r = $this->http->post($this->playfabapi().'/Admin/GetAllSegments');
            if ($r->getStatusCode() == 200) {
                $d = json_decode($r->getBody());
                foreach ($d->data->Segments as $s) {
                    if ($s->Name == $segmentName) {
                        return $s->Id;
                    }
                }
            }
        } catch (Exception $ex) {
            die($ex->getMessage()); // you MUst debug this urself!! :-)
        }
        return null;
    }

    public function getAllSegments() {
        try {
            $r = $this->http->post($this->playfabapi() . '/Admin/GetAllSegments');

            if ($r->getStatusCode() == 200) {
                $d = json_decode($r->getBody());
                $segments = array();

                foreach ($d->data->Segments as $s) {
                    $segments[] = (object) [
                        'id' => $s->Id,
                        'name' => $s->Name
                    ];
                }

                return $segments;
            }
        } catch (Exception $ex) {
            die($ex->getMessage());
        }

        return array();
    }

    public function profilesInSegment($segmentId): int {
        try {
            $r = $this->http->post($this->playfabapi().'/Admin/GetPlayersInSegment', json_encode([
                'SegmentId' => $segmentId,
                'MaxBatchSize' => 0,
            ]));
    
            if ($r->getStatusCode() == 200) {
                $d = json_decode($r->getBody());
                if ($d->data != null) {
                    return $d->data->ProfilesInSegment;
                } else {
                    return 0;
                }
            } else {
                die("Error: " . $r->getStatusCode() . " - " . $r->getBody());
            }
        } catch (Exception $ex) {
            return 0;
        }
        return 0;
    }

    public function hasDefaultCloudScript() {
        try {
            $r = $this->http->post($this->playfabapi().'/Admin/GetCloudScriptRevision');
    
            if ($r->getStatusCode() == 200) {
                $d = json_decode($r->getBody());
                if ($d->data != null && $d->code == 200 || $d->status == "OK") {
                    return $d->data->Files[0]->FileContents == "///////////////////////////////////////////////////////////////////////////////////////////////////////\n//\n// Welcome to your first Cloud Script revision!\n//\n// Cloud Script runs in the PlayFab cloud and has full access to the PlayFab Game Server API \n// (https://api.playfab.com/Documentation/Server), and it runs in the context of a securely\n// authenticated player, so you can use it to implement logic for your game that is safe from\n// client-side exploits. \n//\n// Cloud Script functions can also make web requests to external HTTP\n// endpoints, such as a database or private API for your title, which makes them a flexible\n// way to integrate with your existing backend systems.\n//\n// There are several different options for calling Cloud Script functions:\n//\n// 1) Your game client calls them directly using the \"ExecuteCloudScript\" API,\n// passing in the function name and arguments in the request and receiving the \n// function return result in the response.\n// (https://api.playfab.com/Documentation/Client/method/ExecuteCloudScript)\n// \n// 2) You create PlayStream event actions that call them when a particular \n// event occurs, passing in the event and associated player profile data.\n// (https://api.playfab.com/playstream/docs)\n// \n// 3) For titles using the Photon Add-on (https://playfab.com/marketplace/photon/),\n// Photon room events trigger webhooks which call corresponding Cloud Script functions.\n// \n// The following examples demonstrate all three options.\n//\n///////////////////////////////////////////////////////////////////////////////////////////////////////\n\n\n// This is a Cloud Script function. \"args\" is set to the value of the \"FunctionParameter\" \n// parameter of the ExecuteCloudScript API.\n// (https://api.playfab.com/Documentation/Client/method/ExecuteCloudScript)\n// \"context\" contains additional information when the Cloud Script function is called from a PlayStream action.\nhandlers.helloWorld = function (args, context) {\n    \n    // The pre-defined \"currentPlayerId\" variable is initialized to the PlayFab ID of the player logged-in on the game client. \n    // Cloud Script handles authenticating the player automatically.\n    var message = \"Hello \" + currentPlayerId + \"!\";\n\n    // You can use the \"log\" object to write out debugging statements. It has\n    // three functions corresponding to logging level: debug, info, and error. These functions\n    // take a message string and an optional object.\n    log.info(message);\n    var inputValue = null;\n    if (args && args.inputValue)\n        inputValue = args.inputValue;\n    log.debug(\"helloWorld:\", { input: args.inputValue });\n\n    // The value you return from a Cloud Script function is passed back \n    // to the game client in the ExecuteCloudScript API response, along with any log statements\n    // and additional diagnostic information, such as any errors returned by API calls or external HTTP\n    // requests. They are also included in the optional player_executed_cloudscript PlayStream event \n    // generated by the function execution.\n    // (https://api.playfab.com/playstream/docs/PlayStreamEventModels/player/player_executed_cloudscript)\n    return { messageValue: message };\n};\n\n// This is a simple example of making a PlayFab server API call\nhandlers.makeAPICall = function (args, context) {\n    var request = {\n        PlayFabId: currentPlayerId, Statistics: [{\n                StatisticName: \"Level\",\n                Value: 2\n            }]\n    };\n    // The pre-defined \"server\" object has functions corresponding to each PlayFab server API \n    // (https://api.playfab.com/Documentation/Server). It is automatically \n    // authenticated as your title and handles all communication with \n    // the PlayFab API, so you don't have to write extra code to issue HTTP requests. \n    var playerStatResult = server.UpdatePlayerStatistics(request);\n};\n\n// This an example of a function that calls a PlayFab Entity API. The function is called using the \n// 'ExecuteEntityCloudScript' API (https://api.playfab.com/documentation/CloudScript/method/ExecuteEntityCloudScript).\nhandlers.makeEntityAPICall = function (args, context) {\n\n    // The profile of the entity specified in the 'ExecuteEntityCloudScript' request.\n    // Defaults to the authenticated entity in the X-EntityToken header.\n    var entityProfile = context.currentEntity;\n\n    // The pre-defined 'entity' object has functions corresponding to each PlayFab Entity API,\n    // including 'SetObjects' (https://api.playfab.com/documentation/Data/method/SetObjects).\n    var apiResult = entity.SetObjects({\n        Entity: entityProfile.Entity,\n        Objects: [\n            {\n                ObjectName: \"obj1\",\n                DataObject: {\n                    foo: \"some server computed value\",\n                    prop1: args.prop1\n                }\n            }\n        ]\n    });\n\n    return {\n        profile: entityProfile,\n        setResult: apiResult.SetResults[0].SetResult\n    };\n};\n\n// This is a simple example of making a web request to an external HTTP API.\nhandlers.makeHTTPRequest = function (args, context) {\n    var headers = {\n        \"X-MyCustomHeader\": \"Some Value\"\n    };\n    \n    var body = {\n        input: args,\n        userId: currentPlayerId,\n        mode: \"foobar\"\n    };\n\n    var url = \"http://httpbin.org/status/200\";\n    var content = JSON.stringify(body);\n    var httpMethod = \"post\";\n    var contentType = \"application/json\";\n\n    // The pre-defined http object makes synchronous HTTP requests\n    var response = http.request(url, httpMethod, content, contentType, headers);\n    return { responseContent: response };\n};\n\n// This is a simple example of a function that is called from a\n// PlayStream event action. (https://playfab.com/introducing-playstream/)\nhandlers.handlePlayStreamEventAndProfile = function (args, context) {\n    \n    // The event that triggered the action \n    // (https://api.playfab.com/playstream/docs/PlayStreamEventModels)\n    var psEvent = context.playStreamEvent;\n    \n    // The profile data of the player associated with the event\n    // (https://api.playfab.com/playstream/docs/PlayStreamProfileModels)\n    var profile = context.playerProfile;\n    \n    // Post data about the event to an external API\n    var content = JSON.stringify({ user: profile.PlayerId, event: psEvent.EventName });\n    var response = http.request('https://httpbin.org/status/200', 'post', content, 'application/json', null);\n\n    return { externalAPIResponse: response };\n};\n\n\n// Below are some examples of using Cloud Script in slightly more realistic scenarios\n\n// This is a function that the game client would call whenever a player completes\n// a level. It updates a setting in the player's data that only game server\n// code can write - it is read-only on the client - and it updates a player\n// statistic that can be used for leaderboards. \n//\n// A funtion like this could be extended to perform validation on the \n// level completion data to detect cheating. It could also do things like \n// award the player items from the game catalog based on their performance.\nhandlers.completedLevel = function (args, context) {\n    var level = args.levelName;\n    var monstersKilled = args.monstersKilled;\n    \n    var updateUserDataResult = server.UpdateUserInternalData({\n        PlayFabId: currentPlayerId,\n        Data: {\n            lastLevelCompleted: level\n        }\n    });\n\n    log.debug(\"Set lastLevelCompleted for player \" + currentPlayerId + \" to \" + level);\n    var request = {\n        PlayFabId: currentPlayerId, Statistics: [{\n                StatisticName: \"level_monster_kills\",\n                Value: monstersKilled\n            }]\n    };\n    server.UpdatePlayerStatistics(request);\n    log.debug(\"Updated level_monster_kills stat for player \" + currentPlayerId + \" to \" + monstersKilled);\n};\n\n\n// In addition to the Cloud Script handlers, you can define your own functions and call them from your handlers. \n// This makes it possible to share code between multiple handlers and to improve code organization.\nhandlers.updatePlayerMove = function (args) {\n    var validMove = processPlayerMove(args);\n    return { validMove: validMove };\n};\n\n\n// This is a helper function that verifies that the player's move wasn't made\n// too quickly following their previous move, according to the rules of the game.\n// If the move is valid, then it updates the player's statistics and profile data.\n// This function is called from the \"UpdatePlayerMove\" handler above and also is \n// triggered by the \"RoomEventRaised\" Photon room event in the Webhook handler\n// below. \n//\n// For this example, the script defines the cooldown period (playerMoveCooldownInSeconds)\n// as 15 seconds. A recommended approach for values like this would be to create them in Title\n// Data, so that they can be queries in the script with a call to GetTitleData\n// (https://api.playfab.com/Documentation/Server/method/GetTitleData). This would allow you to\n// make adjustments to these values over time, without having to edit, test, and roll out an\n// updated script.\nfunction processPlayerMove(playerMove) {\n    var now = Date.now();\n    var playerMoveCooldownInSeconds = 15;\n\n    var playerData = server.GetUserInternalData({\n        PlayFabId: currentPlayerId,\n        Keys: [\"last_move_timestamp\"]\n    });\n\n    var lastMoveTimestampSetting = playerData.Data[\"last_move_timestamp\"];\n\n    if (lastMoveTimestampSetting) {\n        var lastMoveTime = Date.parse(lastMoveTimestampSetting.Value);\n        var timeSinceLastMoveInSeconds = (now - lastMoveTime) / 1000;\n        log.debug(\"lastMoveTime: \" + lastMoveTime + \" now: \" + now + \" timeSinceLastMoveInSeconds: \" + timeSinceLastMoveInSeconds);\n\n        if (timeSinceLastMoveInSeconds < playerMoveCooldownInSeconds) {\n            log.error(\"Invalid move - time since last move: \" + timeSinceLastMoveInSeconds + \"s less than minimum of \" + playerMoveCooldownInSeconds + \"s.\");\n            return false;\n        }\n    }\n\n    var playerStats = server.GetPlayerStatistics({\n        PlayFabId: currentPlayerId\n    }).Statistics;\n    var movesMade = 0;\n    for (var i = 0; i < playerStats.length; i++)\n        if (playerStats[i].StatisticName === \"\")\n            movesMade = playerStats[i].Value;\n    movesMade += 1;\n    var request = {\n        PlayFabId: currentPlayerId, Statistics: [{\n                StatisticName: \"movesMade\",\n                Value: movesMade\n            }]\n    };\n    server.UpdatePlayerStatistics(request);\n    server.UpdateUserInternalData({\n        PlayFabId: currentPlayerId,\n        Data: {\n            last_move_timestamp: new Date(now).toUTCString(),\n            last_move: JSON.stringify(playerMove)\n        }\n    });\n\n    return true;\n}\n\n// This is an example of using PlayStream real-time segmentation to trigger\n// game logic based on player behavior. (https://playfab.com/introducing-playstream/)\n// The function is called when a player_statistic_changed PlayStream event causes a player \n// to enter a segment defined for high skill players. It sets a key value in\n// the player's internal data which unlocks some new content for the player.\nhandlers.unlockHighSkillContent = function (args, context) {\n    var playerStatUpdatedEvent = context.playStreamEvent;\n    var request = {\n        PlayFabId: currentPlayerId,\n        Data: {\n            \"HighSkillContent\": \"true\",\n            \"XPAtHighSkillUnlock\": playerStatUpdatedEvent.StatisticValue.toString()\n        }\n    };\n    var playerInternalData = server.UpdateUserInternalData(request);\n    log.info('Unlocked HighSkillContent for ' + context.playerProfile.DisplayName);\n    return { profile: context.playerProfile };\n};\n\n// Photon Webhooks Integration\n//\n// The following functions are examples of Photon Cloud Webhook handlers. \n// When you enable the Photon Add-on (https://playfab.com/marketplace/photon/)\n// in the Game Manager, your Photon applications are automatically configured\n// to authenticate players using their PlayFab accounts and to fire events that \n// trigger your Cloud Script Webhook handlers, if defined. \n// This makes it easier than ever to incorporate multiplayer server logic into your game.\n\n\n// Triggered automatically when a Photon room is first created\nhandlers.RoomCreated = function (args) {\n    log.debug(\"Room Created - Game: \" + args.GameId + \" MaxPlayers: \" + args.CreateOptions.MaxPlayers);\n};\n\n// Triggered automatically when a player joins a Photon room\nhandlers.RoomJoined = function (args) {\n    log.debug(\"Room Joined - Game: \" + args.GameId + \" PlayFabId: \" + args.UserId);\n};\n\n// Triggered automatically when a player leaves a Photon room\nhandlers.RoomLeft = function (args) {\n    log.debug(\"Room Left - Game: \" + args.GameId + \" PlayFabId: \" + args.UserId);\n};\n\n// Triggered automatically when a Photon room closes\n// Note: currentPlayerId is undefined in this function\nhandlers.RoomClosed = function (args) {\n    log.debug(\"Room Closed - Game: \" + args.GameId);\n};\n\n// Triggered automatically when a Photon room game property is updated.\n// Note: currentPlayerId is undefined in this function\nhandlers.RoomPropertyUpdated = function (args) {\n    log.debug(\"Room Property Updated - Game: \" + args.GameId);\n};\n\n// Triggered by calling \"OpRaiseEvent\" on the Photon client. The \"args.Data\" property is \n// set to the value of the \"customEventContent\" HashTable parameter, so you can use\n// it to pass in arbitrary data.\nhandlers.RoomEventRaised = function (args) {\n    var eventData = args.Data;\n    log.debug(\"Event Raised - Game: \" + args.GameId + \" Event Type: \" + eventData.eventType);\n\n    switch (eventData.eventType) {\n        case \"playerMove\":\n            processPlayerMove(eventData);\n            break;\n\n        default:\n            break;\n    }\n};\n";
                } else {
                    return false;
                }
            } else {
                die("Error: " . $r->getStatusCode() . " - " . $r->getBody());
            }
        } catch (Exception $ex) {
            return false;
        }
        return false;
    }
}
?>
