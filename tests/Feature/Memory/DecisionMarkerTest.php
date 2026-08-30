<?php

use App\Services\Memory\DecisionMarkerExtractor;

test('decision marker extractor extracts decisions and commitments correctly', function () {
    $extractor = new DecisionMarkerExtractor;

    $text = "decision: Approved the variation order for geotechnical review.\ncommitment: Ir. Tan will inspect the spillway tomorrow.";
    $markers = $extractor->extract($text);

    expect($markers['decisions'])->toContain('Approved the variation order for geotechnical review.');
    expect($markers['commitments'])->toContain('Ir. Tan will inspect the spillway tomorrow.');
});
