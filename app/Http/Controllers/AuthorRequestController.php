<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ImageKitService;
use App\Notifications\AuthorRequestSubmittedNotification;
use App\Notifications\AuthorRequestStatusNotification;
use App\Models\User;

class AuthorRequestController extends Controller
{
    protected $imageKit;

    public function __construct(ImageKitService $imageKit)
    {
        $this->imageKit = $imageKit;
    }
    public function myRequest()
    {
        $user = auth()->user();
        $request = \App\Models\AuthorRequest::where('user_id', $user->id)->latest()->first();
        return response()->json($request);
    }

    public function index()
    {
        // Admin gets all requests
        $requests = \App\Models\AuthorRequest::with('user:id,name,email')->orderBy('created_at', 'desc')->get();
        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // Check if user already has a pending or approved request
        $existingRequest = \App\Models\AuthorRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingRequest) {
            return response()->json(['message' => 'You already have a pending or approved request.'], 400);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'short_bio' => 'required|string',
            'reason' => 'required|string',
            'experience' => 'nullable|string',
            'id_card' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
            'portfolio' => 'nullable|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:10240', // 10MB max
        ]);

        $idCardFile = $request->file('id_card');
        $idCardUpload = $this->imageKit->upload(
            $idCardFile->getPathname(),
            time().'_'.$idCardFile->getClientOriginalName(),
            '/author_requests/id_cards'
        );
        $idCardPath = $idCardUpload->result->url;
        
        $portfolioPath = null;
        if ($request->hasFile('portfolio')) {
            $portfolioFile = $request->file('portfolio');
            $portfolioUpload = $this->imageKit->upload(
                $portfolioFile->getPathname(),
                time().'_'.$portfolioFile->getClientOriginalName(),
                '/author_requests/portfolios'
            );
            $portfolioPath = $portfolioUpload->result->url;
        }

        $authorRequest = \App\Models\AuthorRequest::create([
            'user_id' => $user->id,
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'short_bio' => $validated['short_bio'],
            'reason' => $validated['reason'],
            'experience' => $validated['experience'] ?? null,
            'id_card_path' => $idCardPath,
            'portfolio_path' => $portfolioPath,
            'status' => 'pending',
        ]);

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AuthorRequestSubmittedNotification($authorRequest, $user));
        }

        return response()->json(['message' => 'Request submitted successfully.', 'data' => $authorRequest], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $authorRequest = \App\Models\AuthorRequest::findOrFail($id);

        if ($authorRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 400);
        }

        $authorRequest->update(['status' => $validated['status']]);

        $user = User::find($authorRequest->user_id);

        // If approved, update user role
        if ($validated['status'] === 'approved') {
            if ($user && $user->role !== 'admin') {
                $user->update(['role' => 'author']);
            }
        }

        if ($user) {
            $user->notify(new AuthorRequestStatusNotification($authorRequest, $validated['status']));
        }

        return response()->json(['message' => 'Request status updated successfully.', 'data' => $authorRequest]);
    }
}
