<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\SupportPathwayRegistry;

class SupportPathwayController extends Controller
{
    public function index(SupportPathwayRegistry $registry)
    {
        return response()->json([
            'version' => $registry->package()['version'], 'review_status' => 'draft',
            'supplied_pathway_count' => 39, 'additional_intake_pathway_count' => 2,
            'clinical_evidence' => false, 'data' => $registry->catalogue(),
        ]);
    }
}
