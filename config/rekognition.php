<?php

return [
    'region' => env('REKOGNITION_REGION', 'us-east-2'),
    'collection' => env('REKOGNITION_COLLECTION', 'campeonato-teste'),
    'threshold' => max(0, min(100, (float) env('REKOGNITION_THRESHOLD', 95))),
];
