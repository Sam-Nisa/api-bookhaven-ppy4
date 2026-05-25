<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Genre;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            [
                'name' => 'Science Fiction',
                'slug' => 'science-fiction',
                'image' => 'genres/science-fiction.jpg',
            ],
            [
                'name' => 'Fantasy',
                'slug' => 'fantasy',
                'image' => 'genres/fantasy.jpg',
            ],
            [
                'name' => 'Mystery',
                'slug' => 'mystery',
                'image' => 'genres/mystery.jpg',
            ],
        ];

        foreach ($genres as $genre) {
            Genre::create([
                'name' => $genre['name'],
                'slug' => $genre['slug'],
                'parent_id' => null,
                'image' => $genre['image'],
            ]);
        }
    }
}
