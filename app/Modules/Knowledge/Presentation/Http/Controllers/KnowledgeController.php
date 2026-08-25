<?php

namespace App\Modules\Knowledge\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\Knowledge\Domain\Repositories\KnowledgeRepositoryInterface;

class KnowledgeController extends Controller
{
    public function search(Request $request, KnowledgeRepositoryInterface $repository): JsonResponse
    {
        $query = $request->query('q', '');
        $orgId = \App\Support\CurrentOrg::id($request);
        $articles = $repository->searchPublished((int)$orgId, $query);

        return response()->json(['status' => 'success', 'data' => $articles]);
    }
}
