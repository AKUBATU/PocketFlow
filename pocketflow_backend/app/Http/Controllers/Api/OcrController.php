<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionTextParser;
use Illuminate\Http\Request;

class OcrController extends Controller
{
    public function parse(Request $request, TransactionTextParser $parser)
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ]);

        return response()->json($parser->parse($validated['text']));
    }
}
