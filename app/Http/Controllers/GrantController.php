<?php

namespace App\Http\Controllers;

use App\Models\GrantApplication;
use Illuminate\Http\Request;

class GrantController extends Controller
{
    public function apply(Request $request)
    {
        $data = $request->validate([
            'project_title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'description' => 'required|string|min:50|max:5000',
            'requested_amount_cfa' => 'required|integer|min:100000|max:100000000',
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'id_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $request->file('id_document')->store('kyc/grants', 'public');

        $grant = GrantApplication::create([
            'user_id' => $request->user()->id,
            'project_title' => $data['project_title'],
            'category' => $data['category'],
            'description' => $data['description'],
            'requested_amount_cfa' => $data['requested_amount_cfa'],
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'id_document_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Projet soumis. Un agent examinera votre dossier.',
            'grant' => $grant,
        ], 201);
    }

    public function mine(Request $request)
    {
        return response()->json(
            GrantApplication::where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->get()
        );
    }
}
