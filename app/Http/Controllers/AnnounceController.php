<?php

namespace App\Http\Controllers;

use App\Models\Announce;
use Illuminate\Http\Request;

class AnnounceController extends Controller
{
    public function index()
    {
        return response()->json(Announce::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'link' => 'nullable|string|max:255',
            'imgUrl' => 'nullable|string',
        ]);

        $announce = Announce::create($request->all());
        return response()->json($announce, 201);
    }

    public function show($id)
    {
        $announce = Announce::findOrFail($id);
        return response()->json($announce);
    }

    public function update(Request $request, $id)
    {
        $announce = Announce::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'link' => 'nullable|string|max:255',
            'imgUrl' => 'nullable|string',
        ]);

        $announce->update($request->all());
        return response()->json($announce);
    }

    public function destroy($id)
    {
        $announce = Announce::findOrFail($id);
        $announce->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
