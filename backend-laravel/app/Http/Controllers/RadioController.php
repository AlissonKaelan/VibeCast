<?php

namespace App\Http\Controllers;

use App\Models\Radio;
use Illuminate\Http\Request;

class RadioController extends Controller
{
    // Lista todas as rádios
    public function index()
    {
        return response()->json(Radio::latest()->get());
    }

    // Salva uma rádio nova
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'stream_url' => 'required|url|unique:radios,stream_url',
            'logo_url' => 'nullable|url'
        ]);

        $radio = Radio::create($request->all());

        return response()->json(['message' => 'Rádio adicionada com sucesso!', 'radio' => $radio], 201);
    }

    // Apaga uma rádio
    public function destroy($id)
    {
        $radio = Radio::findOrFail($id);
        $radio->delete();

        return response()->json(['message' => 'Rádio removida com sucesso!']);
    }
}