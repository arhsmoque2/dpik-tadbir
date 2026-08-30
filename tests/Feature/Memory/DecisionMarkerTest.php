<?php

use App\Services\Memory\DecisionMarkerExtractor;

test('decision marker extractor extracts decisions and commitments correctly', function () {
    $extractor = new DecisionMarkerExtractor;

    $text = "We agreed to submit Claim 4 on Thursday. [dm:decision] Contract variation approved for RM 120,000. [dm:commitment] Ir. Tan will inspect the spillway tomorrow.";
    $markers = $extractor->extract($text);

    expect($markers)->toContain('dm:decision');
    expect($markers)->toContain('dm:commitment');

    $inferred = $extractor->inferFromText("Approved the variation order for geotechnical review.");
    expect($inferred)->toBeArray();
});
