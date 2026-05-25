<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Genre;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\ImageKitService;

class GenreController extends Controller
{
    protected $imageKit;

    public function __construct(ImageKitService $imageKit)
    {
        $this->imageKit = $imageKit;
    }
    // ✅ List all genres with subgenres and image URLs
    public function index()
    {
        $genres = Genre::with('subgenres')->get()->map(function ($genre) {
            return $this->formatGenre($genre);
        });

        return response()->json($genres);
    }

    // ✅ Show single genre with subgenres and image URL
    public function show($id)
    {
        $genre = Genre::with('subgenres')->find($id);
        if (!$genre) {
            return response()->json(['error' => 'Genre not found'], 404);
        }

        return response()->json($this->formatGenre($genre));
    }

    // ✅ Admin creates a genre
    public function store(Request $request)
{
    $currentUser = JWTAuth::parseToken()->authenticate();

    if ($currentUser->role !== 'admin' && $currentUser->role !== 'author') {
        return response()->json([
            'error' => 'Unauthorized. Only admin and author can create genres.'
        ], 403);
    }

    $request->validate([
        'name' => 'required|string|max:255',
        'parent_id' => 'nullable|exists:genres,id',
        'image' => 'nullable|image|max:5120',
    ]);

    Log::info('Creating genre with data: ', $request->all());

    /* =====================
       Generate unique slug
    ===================== */
    $baseSlug = Str::slug($request->name);
    $slug = $baseSlug;
    $count = 1;

    while (Genre::where('slug', $slug)->exists()) {
       return response()->json(['error' => 'Genre slug already exists. Please choose a different name.'], 422);
    }

    /* =====================
       Upload image
    ===================== */
    $imagePath = null;
    if ($request->hasFile('image')) {
        $file = $request->file('image');
        $upload = $this->imageKit->upload(
            $file->getPathname(),
            time().'_'.$file->getClientOriginalName(),
            '/genres/images'
        );
        $imagePath = $upload->result->url;
    }

    /* =====================
       Create genre
    ===================== */
    $genre = Genre::create([
        'name'      => $request->name,
        'slug'      => $slug,
        'parent_id' => $request->parent_id,
        'image'     => $imagePath,
    ]);

    

    return response()->json([
        'message' => 'Genre created successfully',
        'genre'   => $this->formatGenre($genre),
    ], 201);
}


    // ✅ Admin updates a genre
public function update(Request $request, $id)
{
    $currentUser = JWTAuth::parseToken()->authenticate();
    if ($currentUser->role !== 'admin') {
        return response()->json(['error' => 'Unauthorized. Only admin can update genres.'], 403);
    }

    $genre = Genre::find($id);
    if (!$genre) {
        return response()->json(['error' => 'Genre not found'], 404);
    }

    $request->validate([
        'name' => 'sometimes|required|string|max:255',
        'parent_id' => 'nullable|exists:genres,id',
        'image' => 'nullable|image|max:5120'
    ]);

    // Update fields
    if ($request->has('name')) $genre->name = $request->name;
    if ($request->has('parent_id')) $genre->parent_id = $request->parent_id;

    if ($request->hasFile('image')) {
        if ($genre->image && !filter_var($genre->image, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($genre->image);
        }
        $file = $request->file('image');
        $upload = $this->imageKit->upload(
            $file->getPathname(),
            time().'_'.$file->getClientOriginalName(),
            '/genres/images'
        );
        $genre->image = $upload->result->url;
    }

    $genre->save();

    return response()->json([
        'message' => 'Genre updated successfully',
        'genre' => $this->formatGenre($genre)
    ]);
}


    // ✅ Admin deletes a genre
    public function destroy($id)
    {
        $currentUser = JWTAuth::parseToken()->authenticate();
        if ($currentUser->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized. Only admin can delete genres.'], 403);
        }

        $genre = Genre::find($id);
        if (!$genre) {
            return response()->json(['error' => 'Genre not found'], 404);
        }

        if ($genre->image && !filter_var($genre->image, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($genre->image);
        }

        $genre->delete();

        return response()->json([
            'message' => 'Genre deleted successfully'
        ]);
    }

    // 🔹 Helper function to format genre with image_url
    private function formatGenre($genre)
    {
        return [
            'id' => $genre->id,
            'name' => $genre->name,
            'parent_id' => $genre->parent_id,
            'slug' => $genre->slug,
            'image' => $genre->image,
            'image_url' => $genre->image ? (filter_var($genre->image, FILTER_VALIDATE_URL) ? $genre->image : url("storage/{$genre->image}")) : null,
            'created_at' => $genre->created_at,
            'updated_at' => $genre->updated_at,
            'subgenres' => $genre->subgenres->map(function ($sub) {
                return $this->formatGenre($sub);
            })
        ];
    }
}
