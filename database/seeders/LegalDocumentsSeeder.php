<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LegalDocumentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documents = [
            LegalDocument::TYPE_TERMS => [
                'title' => 'Terms and Conditions',
                'content' => '<h2>Terms and Conditions</h2><p>These default terms explain the expected use of Student Hub services. Replace this starter content with your institution-approved legal terms before production use.</p><p>By using Student Hub, users agree to access only authorized information and follow applicable school policies.</p>',
            ],
            LegalDocument::TYPE_COOKIE_POLICY => [
                'title' => 'Cookie Policy',
                'content' => '<h2>Cookie Policy</h2><p>Student Hub uses essential cookies to maintain secure sessions, remember interface preferences, and protect authenticated areas. Replace this starter content with your approved cookie policy.</p>',
            ],
            LegalDocument::TYPE_PRIVACY_POLICY => [
                'title' => 'Privacy Policy',
                'content' => '<h2>Privacy Policy</h2><p>Student Hub processes account, academic, and service information to provide school services. Replace this starter content with your institution-approved privacy policy.</p>',
            ],
        ];

        foreach ($documents as $type => $document) {
            LegalDocument::query()
                ->where('type', $type)
                ->where(fn ($query) => $query->where('version', '!=', '1.0')->orWhereNull('version'))
                ->update(['is_active' => false]);

            LegalDocument::query()->updateOrCreate(
                [
                    'type' => $type,
                    'version' => '1.0',
                ],
                [
                    'title' => $document['title'],
                    'slug' => Str::slug($document['title']),
                    'content' => $document['content'],
                    'is_active' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
