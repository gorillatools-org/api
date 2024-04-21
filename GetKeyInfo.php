<?php

include 'hc.php';
include 'KeyInfo.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $devSecretKey = filter_input(INPUT_GET, 'devSecretKey', FILTER_SANITIZE_STRING);

    if (empty($devSecretKey)) {
        die("devSecretKey param is required. see <a href=https://github.com/gorillatools-org/api/blob/main/GetKeyInfo.php>the source on github</a>");
    } else {
        $keyInfo = new KeyInfo($devSecretKey);
        if ($keyInfo->isKeyValid()) {
            header("Content-Type: application/json");
            $allSegments = $keyInfo->getAllSegments();
            $title = $keyInfo->getTitleId();
            $hasDefaultCS = $keyInfo->hasDefaultCloudScript();
            
            $arr = $devSecretKey;
            $r = array($devSecretKey => array());
            if (isset($title)) {
                $r[$arr]["title_id"] = $title;
            }

            if (isset($allSegments)) {
                $r[$arr]["segments"] = $allSegments;
            }

            if (isset($hasDefaultCS)) {
                $r[$arr]["uses_default_cloudscript"] = $hasDefaultCS;
            }

            if (isset($allSegments)) {
                $r[$arr]["total_segments"] = count($allSegments);
                $segments = [];

                foreach ($allSegments as $segment) {
                    $profilesInSeg = $keyInfo->profilesInSegment($segment->id);
                    $sa = (array) $segment;
                    $sa["profiles_in_segment"] = $profilesInSeg;
                    $segments[] = (object) $sa;
                }

                $r[$arr]["segments"] = $segments;
            }

            if (empty($r[$arr])) {
                $r[$arr][] = (object)["empty" => "nothing was appended."];
            }

            echo json_encode($r);
        } else {
            http_response_code(401);
            die("invalid_key");
        }
    }
} else {
    http_response_code(400);
    header("Content-Type: application/json");
    die(json_encode(['error' => 'bad request. method must be GET and Content-Type must be application/json']));
}
?>
