<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require __DIR__.'/content/pages.php' as $p) {
            if (Page::query()->where('slug', $p['slug'])->exists()) {
                continue;
            }
            Page::create($p + ['kind' => 'page'])->publish('Initial content');
        }

        $legal = [
            'terms' => 'Terms of Service',
            'privacy' => 'Privacy Policy',
            'cancellation' => 'Cancellation & No-Show Policy',
        ];
        foreach ($legal as $slug => $title) {
            if (Page::query()->where('slug', 'legal/'.$slug)->exists()) {
                continue;
            }
            $body = file_get_contents(resource_path("content/legal/{$slug}.md"));
            $body = preg_replace('/^# .*\n+/', '', $body); // the page renders its own title
            Page::create([
                'slug' => 'legal/'.$slug, 'title' => $title, 'kind' => 'legal', 'body' => $body, 'show_in_footer' => true,
                'meta_description' => $title.' for Black & White Ops.',
            ])->publish('Initial legal draft for attorney review');
        }

        if (! Faq::query()->exists()) {
            foreach (require __DIR__.'/content/faqs.php' as $i => [$cat, $q, $a]) {
                Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'sort' => $i])->publish();
            }
        }
    }
}
